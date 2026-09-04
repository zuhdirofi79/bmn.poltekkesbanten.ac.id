<?php

declare(strict_types=1);

namespace App\Services\BhpKebersihan;

use App\Models\BhpKebersihan\BhpKebersihanBarang;
use App\Models\BhpKebersihan\BhpKebersihanStockMovement;
use Illuminate\Support\Facades\DB;

/**
 * Satu sumber kebenaran stok BHP Kebersihan.
 *
 * STOK AKTUAL = stok_awal + barang masuk (bukan saldo awal) - barang keluar +/- koreksi opname
 *
 * stok_awal di master adalah saldo awal. Movement "Stok awal master barang"
 * (jika ada di data lama) TIDAK dijumlahkan lagi.
 */
class StockService
{
    public function stokSaatIni(BhpKebersihanBarang $barang): int
    {
        return $this->hitungStok($barang);
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
        $this->excludeOpeningBalance($q);
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

        return $this->hitungStok($barang, null, $from, true);
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
        $this->excludeOpeningBalance($masukQ);
        $this->applyDateRange($masukQ, $from, $to);
        $masuk = $masukQ->groupBy('barang_id')->pluck('total', 'barang_id');

        $keluarQ = BhpKebersihanStockMovement::query()
            ->select('barang_id', DB::raw('SUM(jumlah) as total'))
            ->whereIn('barang_id', $ids)
            ->where('tipe', BhpKebersihanStockMovement::TIPE_KELUAR);
        $this->applyDateRange($keluarQ, $from, $to);
        $keluar = $keluarQ->groupBy('barang_id')->pluck('total', 'barang_id');

        $koreksiQ = BhpKebersihanStockMovement::query()
            ->select('barang_id', DB::raw('SUM(stok_sesudah - stok_sebelum) as total'))
            ->whereIn('barang_id', $ids)
            ->where('tipe', BhpKebersihanStockMovement::TIPE_KOREKSI);
        $this->applyDateRange($koreksiQ, $from, $to);
        $koreksi = $koreksiQ->groupBy('barang_id')->pluck('total', 'barang_id');

        $result = [];
        foreach ($barangs as $b) {
            $awal = $this->stokAwalPeriode($b, $from);
            $m = (int) ($masuk[$b->id] ?? 0);
            $k = (int) ($keluar[$b->id] ?? 0);
            $c = (int) ($koreksi[$b->id] ?? 0);
            $result[(int) $b->id] = [
                'awal'   => $awal,
                'masuk'  => $m,
                'keluar' => $k,
                'akhir'  => $awal + $m - $k + $c,
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
        if ($jumlah < 1) {
            throw new \InvalidArgumentException('Jumlah masuk harus lebih dari 0.');
        }

        return DB::transaction(function () use ($barang, $jumlah, $sumber, $refId, $catatan, $createdBy) {
            $locked = BhpKebersihanBarang::query()->whereKey($barang->id)->lockForUpdate()->firstOrFail();
            $sebelum = $this->hitungStok($locked);
            $sesudah = $sebelum + $jumlah;

            return BhpKebersihanStockMovement::create([
                'barang_id'    => $locked->id,
                'tipe'         => BhpKebersihanStockMovement::TIPE_MASUK,
                'sumber'       => $sumber,
                'ref_id'       => $refId,
                'jumlah'       => $jumlah,
                'stok_sebelum' => $sebelum,
                'stok_sesudah' => $sesudah,
                'catatan'      => $catatan,
                'created_by'   => $createdBy,
            ]);
        });
    }

    public function recordKeluar(
        BhpKebersihanBarang $barang,
        int $jumlah,
        string $sumber,
        ?int $refId = null,
        ?string $catatan = null,
        ?int $createdBy = null,
    ): BhpKebersihanStockMovement {
        if ($jumlah < 1) {
            throw new \InvalidArgumentException('Jumlah keluar harus lebih dari 0.');
        }

        return DB::transaction(function () use ($barang, $jumlah, $sumber, $refId, $catatan, $createdBy) {
            $exists = BhpKebersihanStockMovement::query()
                ->where('barang_id', $barang->id)
                ->where('tipe', BhpKebersihanStockMovement::TIPE_KELUAR)
                ->where('sumber', $sumber)
                ->where('ref_id', $refId)
                ->lockForUpdate()
                ->first();

            if ($exists !== null) {
                return $exists;
            }

            $locked = BhpKebersihanBarang::query()->whereKey($barang->id)->lockForUpdate()->firstOrFail();
            $sebelum = $this->hitungStok($locked);

            if ($jumlah > $sebelum) {
                throw new \InvalidArgumentException('Jumlah pengambilan melebihi stok tersedia.');
            }

            $sesudah = $sebelum - $jumlah;

            return BhpKebersihanStockMovement::create([
                'barang_id'    => $locked->id,
                'tipe'         => BhpKebersihanStockMovement::TIPE_KELUAR,
                'sumber'       => $sumber,
                'ref_id'       => $refId,
                'jumlah'       => $jumlah,
                'stok_sebelum' => $sebelum,
                'stok_sesudah' => $sesudah,
                'catatan'      => $catatan,
                'created_by'   => $createdBy,
            ]);
        });
    }

    public function recordKoreksiOpname(
        BhpKebersihanBarang $barang,
        int $stokFisik,
        int $opnameId,
        ?string $catatan = null,
        ?int $createdBy = null,
    ): ?BhpKebersihanStockMovement {
        return DB::transaction(function () use ($barang, $stokFisik, $opnameId, $catatan, $createdBy) {
            $existing = BhpKebersihanStockMovement::query()
                ->where('barang_id', $barang->id)
                ->where('tipe', BhpKebersihanStockMovement::TIPE_KOREKSI)
                ->where('sumber', BhpKebersihanStockMovement::SUMBER_OPNAME)
                ->where('ref_id', $opnameId)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $locked = BhpKebersihanBarang::query()->whereKey($barang->id)->lockForUpdate()->firstOrFail();
            $sebelum = $this->hitungStok($locked);
            $selisih = $stokFisik - $sebelum;

            if ($selisih === 0) {
                return null;
            }

            return BhpKebersihanStockMovement::create([
                'barang_id'    => $locked->id,
                'tipe'         => BhpKebersihanStockMovement::TIPE_KOREKSI,
                'sumber'       => BhpKebersihanStockMovement::SUMBER_OPNAME,
                'ref_id'       => $opnameId,
                'jumlah'       => abs($selisih),
                'stok_sebelum' => $sebelum,
                'stok_sesudah' => $stokFisik,
                'catatan'      => $catatan ?? ('Koreksi opname selisih ' . $selisih),
                'created_by'   => $createdBy,
            ]);
        });
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\BhpKebersihan\BhpKebersihanStockMovement>  $query
     */
    private function excludeOpeningBalance($query): void
    {
        $query->where(function ($q) {
            $q->where('sumber', '!=', BhpKebersihanStockMovement::SUMBER_MANUAL)
                ->orWhereNull('catatan')
                ->orWhere('catatan', 'not like', BhpKebersihanStockMovement::CATATAN_STOK_AWAL . '%');
        });
    }

    private function hitungStok(
        BhpKebersihanBarang $barang,
        ?string $from = null,
        ?string $to = null,
        bool $toExclusive = false,
    ): int {
        $masuk = $this->sumJumlah($barang->id, BhpKebersihanStockMovement::TIPE_MASUK, $from, $to, $toExclusive, true);
        $keluar = $this->sumJumlah($barang->id, BhpKebersihanStockMovement::TIPE_KELUAR, $from, $to, $toExclusive, false);
        $koreksi = $this->netKoreksi($barang->id, $from, $to, $toExclusive);

        return (int) $barang->stok_awal + $masuk - $keluar + $koreksi;
    }

    private function sumJumlah(
        int $barangId,
        string $tipe,
        ?string $from,
        ?string $to,
        bool $toExclusive,
        bool $excludeOpening,
    ): int {
        $q = BhpKebersihanStockMovement::query()
            ->where('barang_id', $barangId)
            ->where('tipe', $tipe);

        if ($excludeOpening) {
            $this->excludeOpeningBalance($q);
        }

        $this->applyDateRange($q, $from, $to, $toExclusive);

        return (int) $q->sum('jumlah');
    }

    private function netKoreksi(int $barangId, ?string $from, ?string $to, bool $toExclusive = false): int
    {
        $q = BhpKebersihanStockMovement::query()
            ->where('barang_id', $barangId)
            ->where('tipe', BhpKebersihanStockMovement::TIPE_KOREKSI);

        $this->applyDateRange($q, $from, $to, $toExclusive);

        return (int) $q->sum(DB::raw('stok_sesudah - stok_sebelum'));
    }

    private function applyDateRange($query, ?string $from, ?string $to, bool $toExclusive = false): void
    {
        if ($from !== null && $from !== '') {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to !== null && $to !== '') {
            if ($toExclusive) {
                $query->whereDate('created_at', '<', $to);
            } else {
                $query->whereDate('created_at', '<=', $to);
            }
        }
    }
}
