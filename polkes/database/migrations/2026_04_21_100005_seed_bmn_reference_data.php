<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class SeedBmnReferenceData extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $now = now();

        if (DB::table('kategori_aset')->count() === 0) {
            $kategori = ['KIB A', 'KIB B', 'KIB C', 'KIB D', 'KIB E'];
            foreach ($kategori as $nama) {
                DB::table('kategori_aset')->insert([
                    'nama_kategori' => $nama,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        if (DB::table('lokasi_aset')->count() === 0) {
            DB::table('lokasi_aset')->insert([
                'nama_lokasi' => 'Belum ditentukan',
                'deskripsi' => 'Lokasi default awal modul BMN',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Tidak menghapus data referensi otomatis agar rollback modul BMN tidak bentrok dengan FK aset.
    }
}
