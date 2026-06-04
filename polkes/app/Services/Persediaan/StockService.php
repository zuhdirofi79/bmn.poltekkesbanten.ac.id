<?php

declare(strict_types=1);

namespace App\Services\Persediaan;

use App\Models\Persediaan\PersediaanBarang;
use App\Models\Persediaan\PersediaanStockMovement;
use Illuminate\Support\Facades\DB;

/**
 * Sumber kebenaran perhitungan stok persediaan.
 *
 * Stok Akhir = stok_awal + SUM(qty_masuk) - SUM(qty_keluar)
 * dihitung dari tabel persediaan_stock_movements (kecuali stok_awal yang
 * juga sudah dicatat sebagai movement tipe 'stok_awal' saat seed/master).
 */
class StockService
{
    public function recordMovement(array $data): PersediaanStockMovement
    {
        return PersediaanStockMovement::create([
            'barang_id'  => $data['barang_id'],
            'unit_kerja' => $data['unit_kerja'],
            'tipe'       => $data['tipe'],
            'ref_table'  => $data['ref_table'] ?? null,
            'ref_id'     => $data['ref_id'] ?? null,
            'qty_masuk'  => (int) ($data['qty_masuk'] ?? 0),
            'qty_keluar' => (int) ($data['qty_keluar'] ?? 0),
            'keterangan' => $data['keterangan'] ?? null,
            'created_by' => $data['created_by'] ?? null,
        ]);
    }

    /** Total qty masuk dari movement (semua tipe, termasuk stok_awal). */
    public function totalMasuk(int $barangId): int
    {
        return (int) PersediaanStockMovement::where('barang_id', $barangId)->sum('qty_masuk');
    }

    /** Total qty keluar dari movement. */
    public function totalKeluar(int $barangId): int
    {
        return (int) PersediaanStockMovement::where('barang_id', $barangId)->sum('qty_keluar');
    }

    /**
     * Barang masuk DI LUAR stok awal (untuk kolom "Barang Masuk" pada tabel stok).
     */
    public function barangMasuk(int $barangId, ?string $from = null, ?string $to = null): int
    {
        $q = PersediaanStockMovement::where('barang_id', $barangId)
            ->where('tipe', '!=', PersediaanStockMovement::TIPE_STOK_AWAL);

        $this->applyDateRange($q, $from, $to);

        return (int) $q->sum('qty_masuk');
    }

    public function barangKeluar(int $barangId, ?string $from = null, ?string $to = null): int
    {
        $q = PersediaanStockMovement::where('barang_id', $barangId);
        $this->applyDateRange($q, $from, $to);

        return (int) $q->sum('qty_keluar');
    }

    /**
     * Stok akhir saat ini = stok_awal + (semua masuk non-awal) - (semua keluar).
     */
    public function stokAkhir(PersediaanBarang $barang): int
    {
        $masuk = $this->barangMasuk((int) $barang->id);
        $keluar = $this->barangKeluar((int) $barang->id);

        return (int) $barang->stok_awal + $masuk - $keluar;
    }

    /**
     * Agregat stok untuk banyak barang sekaligus (hindari N+1 di tabel stok).
     *
     * @param  \Illuminate\Support\Collection<int,PersediaanBarang>  $barangs
     * @return array<int,array{masuk:int,keluar:int,akhir:int}>
     */
    public function ringkasanBanyak($barangs, ?string $from = null, ?string $to = null): array
    {
        $ids = $barangs->pluck('id')->all();
        if (empty($ids)) {
            return [];
        }

        $masukQ = PersediaanStockMovement::query()
            ->select('barang_id', DB::raw('SUM(qty_masuk) as total'))
            ->whereIn('barang_id', $ids)
            ->where('tipe', '!=', PersediaanStockMovement::TIPE_STOK_AWAL);
        $this->applyDateRange($masukQ, $from, $to);
        $masuk = $masukQ->groupBy('barang_id')->pluck('total', 'barang_id');

        $keluarQ = PersediaanStockMovement::query()
            ->select('barang_id', DB::raw('SUM(qty_keluar) as total'))
            ->whereIn('barang_id', $ids);
        $this->applyDateRange($keluarQ, $from, $to);
        $keluar = $keluarQ->groupBy('barang_id')->pluck('total', 'barang_id');

        $result = [];
        foreach ($barangs as $b) {
            $m = (int) ($masuk[$b->id] ?? 0);
            $k = (int) ($keluar[$b->id] ?? 0);
            $result[(int) $b->id] = [
                'masuk'  => $m,
                'keluar' => $k,
                'akhir'  => (int) $b->stok_awal + $m - $k,
            ];
        }

        return $result;
    }

    private function applyDateRange($query, ?string $from, ?string $to): void
    {
        if ($from !== null && $from !== '') {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to !== null && $to !== '') {
            $query->whereDate('created_at', '<=', $to);
        }
    }
}
