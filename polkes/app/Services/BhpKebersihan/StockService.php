<?php

declare(strict_types=1);

namespace App\Services\BhpKebersihan;

use App\Models\BhpKebersihan\BhpKebersihanBarang;
use App\Models\BhpKebersihan\BhpKebersihanStockMovement;
use Illuminate\Support\Facades\DB;

/**
 * Perhitungan stok BHP Kebersihan dari stock_movements.
 *
 * Stok akhir = stok_awal + barang masuk - barang keluar (+ koreksi opname).
 */
class StockService
{
    public function stokSaatIni(BhpKebersihanBarang $barang): int
    {
        $last = BhpKebersihanStockMovement::query()
            ->where('barang_id', $barang->id)
            ->orderByDesc('id')
            ->first();

        if ($last !== null) {
            return (int) $last->stok_sesudah;
        }

        return (int) $barang->stok_awal;
    }

    public function stokSaatIniById(int $barangId): int
    {
        $barang = BhpKebersihanBarang::find($barangId);

        return $barang ? $this->stokSaatIni($barang) : 0;
    }

    public function barangMasuk(int $barangId, ?string $from = null, ?string $to = null): int
    {
        $q = BhpKebersihanStockMovement::query()
            ->where('barang_id', $barangId)
            ->where('tipe', BhpKebersihanStockMovement::TIPE_MASUK);

        $this->applyDateRange($q, $from, $to);

        return (int) $q->sum('jumlah');
    }

    public function barangKeluar(int $barangId, ?string $from = null, ?string $to = null): int
    {
        $q = BhpKebersihanStockMovement::query()
            ->where('barang_id', $barangId)
            ->where('tipe', BhpKebersihanStockMovement::TIPE_KELUAR);

        $this->applyDateRange($q, $from, $to);

        return (int) $q->sum('jumlah');
    }

    public function stokAwalPeriode(BhpKebersihanBarang $barang, ?string $from = null): int
    {
        if ($from === null || $from === '') {
            return (int) $barang->stok_awal;
        }

        $lastBefore = BhpKebersihanStockMovement::query()
            ->where('barang_id', $barang->id)
            ->whereDate('created_at', '<', $from)
            ->orderByDesc('id')
            ->first();

        if ($lastBefore !== null) {
            return (int) $lastBefore->stok_sesudah;
        }

        return (int) $barang->stok_awal;
    }

    /**
     * @param  \Illuminate\Support\Collection<int,BhpKebersihanBarang>  $barangs
     * @return array<int,array{awal:int,masuk:int,keluar:int,akhir:int}>
     */
    public function ringkasanBanyak($barangs, ?string $from = null, ?string $to = null): array
    {
        $ids = $barangs->pluck('id')->all();
        if (empty($ids)) {
            return [];
        }

        $masukQ = BhpKebersihanStockMovement::query()
            ->select('barang_id', DB::raw('SUM(jumlah) as total'))
            ->whereIn('barang_id', $ids)
            ->where('tipe', BhpKebersihanStockMovement::TIPE_MASUK);
        $this->applyDateRange($masukQ, $from, $to);
        $masuk = $masukQ->groupBy('barang_id')->pluck('total', 'barang_id');

        $keluarQ = BhpKebersihanStockMovement::query()
            ->select('barang_id', DB::raw('SUM(jumlah) as total'))
            ->whereIn('barang_id', $ids)
            ->where('tipe', BhpKebersihanStockMovement::TIPE_KELUAR);
        $this->applyDateRange($keluarQ, $from, $to);
        $keluar = $keluarQ->groupBy('barang_id')->pluck('total', 'barang_id');

        $result = [];
        foreach ($barangs as $b) {
            $awal = $this->stokAwalPeriode($b, $from);
            $m = (int) ($masuk[$b->id] ?? 0);
            $k = (int) ($keluar[$b->id] ?? 0);
            $result[(int) $b->id] = [
                'awal'   => $awal,
                'masuk'  => $m,
                'keluar' => $k,
                'akhir'  => $awal + $m - $k,
            ];
        }

        return $result;
    }

    public function recordMasuk(
        BhpKebersihanBarang $barang,
        int $jumlah,
        string $sumber,
        ?int $refId = null,
        ?string $catatan = null,
        ?int $createdBy = null,
    ): BhpKebersihanStockMovement {
        $sebelum = $this->stokSaatIni($barang);
        $sesudah = $sebelum + $jumlah;

        return BhpKebersihanStockMovement::create([
            'barang_id'    => $barang->id,
            'tipe'         => BhpKebersihanStockMovement::TIPE_MASUK,
            'sumber'       => $sumber,
            'ref_id'       => $refId,
            'jumlah'       => $jumlah,
            'stok_sebelum' => $sebelum,
            'stok_sesudah' => $sesudah,
            'catatan'      => $catatan,
            'created_by'   => $createdBy,
        ]);
    }

    public function recordKeluar(
        BhpKebersihanBarang $barang,
        int $jumlah,
        string $sumber,
        ?int $refId = null,
        ?string $catatan = null,
        ?int $createdBy = null,
    ): BhpKebersihanStockMovement {
        $exists = BhpKebersihanStockMovement::query()
            ->where('barang_id', $barang->id)
            ->where('tipe', BhpKebersihanStockMovement::TIPE_KELUAR)
            ->where('sumber', $sumber)
            ->where('ref_id', $refId)
            ->exists();

        if ($exists) {
            return BhpKebersihanStockMovement::query()
                ->where('barang_id', $barang->id)
                ->where('tipe', BhpKebersihanStockMovement::TIPE_KELUAR)
                ->where('sumber', $sumber)
                ->where('ref_id', $refId)
                ->first();
        }

        $sebelum = $this->stokSaatIni($barang);
        $sesudah = max(0, $sebelum - $jumlah);

        return BhpKebersihanStockMovement::create([
            'barang_id'    => $barang->id,
            'tipe'         => BhpKebersihanStockMovement::TIPE_KELUAR,
            'sumber'       => $sumber,
            'ref_id'       => $refId,
            'jumlah'       => $jumlah,
            'stok_sebelum' => $sebelum,
            'stok_sesudah' => $sesudah,
            'catatan'      => $catatan,
            'created_by'   => $createdBy,
        ]);
    }

    public function recordKoreksiOpname(
        BhpKebersihanBarang $barang,
        int $stokFisik,
        int $opnameId,
        ?string $catatan = null,
        ?int $createdBy = null,
    ): ?BhpKebersihanStockMovement {
        $sebelum = $this->stokSaatIni($barang);
        $selisih = $stokFisik - $sebelum;

        if ($selisih === 0) {
            return null;
        }

        return BhpKebersihanStockMovement::create([
            'barang_id'    => $barang->id,
            'tipe'         => BhpKebersihanStockMovement::TIPE_KOREKSI,
            'sumber'       => BhpKebersihanStockMovement::SUMBER_OPNAME,
            'ref_id'       => $opnameId,
            'jumlah'       => abs($selisih),
            'stok_sebelum' => $sebelum,
            'stok_sesudah' => $stokFisik,
            'catatan'      => $catatan ?? ('Koreksi opname selisih ' . $selisih),
            'created_by'   => $createdBy,
        ]);
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
