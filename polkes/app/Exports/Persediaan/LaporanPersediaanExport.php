<?php

declare(strict_types=1);

namespace App\Exports\Persediaan;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class LaporanPersediaanExport implements FromArray, WithHeadings, ShouldAutoSize
{
    /**
     * @param  list<array<string, int|string>>  $rows
     * @param  array<string, int|string>  $summary
     */
    public function __construct(
        private array $rows,
        private array $summary,
    ) {
    }

    public function headings(): array
    {
        return [
            'Kode',
            'Nama Barang',
            'Unit',
            'Satuan',
            'Stok Pembuka',
            'Barang Masuk',
            'Barang Keluar',
            'Koreksi Opname',
            'Stok Saat Ini',
            'Harga Satuan',
            'Nilai Pembuka',
            'Nilai Masuk',
            'Nilai Keluar',
            'Nilai Koreksi',
            'Nilai Saat Ini',
        ];
    }

    public function array(): array
    {
        $data = [];
        foreach ($this->rows as $row) {
            $data[] = [
                $row['kode'],
                $row['nama'],
                $row['unit'],
                $row['satuan'],
                $row['stok_pembuka'],
                $row['masuk'],
                $row['keluar'],
                $row['koreksi'],
                $row['stok_saat_ini'],
                (float) $row['harga'],
                (float) $row['nilai_pembuka'],
                (float) $row['nilai_masuk'],
                (float) $row['nilai_keluar'],
                (float) $row['nilai_koreksi'],
                (float) $row['nilai_saat_ini'],
            ];
        }

        $data[] = [
            '',
            'TOTAL',
            '',
            '',
            $this->summary['total_stok_pembuka'],
            $this->summary['jumlah_barang_masuk'],
            $this->summary['jumlah_barang_keluar'],
            $this->summary['jumlah_koreksi'],
            $this->summary['total_stok_saat_ini'],
            '',
            (float) $this->summary['nilai_persediaan_awal'],
            (float) $this->summary['nilai_barang_masuk'],
            (float) $this->summary['nilai_barang_keluar'],
            (float) $this->summary['nilai_koreksi_opname'],
            (float) $this->summary['nilai_persediaan_saat_ini'],
        ];

        return $data;
    }
}
