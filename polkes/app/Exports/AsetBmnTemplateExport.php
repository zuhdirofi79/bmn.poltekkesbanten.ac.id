<?php

declare(strict_types=1);

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;

class AsetBmnTemplateExport implements FromArray
{
    /**
     * @return list<list<string|int|float>>
     */
    public function array(): array
    {
        return [
            ['kode_barang', 'nup', 'nama_barang', 'merk', 'kategori_id', 'nilai_perolehan', 'tanggal_perolehan', 'kondisi', 'pengguna', 'satker_id', 'lokasi', 'foto_url'],
            // tanggal_perolehan: dd-mm-yyyy | dd/mm/yyyy | yyyy-mm-dd | tanggal asli Excel
            ['3.03.2026.001', '001', 'Laptop HP', 'HP', 1, 10000000, '01/01/2026', 'baik', 'Lab IT', 1, 'Ruang Lab IT', 'https://contoh.com/gambar.jpg'],
        ];
    }
}
