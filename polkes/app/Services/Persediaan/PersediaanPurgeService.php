<?php

declare(strict_types=1);

namespace App\Services\Persediaan;

use App\Models\Persediaan\PersediaanBarang;
use App\Models\Persediaan\PersediaanBarangArsip;
use App\Models\Persediaan\PersediaanPurgeBatch;
use App\Models\Persediaan\PersediaanPurgeLog;
use App\Support\Persediaan\PersediaanAdminSession;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Hapus fisik master barang yang sudah di sampah.
 * Histori transaksi tidak dihapus. Identitas master disalin ke arsip lebih dulu.
 */
final class PersediaanPurgeService
{
    public const BATCH_SIZE = 40;

    public const CONFIRM_ALL = 'HAPUS PERMANEN';

    /** @var list<string> */
    private const SUPPORTED_TABLES = [
        'persediaan_permintaan_items',
        'persediaan_pembelian_items',
        'persediaan_stock_movements',
        'persediaan_stock_opname_items',
        'persediaan_rak_barang',
        'persediaan_rak_movements',
    ];

    public function __construct(private PersediaanDeletionService $deletion)
    {
    }

    /**
     * @param  list<int>  $ids
     * @return array{total:int,with_history:int,without_history:int,unsupported:int}
     */
    public function preview(Builder $trashedQuery, array $ids = []): array
    {
        $query = clone $trashedQuery;
        if ($ids !== []) {
            $query->whereIn('id', $ids);
        }

        $targetIds = $query->orderBy('id')->pluck('id')->map(static fn ($id): int => (int) $id)->all();
        $withHistory = array_flip($this->deletion->barangIdsWithHistory($targetIds));
        $unsupported = array_flip($this->idsWithUnsupportedDependency($targetIds));
        $historyCount = 0;
        $unsupportedCount = 0;
        foreach ($targetIds as $id) {
            if (isset($unsupported[$id])) {
                $unsupportedCount++;
                continue;
            }
            if (isset($withHistory[$id])) {
                $historyCount++;
            }
        }

        return [
            'total'         => count($targetIds),
            'with_history'  => $historyCount,
            'without_history' => count($targetIds) - $historyCount - $unsupportedCount,
            'unsupported'   => $unsupportedCount,
        ];
    }

    /**
     * @param  list<int>  $ids
     * @return array<string, mixed>
     */
    public function start(Request $request, Builder $trashedQuery, string $mode, array $ids = []): array
    {
        $this->assertSchema();
        $adminId = PersediaanAdminSession::userId($request);

        $busy = PersediaanPurgeBatch::query()
            ->where('status', PersediaanPurgeBatch::STATUS_RUNNING)
            ->where('locked_at', '>=', now()->subMinutes(2))
            ->exists();
        if ($busy) {
            return ['ok' => false, 'http_status' => 409, 'message' => 'Penghapusan massal lain masih berjalan. Tunggu sampai selesai, lalu coba lagi.'];
        }

        $targets = (clone $trashedQuery)->orderBy('id');
        if ($mode !== PersediaanPurgeBatch::MODE_ALL) {
            $targets->whereIn('id', $ids);
        }
        $rows = $targets->get(['id', 'kode_barang', 'nama_barang', 'unit_kerja']);

        $batch = PersediaanPurgeBatch::query()->create([
            'public_id' => (string) Str::uuid(),
            'admin_id'  => $adminId,
            'mode'      => $mode,
            'status'    => PersediaanPurgeBatch::STATUS_RUNNING,
            'total'     => $rows->count() + $this->rejectedActiveCount($request, $mode, $ids, $rows->pluck('id')->all()),
            'locked_at' => now(),
        ]);

        foreach ($rows->chunk(40) as $chunk) {
            $insert = [];
            $now = now();
            foreach ($chunk as $barang) {
                $insert[] = [
                    'batch_id'           => $batch->id,
                    'original_barang_id' => (int) $barang->id,
                    'kode_barang'        => (string) $barang->kode_barang,
                    'nama_barang'        => (string) $barang->nama_barang,
                    'unit_kerja'         => (string) $barang->unit_kerja,
                    'status'             => PersediaanPurgeLog::STATUS_PENDING,
                    'history_archived'   => false,
                    'created_at'         => $now,
                    'updated_at'         => $now,
                ];
            }
            DB::table('persediaan_purge_logs')->insert($insert);
        }

        if ($mode !== PersediaanPurgeBatch::MODE_ALL) {
            $this->logRejectedIds($request, $batch, $ids, $rows->pluck('id')->map(static fn ($id): int => (int) $id)->all());
        }

        $batch = $batch->fresh();
        $pending = $batch->logs()->where('status', PersediaanPurgeLog::STATUS_PENDING)->exists();
        if (! $pending) {
            $batch->status = PersediaanPurgeBatch::STATUS_DONE;
            $batch->locked_at = null;
            $batch->save();
        }

        return ['ok' => true, 'http_status' => 200] + $this->present($batch->fresh());
    }

    /**
     * @return array<string, mixed>
     */
    public function step(PersediaanPurgeBatch $batch): array
    {
        $this->assertSchema();
        $batch->refresh();
        if ($batch->status === PersediaanPurgeBatch::STATUS_DONE) {
            return $this->present($batch);
        }

        if ($this->stepIsBusy($batch)) {
            return $this->present($batch) + ['busy' => true];
        }

        $token = Str::random(20);
        $batch->step_token = $token;
        $batch->locked_at = now();
        $batch->status = PersediaanPurgeBatch::STATUS_RUNNING;
        $batch->save();

        try {
            $logs = PersediaanPurgeLog::query()
                ->where('batch_id', $batch->id)
                ->where('status', PersediaanPurgeLog::STATUS_PENDING)
                ->orderBy('id')
                ->limit(self::BATCH_SIZE)
                ->get();

            foreach ($logs as $log) {
                $this->purgeLog($log);
            }

            $this->refreshCounts($batch);
            $pending = PersediaanPurgeLog::query()
                ->where('batch_id', $batch->id)
                ->where('status', PersediaanPurgeLog::STATUS_PENDING)
                ->exists();
            $batch->status = $pending ? PersediaanPurgeBatch::STATUS_RUNNING : PersediaanPurgeBatch::STATUS_DONE;
            $batch->save();
        } finally {
            $batch->step_token = null;
            if ($batch->status === PersediaanPurgeBatch::STATUS_DONE) {
                $batch->locked_at = null;
            }
            $batch->save();
        }

        return $this->present($batch->fresh());
    }

    /**
     * Satu atau beberapa id sampah, diproses sampai selesai. Untuk aksi per item.
     *
     * @param  list<int>  $ids
     * @return array<string, mixed>
     */
    public function purgeNow(Request $request, Builder $trashedQuery, array $ids, string $mode): array
    {
        $started = $this->start($request, $trashedQuery, $mode, $ids);
        if ($started['ok'] !== true) {
            return $started;
        }

        $batch = PersediaanPurgeBatch::query()->where('public_id', $started['batch_id'])->firstOrFail();
        $guard = 0;
        do {
            $state = $this->step($batch);
            $guard++;
        } while (($state['status'] ?? '') === PersediaanPurgeBatch::STATUS_RUNNING && $guard < 100);

        return ['ok' => true, 'http_status' => 200] + $state;
    }

    /**
     * @return array<string, mixed>
     */
    public function present(PersediaanPurgeBatch $batch): array
    {
        $batch->refresh();
        $total = (int) $batch->total;
        $processed = (int) $batch->processed;
        $failures = PersediaanPurgeLog::query()
            ->where('batch_id', $batch->id)
            ->where('status', PersediaanPurgeLog::STATUS_FAILED)
            ->orderBy('id')
            ->limit(8)
            ->get(['kode_barang', 'nama_barang', 'unit_kerja', 'message'])
            ->map(static fn (PersediaanPurgeLog $log): array => [
                'kode'    => (string) $log->kode_barang,
                'nama'    => (string) $log->nama_barang,
                'unit'    => (string) $log->unit_kerja,
                'message' => (string) $log->message,
            ])
            ->all();

        return [
            'batch_id'   => (string) $batch->public_id,
            'mode'       => (string) $batch->mode,
            'status'     => (string) $batch->status,
            'total'      => $total,
            'processed'  => $processed,
            'succeeded'  => (int) $batch->succeeded,
            'failed'     => (int) $batch->failed,
            'percent'    => $total === 0 ? 100 : (int) floor(($processed / $total) * 100),
            'failures'   => $failures,
        ];
    }

    public function failureCsv(PersediaanPurgeBatch $batch): string
    {
        $lines = ["kode_barang,nama_barang,unit_kerja,alasan"];
        $logs = PersediaanPurgeLog::query()
            ->where('batch_id', $batch->id)
            ->where('status', PersediaanPurgeLog::STATUS_FAILED)
            ->orderBy('id')
            ->get();
        foreach ($logs as $log) {
            $lines[] = implode(',', [
                $this->csv((string) $log->kode_barang),
                $this->csv((string) $log->nama_barang),
                $this->csv((string) $log->unit_kerja),
                $this->csv((string) $log->message),
            ]);
        }

        return implode("\n", $lines) . "\n";
    }

    private function purgeLog(PersediaanPurgeLog $log): void
    {
        $path = null;
        $committed = false;
        try {
            DB::transaction(function () use ($log, &$path, &$committed): void {
                $barang = PersediaanBarang::onlyTrashed()->whereKey($log->original_barang_id)->lockForUpdate()->first();
                if ($barang === null) {
                    $archived = PersediaanBarangArsip::query()->where('original_barang_id', $log->original_barang_id)->exists();
                    $this->finishLog($log, $archived ? PersediaanPurgeLog::STATUS_PURGED : PersediaanPurgeLog::STATUS_FAILED, $archived, $archived ? 'Sudah dihapus sebelumnya.' : 'Barang tidak berada di sampah.');

                    return;
                }

                $blocked = $this->idsWithUnsupportedDependency([(int) $barang->id]);
                if ($blocked !== []) {
                    $tables = $this->unsupportedTablesFor((int) $barang->id);
                    $this->finishLog($log, PersediaanPurgeLog::STATUS_FAILED, false, 'Dependensi belum didukung: ' . implode(', ', $tables));

                    return;
                }

                $hasHistory = $this->deletion->barangIdsWithHistory([(int) $barang->id]) !== [];
                $this->archive($barang, $log->batch()->value('admin_id'));
                $this->backfillSnapshots($barang);
                $this->releaseRakAssignment($barang);
                $path = $barang->barcode_path;
                $barang->forceDelete();
                $this->finishLog($log, PersediaanPurgeLog::STATUS_PURGED, $hasHistory, $hasHistory ? 'Histori diarsipkan, master dihapus.' : 'Master dihapus.');
                $committed = true;
            });
            if ($committed && is_string($path) && $path !== '' && $log->fresh()->status === PersediaanPurgeLog::STATUS_PURGED) {
                $this->deletion->deleteExclusiveBarcodes([$path]);
            }
        } catch (\Throwable $e) {
            report($e);
            $log->refresh();
            if ($log->status === PersediaanPurgeLog::STATUS_PENDING) {
                $this->finishLog($log, PersediaanPurgeLog::STATUS_FAILED, false, 'Gagal diproses. Data master tidak diubah.');
            }
        }
    }

    private function archive(PersediaanBarang $barang, mixed $adminId): void
    {
        $exists = PersediaanBarangArsip::query()->where('original_barang_id', $barang->id)->exists();
        if ($exists) {
            return;
        }

        $rakId = null;
        if (Schema::hasTable('persediaan_rak_barang')) {
            $rakId = DB::table('persediaan_rak_barang')->where('barang_id', $barang->id)->value('rak_id');
        }

        PersediaanBarangArsip::query()->create([
            'original_barang_id' => (int) $barang->id,
            'kode_barang'        => (string) $barang->kode_barang,
            'nama_barang'        => (string) $barang->nama_barang,
            'satuan'             => (string) $barang->satuan,
            'unit_kerja'         => (string) $barang->unit_kerja,
            'stok_awal'          => (int) $barang->stok_awal,
            'stok_minimum'       => (int) $barang->stok_minimum,
            'harga_satuan'       => $barang->harga_satuan,
            'is_active'          => (bool) $barang->is_active,
            'barcode_payload'    => $barang->barcode_payload,
            'rak_id'             => $rakId !== null ? (int) $rakId : null,
            'soft_deleted_at'    => $barang->deleted_at,
            'archived_by'        => is_numeric($adminId) ? (int) $adminId : null,
            'archived_at'        => now(),
        ]);
    }

    private function backfillSnapshots(PersediaanBarang $barang): void
    {
        $identity = [
            'kode_barang' => (string) $barang->kode_barang,
            'nama_barang' => (string) $barang->nama_barang,
            'satuan'      => (string) $barang->satuan,
        ];

        if (Schema::hasTable('persediaan_stock_movements') && Schema::hasColumn('persediaan_stock_movements', 'nama_barang')) {
            $payload = $identity;
            if (Schema::hasColumn('persediaan_stock_movements', 'harga_satuan')) {
                $payload['harga_satuan'] = $barang->harga_satuan;
            }
            DB::table('persediaan_stock_movements')
                ->where('barang_id', $barang->id)
                ->where(function ($query): void {
                    $query->whereNull('nama_barang')->orWhere('nama_barang', '');
                })
                ->update($payload);
        }

        if (Schema::hasTable('persediaan_rak_movements') && Schema::hasColumn('persediaan_rak_movements', 'nama_barang')) {
            DB::table('persediaan_rak_movements')
                ->where('barang_id', $barang->id)
                ->where(function ($query): void {
                    $query->whereNull('nama_barang')->orWhere('nama_barang', '');
                })
                ->update($identity);
        }

        foreach (['persediaan_permintaan_items', 'persediaan_pembelian_items', 'persediaan_stock_opname_items'] as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'nama_barang')) {
                continue;
            }
            DB::table($table)
                ->where('barang_id', $barang->id)
                ->where(function ($query): void {
                    $query->whereNull('nama_barang')->orWhere('nama_barang', '');
                })
                ->update($identity);
        }
    }

    private function releaseRakAssignment(PersediaanBarang $barang): void
    {
        if (! Schema::hasTable('persediaan_rak_barang')) {
            return;
        }

        DB::table('persediaan_rak_barang')->where('barang_id', $barang->id)->delete();
    }

    private function finishLog(PersediaanPurgeLog $log, string $status, bool $historyArchived, string $message): void
    {
        $log->status = $status;
        $log->history_archived = $historyArchived;
        $log->message = Str::limit($message, 240, '');
        $log->processed_at = now();
        $log->save();
    }

    private function refreshCounts(PersediaanPurgeBatch $batch): void
    {
        $batch->succeeded = (int) PersediaanPurgeLog::query()
            ->where('batch_id', $batch->id)
            ->where('status', PersediaanPurgeLog::STATUS_PURGED)
            ->count();
        $batch->failed = (int) PersediaanPurgeLog::query()
            ->where('batch_id', $batch->id)
            ->where('status', PersediaanPurgeLog::STATUS_FAILED)
            ->count();
        $batch->processed = $batch->succeeded + $batch->failed;
        $batch->save();
    }

    private function stepIsBusy(PersediaanPurgeBatch $batch): bool
    {
        return $batch->step_token !== null
            && $batch->step_token !== ''
            && $batch->locked_at !== null
            && $batch->locked_at->gt(now()->subSeconds(45));
    }

    /**
     * @param  list<int>  $ids
     * @param  list<int>  $trashedIds
     */
    private function logRejectedIds(Request $request, PersediaanPurgeBatch $batch, array $ids, array $trashedIds): void
    {
        $trashed = array_flip($trashedIds);
        $activeIds = [];
        foreach ($ids as $id) {
            if (! isset($trashed[$id])) {
                $activeIds[] = $id;
            }
        }
        if ($activeIds === []) {
            return;
        }

        $active = $this->activeInScope($request, $activeIds);
        $now = now();
        $insert = [];
        foreach ($active as $barang) {
            $insert[] = [
                'batch_id'           => $batch->id,
                'original_barang_id' => (int) $barang->id,
                'kode_barang'        => (string) $barang->kode_barang,
                'nama_barang'        => (string) $barang->nama_barang,
                'unit_kerja'         => (string) $barang->unit_kerja,
                'status'             => PersediaanPurgeLog::STATUS_FAILED,
                'history_archived'   => false,
                'message'            => 'Barang aktif tidak dapat dihapus permanen.',
                'processed_at'       => $now,
                'created_at'         => $now,
                'updated_at'         => $now,
            ];
        }
        if ($insert !== []) {
            DB::table('persediaan_purge_logs')->insert($insert);
        }
        $this->refreshCounts($batch);
    }

    /**
     * @param  list<int>  $ids
     * @param  list<int>  $trashedIds
     */
    private function rejectedActiveCount(Request $request, string $mode, array $ids, array $trashedIds): int
    {
        if ($mode === PersediaanPurgeBatch::MODE_ALL) {
            return 0;
        }

        $missing = array_values(array_diff($ids, $trashedIds));

        return $missing === [] ? 0 : $this->activeInScope($request, $missing)->count();
    }

    /**
     * @param  list<int>  $ids
     */
    private function activeInScope(Request $request, array $ids)
    {
        $query = PersediaanBarang::query()->whereIn('id', $ids);
        if (! PersediaanAdminSession::isSuperadmin($request)) {
            $admin = PersediaanAdminSession::activeAdmin($request);
            $satker = $admin?->effectiveSatker();
            if ($satker !== null) {
                $query->where('unit_kerja', $satker);
            }
        }

        return $query->get(['id', 'kode_barang', 'nama_barang', 'unit_kerja']);
    }

    /**
     * @param  list<int>  $ids
     * @return list<int>
     */
    private function idsWithUnsupportedDependency(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $blocked = [];
        foreach ($this->referencingTables() as $table) {
            if (in_array($table, self::SUPPORTED_TABLES, true)) {
                continue;
            }
            $found = DB::table($table)->whereIn('barang_id', $ids)->pluck('barang_id');
            foreach ($found as $id) {
                $blocked[(int) $id] = true;
            }
        }

        return array_map('intval', array_keys($blocked));
    }

    /**
     * @return list<string>
     */
    private function unsupportedTablesFor(int $id): array
    {
        $names = [];
        foreach ($this->referencingTables() as $table) {
            if (in_array($table, self::SUPPORTED_TABLES, true)) {
                continue;
            }
            if (DB::table($table)->where('barang_id', $id)->exists()) {
                $names[] = $table;
            }
        }

        return $names;
    }

    /**
     * @return list<string>
     */
    private function referencingTables(): array
    {
        $names = [];
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'sqlite') {
            foreach (DB::select("SELECT name FROM sqlite_master WHERE type = 'table'") as $row) {
                $names[] = (string) $row->name;
            }
        } else {
            foreach (DB::select('SHOW TABLES') as $row) {
                $values = array_values((array) $row);
                $names[] = (string) ($values[0] ?? '');
            }
        }

        $hit = [];
        foreach ($names as $name) {
            if (! str_starts_with($name, 'persediaan_') || ! Schema::hasTable($name) || ! Schema::hasColumn($name, 'barang_id')) {
                continue;
            }
            if (in_array($name, ['persediaan_barang', 'persediaan_barang_arsip', 'persediaan_purge_logs'], true)) {
                continue;
            }
            $hit[] = $name;
        }

        return $hit;
    }

    private function assertSchema(): void
    {
        foreach (['persediaan_barang_arsip', 'persediaan_purge_batches', 'persediaan_purge_logs'] as $table) {
            if (! Schema::hasTable($table)) {
                throw new \RuntimeException('Tabel ' . $table . ' belum ada. Jalankan migration terlebih dahulu.');
            }
        }
    }

    private function csv(string $value): string
    {
        return '"' . str_replace('"', '""', $value) . '"';
    }
}
