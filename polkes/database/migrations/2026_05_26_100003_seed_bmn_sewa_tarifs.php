<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SeedBmnSewaTarifs extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bmn_sewa_tarifs') || DB::table('bmn_sewa_tarifs')->exists()) {
            return;
        }

        $now = now();
        $sort = 0;
        $rows = [];

        $add = function (string $kategori, string $nama, string $satuan, float $umum, ?float $pegawai = null) use (&$rows, &$sort, $now): void {
            $rows[] = [
                'kategori' => $kategori,
                'nama_layanan' => $nama,
                'satuan' => $satuan,
                'tarif_umum' => $umum,
                'tarif_pegawai' => $pegawai ?? round($umum * 0.5, 2),
                'is_active' => 1,
                'sort_order' => ++$sort,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        };

        $add('Sewa Kantin / Dapur', 'Sewa Ruang Kantin Harian', 'per hari', 500000);
        $add('Sewa Tempat Usaha dan Laboratorium', 'Sewa Lab Praktikum', 'per hari', 750000);
        $add('Sewa Kendaraan', 'Sewa Mobil Operasional', 'per hari', 600000);
        $add('Sewa Kendaraan', 'Sewa Bus Medium', 'per hari', 1500000);
        $add('Sewa Asrama / Guest House', 'Sewa Kamar Asrama', 'per malam', 150000);
        $add('Tes Kesehatan Mahasiswa', 'Paket Tes Kesehatan Mahasiswa', 'per orang', 350000);
        $add('Uang Makan Mahasiswa', 'Uang Makan Mahasiswa Praktik', 'per hari', 75000);
        $add('Toga Wisuda', 'Sewa Toga Wisuda', 'per set', 250000);
        $add('Layanan Perpustakaan', 'Sewa Ruang Baca', 'per jam', 50000);
        $add('Institutional Fee', 'Institutional Fee Penelitian', 'per paket', 2000000);
        $add('Tarif Publikasi dan Pemesanan Jurnal', 'Pemesanan Jurnal Internasional', 'per artikel', 500000);
        $add('Tarif Kaji Etik Penelitian', 'Kaji Etik Penelitian', 'per protokol', 300000);
        $add('Tarif Ijin Penelitian / Pengmas', 'Ijin Penelitian / Pengmas', 'per kegiatan', 400000);
        $add('Lain-lain', 'Layanan BLU Lainnya', 'per unit', 100000);

        DB::table('bmn_sewa_tarifs')->insert($rows);
    }

    public function down(): void
    {
        if (Schema::hasTable('bmn_sewa_tarifs')) {
            DB::table('bmn_sewa_tarifs')->truncate();
        }
    }
}
