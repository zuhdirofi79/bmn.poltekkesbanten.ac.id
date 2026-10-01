<?php

declare(strict_types=1);

namespace App\Exports\Persediaan;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PembelianTemplateExport implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            new class implements FromArray, WithHeadings, WithTitle, ShouldAutoSize, WithStyles {
                public function title(): string
                {
                    return 'Data Pembelian';
                }

                public function headings(): array
                {
                    return [
                        'no_faktur',
                        'tanggal',
                        'penyedia',
                        'unit_kerja',
                        'kode_barang',
                        'nama_barang',
                        'satuan',
                        'harga_satuan',
                        'jumlah',
                    ];
                }

                public function array(): array
                {
                    return [];
                }

                public function styles(Worksheet $sheet)
                {
                    return [1 => ['font' => ['bold' => true]]];
                }
            },
            new class implements FromArray, WithTitle, ShouldAutoSize {
                public function title(): string
                {
                    return 'Petunjuk';
                }

                public function array(): array
                {
                    return [
                        ['Petunjuk pengisian template pembelian persediaan'],
                        ['Sheet yang diimpor adalah "Data Pembelian". Jangan menaruh data transaksi di sheet ini.'],
                        ['Satu baris pada Data Pembelian = satu item.'],
                        ['Beberapa baris dengan no_faktur yang sama = satu transaksi, bukan banyak transaksi.'],
                        ['Tanggal memakai format Indonesia DD/MM/YYYY. Contoh: 06/08/2026 berarti 6 Agustus 2026.'],
                        ['Tanggal Excel (date serial) juga diterima dan dinormalisasi menjadi tahun-bulan-hari.'],
                        ['kode_barang boleh berasal dari Master Barang. Jika kode dan unit kerja cocok, barang terhubung ke master dan stok master bertambah satu kali.'],
                        ['Nama barang dapat diisi manual. Jika kode tidak ada di master, item disimpan sebagai manual dan stok master tidak berubah.'],
                        ['Sistem tidak membuat master barang baru dari file Excel.'],
                        ['harga_satuan harus berupa angka, minimal 0. jumlah harus berupa angka bulat lebih besar dari 0.'],
                        ['Subtotal dan total transaksi dihitung aplikasi: harga_satuan x jumlah. Jangan dijadikan sumber data.'],
                        ['no_faktur, tanggal, penyedia, unit_kerja, nama_barang, dan satuan wajib diisi. kode_barang boleh kosong untuk item manual.'],
                        ['Unit kerja yang diterima: Keperawatan, Kebidanan, TLM, Direktorat, PPPK, Pimpinan.'],
                        ['No. Faktur yang sudah ada di database ditolak dan tidak membuat transaksi kedua.'],
                        ['Contoh di sheet ini tidak ikut ter-import.'],
                        ['Contoh pengelompokan: tiga baris INV-001 menjadi 1 transaksi dengan 3 detail. Dua baris INV-002 menjadi 1 transaksi lain.'],
                    ];
                }
            },
        ];
    }
}
