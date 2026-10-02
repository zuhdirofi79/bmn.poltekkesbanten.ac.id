<?php

declare(strict_types=1);

namespace App\Services\Persediaan;

use App\Models\Persediaan\PersediaanBarang;
use App\Models\Persediaan\PersediaanStockMovement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Satu sumber stok dan nilai persediaan.
 *
 * Nilai pembuka memakai stok_awal × harga master.
 * Nilai saat ini memakai stok saat ini × harga master yang sama,
 * supaya nilai pembuka + masuk − keluar + koreksi = nilai saat ini.
 *
 * Movement tipe stok_awal tidak dijumlahkan lagi.
 * Pembelian, pengeluaran permintaan, dan koreksi opname dihitung sekali
 * dari persediaan_stock_movements. Harga transaksi tidak dipakai:
 * kolom movement tidak menyimpan snapshot harga.
 *
 * Mengubah harga_satuan master menilai ulang seluruh saldo dan movement
 * dengan harga baru. Tidak ada cache total.
 */
class PersediaanValuationService
{
    /**
     * @return Builder<PersediaanBarang>
     */
    public function activeCatalogQuery(): Builder
    {
        return PersediaanBarang::query()->where('is_active', true);
    }

    /**
     * Nilai pembuka katalog: SUM(stok_awal × harga_satuan).
     * Bukan nilai persediaan saat ini.
     *
     * @param  Builder<PersediaanBarang>  $query
     */
    public function calculateTotalValue(Builder $query): string
    {
        $row = (clone $query)
            ->toBase()
            ->reorder()
            ->selectRaw('COALESCE(SUM(COALESCE(stok_awal, 0) * COALESCE(harga_satuan, 0)), 0) as aggregate')
            ->first();

        return $this->decimal($row->aggregate ?? '0');
    }

    public function rowValue(mixed $stokAwal, mixed $hargaSatuan): string
    {
        $stok = ($stokAwal === null || $stokAwal === '') ? '0' : (string) (int) $stokAwal;
        $harga = ($hargaSatuan === null || $hargaSatuan === '') ? '0.00' : $this->decimal($hargaSatuan);

        return bcmul($stok, $harga, 2);
    }

    /**
     * Rekonsiliasi stok dan nilai untuk katalog terfilter.
     *
     * from/to kosong = seluruh movement sampai saat ini.
     * from/to terisi = movement di dalam periode; stok pembuka adalah saldo sebelum from;
     * stok saat ini adalah saldo sampai akhir tanggal to.
     *
     * @param  array{unit?: ?string, from?: ?string, to?: ?string, q?: ?string, active_only?: bool}  $filters
     * @return array{summary: array<string, int|string>, rows: list<array<string, int|string>>}
     */
    public function reconcile(array $filters = []): array
    {
        $from = $this->dayStart($filters['from'] ?? null);
        $to = $this->dayEnd($filters['to'] ?? null);
        $barangs = $this->catalog($filters)->get();

        $ids = $barangs->pluck('id')->map(static fn ($id): int => (int) $id)->all();
        $agg = $this->aggregateMovements($ids, $from, $to);

        $rows = [];
        $summary = $this->emptySummary();
        $summary['total_jenis_barang'] = $barangs->count();
        $summary['barang_tanpa_harga'] = $barangs->filter(static fn ($b): bool => $b->harga_satuan === null)->count();

        foreach ($barangs as $barang) {
            $id = (int) $barang->id;
            $move = $agg[$id] ?? $this->emptyMove();
            $pembuka = (int) $barang->stok_awal + $move['masuk_sebelum'] - $move['keluar_sebelum'];
            $saatIni = $pembuka + $move['masuk'] - $move['keluar'] + $move['koreksi'];
            $harga = $barang->harga_satuan === null ? '0.00' : $this->decimal($barang->harga_satuan);

            $row = [
                'id'              => $id,
                'kode'            => (string) $barang->kode_barang,
                'nama'            => (string) $barang->nama_barang,
                'unit'            => (string) $barang->unit_kerja,
                'satuan'          => (string) $barang->satuan,
                'stok_minimum'    => (int) $barang->stok_minimum,
                'stok_pembuka'    => $pembuka,
                'masuk'           => $move['masuk'],
                'keluar'          => $move['keluar'],
                'koreksi'         => $move['koreksi'],
                'stok_saat_ini'   => $saatIni,
                'harga'           => $harga,
                'nilai_pembuka'   => bcmul((string) $pembuka, $harga, 2),
                'nilai_masuk'     => bcmul((string) $move['masuk'], $harga, 2),
                'nilai_keluar'    => bcmul((string) $move['keluar'], $harga, 2),
                'nilai_koreksi'   => bcmul((string) $move['koreksi'], $harga, 2),
                'nilai_saat_ini'  => bcmul((string) $saatIni, $harga, 2),
            ];
            $rows[] = $row;
            $this->addRow($summary, $row);
        }

        return ['summary' => $summary, 'rows' => $rows];
    }

    /**
     * Grafik kuantitas dan nilai. Opname tidak masuk ke series masuk/keluar.
     *
     * @param  list<int>  $barangIds
     * @param  array<int, string>  $hargaById
     * @return array{labels: list<string>, masuk: list<int>, keluar: list<int>, nilai_masuk: list<string>, nilai_keluar: list<string>}
     */
    public function monthlyChart(array $barangIds, array $hargaById, Carbon $until, int $months = 6): array
    {
        $end = (clone $until)->endOfMonth();
        $start = (clone $until)->startOfMonth()->subMonths($months - 1);
        $buckets = [];
        $labels = [];
        for ($i = 0; $i < $months; $i++) {
            $month = (clone $start)->addMonths($i);
            $key = $month->format('Y-m');
            $buckets[$key] = ['masuk' => 0, 'keluar' => 0, 'nilai_masuk' => '0.00', 'nilai_keluar' => '0.00'];
            $labels[] = $month->translatedFormat('M Y');
        }

        if ($barangIds !== []) {
            $movements = PersediaanStockMovement::query()
                ->whereIn('barang_id', $barangIds)
                ->whereIn('tipe', [PersediaanStockMovement::TIPE_PEMBELIAN, PersediaanStockMovement::TIPE_KELUAR])
                ->where('created_at', '>=', $start->toDateTimeString())
                ->where('created_at', '<=', $end->toDateTimeString())
                ->get(['barang_id', 'tipe', 'qty_masuk', 'qty_keluar', 'created_at']);

            foreach ($movements as $movement) {
                $key = Carbon::parse($movement->created_at)->format('Y-m');
                if (! isset($buckets[$key])) {
                    continue;
                }
                $harga = $hargaById[(int) $movement->barang_id] ?? '0.00';
                if ($movement->tipe === PersediaanStockMovement::TIPE_PEMBELIAN) {
                    $qty = (int) $movement->qty_masuk;
                    $buckets[$key]['masuk'] += $qty;
                    $buckets[$key]['nilai_masuk'] = bcadd($buckets[$key]['nilai_masuk'], bcmul((string) $qty, $harga, 2), 2);
                } else {
                    $qty = (int) $movement->qty_keluar;
                    $buckets[$key]['keluar'] += $qty;
                    $buckets[$key]['nilai_keluar'] = bcadd($buckets[$key]['nilai_keluar'], bcmul((string) $qty, $harga, 2), 2);
                }
            }
        }

        return [
            'labels'       => $labels,
            'masuk'        => array_values(array_column($buckets, 'masuk')),
            'keluar'       => array_values(array_column($buckets, 'keluar')),
            'nilai_masuk'  => array_values(array_map(static fn (array $b): float => (float) $b['nilai_masuk'], $buckets)),
            'nilai_keluar' => array_values(array_map(static fn (array $b): float => (float) $b['nilai_keluar'], $buckets)),
        ];
    }

    /**
     * @param  array{unit?: ?string, from?: ?string, to?: ?string, q?: ?string, active_only?: bool}  $filters
     * @return Builder<PersediaanBarang>
     */
    private function catalog(array $filters): Builder
    {
        $activeOnly = $filters['active_only'] ?? true;
        $query = PersediaanBarang::query()->orderBy('unit_kerja')->orderBy('kode_barang');
        if ($activeOnly) {
            $query->where('is_active', true);
        }
        $unit = $filters['unit'] ?? null;
        if (is_string($unit) && $unit !== '') {
            $query->where('unit_kerja', $unit);
        }
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . $q . '%';
            $query->where(function ($w) use ($like): void {
                $w->where('kode_barang', 'like', $like)->orWhere('nama_barang', 'like', $like);
            });
        }

        return $query;
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, array{masuk: int, keluar: int, koreksi: int, masuk_sebelum: int, keluar_sebelum: int}>
     */
    private function aggregateMovements(array $ids, ?Carbon $from, ?Carbon $to): array
    {
        if ($ids === []) {
            return [];
        }

        $period = [];
        $periodBind = [];
        if ($from !== null) {
            $period[] = 'created_at >= ?';
            $periodBind[] = $from->toDateTimeString();
        }
        if ($to !== null) {
            $period[] = 'created_at <= ?';
            $periodBind[] = $to->toDateTimeString();
        }
        $periodSql = $period === [] ? '1 = 1' : implode(' AND ', $period);

        $sql = "SUM(CASE WHEN tipe = ? AND {$periodSql} THEN qty_masuk ELSE 0 END) as masuk, "
            . "SUM(CASE WHEN tipe = ? AND {$periodSql} THEN qty_keluar ELSE 0 END) as keluar, "
            . "SUM(CASE WHEN tipe = ? AND {$periodSql} THEN (qty_masuk - qty_keluar) ELSE 0 END) as koreksi";
        $bindings = [
            PersediaanStockMovement::TIPE_PEMBELIAN,
            ...$periodBind,
            PersediaanStockMovement::TIPE_KELUAR,
            ...$periodBind,
            PersediaanStockMovement::TIPE_OPNAME,
            ...$periodBind,
        ];

        if ($from !== null) {
            $sql .= ', SUM(CASE WHEN tipe != ? AND created_at < ? THEN qty_masuk ELSE 0 END) as masuk_sebelum'
                . ', SUM(CASE WHEN created_at < ? THEN qty_keluar ELSE 0 END) as keluar_sebelum';
            $bindings[] = PersediaanStockMovement::TIPE_STOK_AWAL;
            $bindings[] = $from->toDateTimeString();
            $bindings[] = $from->toDateTimeString();
        } else {
            $sql .= ', 0 as masuk_sebelum, 0 as keluar_sebelum';
        }

        $result = [];
        foreach (array_chunk($ids, 500) as $chunk) {
            $rows = PersediaanStockMovement::query()
                ->select('barang_id')
                ->selectRaw($sql, $bindings)
                ->whereIn('barang_id', $chunk)
                ->groupBy('barang_id')
                ->get();
            foreach ($rows as $row) {
                $result[(int) $row->barang_id] = [
                    'masuk'          => (int) $row->masuk,
                    'keluar'         => (int) $row->keluar,
                    'koreksi'        => (int) $row->koreksi,
                    'masuk_sebelum'  => (int) $row->masuk_sebelum,
                    'keluar_sebelum' => (int) $row->keluar_sebelum,
                ];
            }
        }

        return $result;
    }

    /**
     * @return array<string, int|string>
     */
    private function emptySummary(): array
    {
        return [
            'total_jenis_barang'        => 0,
            'total_stok_pembuka'        => 0,
            'total_stok_saat_ini'       => 0,
            'jumlah_barang_masuk'       => 0,
            'jumlah_barang_keluar'      => 0,
            'jumlah_koreksi'            => 0,
            'nilai_barang_masuk'        => '0.00',
            'nilai_barang_keluar'       => '0.00',
            'nilai_koreksi_opname'      => '0.00',
            'nilai_persediaan_awal'     => '0.00',
            'nilai_persediaan_saat_ini' => '0.00',
            'barang_tanpa_harga'        => 0,
        ];
    }

    /**
     * @return array{masuk: int, keluar: int, koreksi: int, masuk_sebelum: int, keluar_sebelum: int}
     */
    private function emptyMove(): array
    {
        return ['masuk' => 0, 'keluar' => 0, 'koreksi' => 0, 'masuk_sebelum' => 0, 'keluar_sebelum' => 0];
    }

    /**
     * @param  array<string, int|string>  $summary
     * @param  array<string, int|string>  $row
     */
    private function addRow(array &$summary, array $row): void
    {
        $summary['total_stok_pembuka'] += $row['stok_pembuka'];
        $summary['total_stok_saat_ini'] += $row['stok_saat_ini'];
        $summary['jumlah_barang_masuk'] += $row['masuk'];
        $summary['jumlah_barang_keluar'] += $row['keluar'];
        $summary['jumlah_koreksi'] += $row['koreksi'];
        $summary['nilai_barang_masuk'] = bcadd((string) $summary['nilai_barang_masuk'], (string) $row['nilai_masuk'], 2);
        $summary['nilai_barang_keluar'] = bcadd((string) $summary['nilai_barang_keluar'], (string) $row['nilai_keluar'], 2);
        $summary['nilai_koreksi_opname'] = bcadd((string) $summary['nilai_koreksi_opname'], (string) $row['nilai_koreksi'], 2);
        $summary['nilai_persediaan_awal'] = bcadd((string) $summary['nilai_persediaan_awal'], (string) $row['nilai_pembuka'], 2);
        $summary['nilai_persediaan_saat_ini'] = bcadd((string) $summary['nilai_persediaan_saat_ini'], (string) $row['nilai_saat_ini'], 2);
    }

    private function dayStart(?string $date): ?Carbon
    {
        if ($date === null || $date === '') {
            return null;
        }

        return Carbon::parse($date)->startOfDay();
    }

    private function dayEnd(?string $date): ?Carbon
    {
        if ($date === null || $date === '') {
            return null;
        }

        return Carbon::parse($date)->endOfDay();
    }

    private function decimal(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '0.00';
        }

        if (is_int($value)) {
            return bcadd((string) $value, '0', 2);
        }

        if (is_float($value)) {
            return bcadd(sprintf('%.2F', $value), '0', 2);
        }

        return bcadd((string) $value, '0', 2);
    }
}
