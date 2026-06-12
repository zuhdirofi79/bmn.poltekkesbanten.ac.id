<?php

declare(strict_types=1);

namespace App\Exports\Persediaan;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class MasterBarangTemplateExport implements FromArray, WithHeadings, ShouldAutoSize
{
    /**
     * @return list<list<string|int>>
     */
    public function array(): array
    {
        return [
            ['ATK001', 'Pulpen', 'Keperawatan', 'Buah', 150, 2500, 'aktif'],
            ['ATK002', 'Kertas A4', 'Keperawatan', 'Rim', 50, 55000, 'aktif'],
            ['ATK003', 'Stapler', 'Keperawatan', 'Buah', 20, 35000, 'aktif'],
        ];
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return [
            'kode_barang',
            'nama_barang',
            'unit_kerja',
            'satuan',
            'stok_awal',
            'harga_satuan',
            'status',
        ];
    }
}
