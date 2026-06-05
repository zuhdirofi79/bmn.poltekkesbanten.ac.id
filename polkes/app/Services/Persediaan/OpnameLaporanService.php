<?php

declare(strict_types=1);

namespace App\Services\Persediaan;

use App\Models\Persediaan\PersediaanBarang;
use App\Models\Persediaan\PersediaanStockOpname;
use App\User;

/**
 * Menyusun data laporan "Daftar Transaksi Persediaan" untuk stock opname.
 */
class OpnameLaporanService
{
    public const KODE_UAPKPB = '024.12.900.637615.003.KD';
    public const JENIS_TRANSAKSI = 'P01 - Hasil Opname Fisik';

    /**
     * @return array{
     *     nama_admin: string,
     *     tanggal_opname: \Illuminate\Support\Carbon|null,
     *     tahun_anggaran: int,
     *     rows: list<array{kode: string, uraian: string, kuantitas: int, rupiah: float}>,
     *     total_kuantitas: int,
     *     total_rupiah: float
     * }
     */
    public function build(PersediaanStockOpname $opname): array
    {
        $opname->loadMissing('items');

        $hargaMap = PersediaanBarang::query()
            ->whereIn('id', $opname->items->pluck('barang_id'))
            ->pluck('harga_satuan', 'id');

        $rows = [];
        $totalKuantitas = 0;
        $totalRupiah = 0.0;

        foreach ($opname->items as $item) {
            if ($item->selisih === 0) {
                continue;
            }

            $harga = (float) ($hargaMap[$item->barang_id] ?? 0);
            $rupiah = $item->selisih * $harga;
            $totalKuantitas += $item->selisih;
            $totalRupiah += $rupiah;

            $rows[] = [
                'kode'      => $item->kode_barang,
                'uraian'    => $item->nama_barang,
                'kuantitas' => $item->selisih,
                'rupiah'    => $rupiah,
            ];
        }

        $nama = User::query()->whereKey($opname->created_by)->value('name') ?: 'Admin';

        return [
            'nama_admin'       => mb_strtoupper($nama, 'UTF-8'),
            'tanggal_opname'   => $opname->tanggal_opname,
            'tahun_anggaran'   => (int) optional($opname->tanggal_opname)->format('Y'),
            'rows'             => $rows,
            'total_kuantitas'  => $totalKuantitas,
            'total_rupiah'     => $totalRupiah,
        ];
    }

    public function formatRupiah(float|int $value): string
    {
        $num = (float) $value;

        if ($num < 0) {
            return '-' . number_format(abs($num), 0, ',', '.');
        }

        return number_format($num, 0, ',', '.');
    }
}
