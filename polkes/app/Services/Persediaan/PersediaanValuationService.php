<?php

declare(strict_types=1);

namespace App\Services\Persediaan;

use App\Models\Persediaan\PersediaanBarang;
use Illuminate\Database\Eloquent\Builder;

/**
 * Nilai persediaan Master Barang.
 *
 * SUM(COALESCE(stok_awal, 0) * COALESCE(harga_satuan, 0))
 *
 * Kartu dashboard dan kolom Stok Awal × Harga pada export Master Barang
 * memakai definisi ini. Stok akhir dari movement tidak masuk ke total ini.
 */
class PersediaanValuationService
{
    /**
     * Katalog yang sama dengan hitungan 285 barang aktif pada dashboard
     * dan berkas export Master Barang saat ini (semua baris berstatus Aktif).
     *
     * @return Builder<PersediaanBarang>
     */
    public function activeCatalogQuery(): Builder
    {
        return PersediaanBarang::query()->where('is_active', true);
    }

    /**
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
