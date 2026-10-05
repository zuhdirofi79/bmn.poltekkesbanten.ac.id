<?php

declare(strict_types=1);

namespace App\Services\Persediaan;

use App\Models\Persediaan\PersediaanBarang;
use App\Models\Persediaan\PersediaanPermintaan;
use App\Models\Persediaan\PersediaanStockMovement;
use App\Support\Persediaan\PublicUploadPath;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Klasifikasi hapus permanen. Stock movement tidak pernah dihapus.
 */
final class PersediaanDeletionService
{
    public const BARANG_BLOCKED = 'Barang tidak dapat dihapus permanen karena sudah memiliki histori transaksi. Gunakan Soft Delete agar histori stok dan laporan tetap terjaga.';

    public const PERMINTAAN_BLOCKED = 'Permintaan ini tidak dapat dihapus permanen karena telah menghasilkan transaksi barang keluar dan memengaruhi stok. Gunakan Soft Delete untuk menjaga konsistensi stok dan audit trail.';

    /** @var list<string> */
    public const STOCK_AFFECTING_STATUSES = [
        PersediaanPermintaan::STATUS_DISETUJUI,
        PersediaanPermintaan::STATUS_DIKELUARKAN,
        PersediaanPermintaan::STATUS_SELESAI,
    ];

    /**
     * @param  list<int|string>  $ids
     * @return list<int>
     */
    public function normalizeIds(array $ids): array
    {
        $clean = [];
        foreach ($ids as $id) {
            if (is_numeric($id) && (int) $id > 0) {
                $clean[] = (int) $id;
            }
        }

        return array_values(array_unique($clean));
    }

    /**
     * @param  list<int>  $ids
     * @return list<int>
     */
    public function barangIdsWithHistory(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $blocked = [];
        foreach ([
            'persediaan_permintaan_items',
            'persediaan_pembelian_items',
            'persediaan_stock_movements',
            'persediaan_stock_opname_items',
            'persediaan_rak_barang',
            'persediaan_rak_movements',
        ] as $table) {
            foreach ($this->idsPresentIn($table, 'barang_id', $ids) as $id) {
                $blocked[$id] = true;
            }
        }

        return array_map('intval', array_keys($blocked));
    }

    /**
     * Permintaan yang sudah mengurangi stok, atau statusnya sudah lewat persetujuan.
     *
     * @param  list<int>  $ids
     * @return list<int>
     */
    public function permintaanIdsAffectingStock(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $blocked = [];

        if (Schema::hasTable('persediaan_stock_movements')) {
            $movementIds = DB::table('persediaan_stock_movements')
                ->where('ref_table', 'persediaan_permintaan')
                ->whereIn('ref_id', $ids)
                ->where(function ($query): void {
                    $query->where('tipe', PersediaanStockMovement::TIPE_KELUAR)
                        ->orWhere('qty_keluar', '>', 0);
                })
                ->pluck('ref_id');

            foreach ($movementIds as $id) {
                $blocked[(int) $id] = true;
            }
        }

        if (Schema::hasTable('persediaan_permintaan')) {
            $statusIds = PersediaanPermintaan::withTrashed()
                ->whereIn('id', $ids)
                ->whereIn('status', self::STOCK_AFFECTING_STATUSES)
                ->pluck('id');

            foreach ($statusIds as $id) {
                $blocked[(int) $id] = true;
            }
        }

        return array_map('intval', array_keys($blocked));
    }

    /**
     * @param  list<int|string|null>  $relativePaths
     */
    public function deleteExclusiveBarcodes(array $relativePaths): void
    {
        foreach ($relativePaths as $path) {
            $this->deleteExclusiveFile($path, PublicUploadPath::BARCODE_REL_DIR, 'barcode_path', PersediaanBarang::class);
        }
    }

    /**
     * @param  list<int|string|null>  $relativePaths
     */
    public function deleteExclusiveSignatures(array $relativePaths): void
    {
        foreach ($relativePaths as $path) {
            $this->deleteExclusiveSignature($path);
        }
    }

    /**
     * @param  list<int>  $ids
     * @return list<int>
     */
    private function idsPresentIn(string $table, string $column, array $ids): array
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return [];
        }

        return DB::table($table)
            ->whereIn($column, $ids)
            ->distinct()
            ->pluck($column)
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * @param  class-string<PersediaanBarang>  $model
     */
    private function deleteExclusiveFile(mixed $relativePath, string $allowedDir, string $column, string $model): void
    {
        $relative = $this->exclusiveRelative($relativePath, $allowedDir);
        if ($relative === null) {
            return;
        }

        $stillUsed = $model::withTrashed()->where($column, $relative)->exists();
        if ($stillUsed) {
            return;
        }

        $this->unlinkIfPresent($relative);
    }

    private function deleteExclusiveSignature(mixed $relativePath): void
    {
        $relative = $this->exclusiveRelative($relativePath, 'uploads/persediaan/ttd');
        if ($relative === null) {
            return;
        }

        $stillUsed = PersediaanPermintaan::withTrashed()
            ->where(function ($query) use ($relative): void {
                $query->where('tanda_tangan_pemohon_path', $relative)
                    ->orWhere('tanda_tangan_admin_path', $relative);
            })
            ->exists();

        if ($stillUsed) {
            return;
        }

        $this->unlinkIfPresent($relative);
    }

    private function exclusiveRelative(mixed $relativePath, string $allowedDir): ?string
    {
        if (! is_string($relativePath) || trim($relativePath) === '') {
            return null;
        }

        $relative = str_replace('\\', '/', trim($relativePath));
        $relative = ltrim($relative, '/');
        $allowedDir = trim(str_replace('\\', '/', $allowedDir), '/');

        if ($relative !== $allowedDir && ! str_starts_with($relative, $allowedDir . '/')) {
            return null;
        }

        return $relative;
    }

    private function unlinkIfPresent(string $relative): void
    {
        $absolute = PublicUploadPath::firstExistingFile($relative);
        if ($absolute === null || ! is_file($absolute)) {
            return;
        }

        @unlink($absolute);
    }
}
