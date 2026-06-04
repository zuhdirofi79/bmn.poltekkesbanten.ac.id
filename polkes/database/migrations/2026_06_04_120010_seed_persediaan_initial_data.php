<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seed awal modul Persediaan:
 *  - Admin Persediaan: user_id 3235 (Pak Hendri) & 3243 (Bu Nirmala) sebagai superadmin_persediaan.
 *  - Contoh master barang ATK per unit kerja agar form & tabel stok bisa langsung diuji.
 *
 * Idempotent: aman dijalankan ulang (cek keberadaan baris sebelum insert).
 */
class SeedPersediaanInitialData extends Migration
{
    public function up()
    {
        $now = now();

        $admins = [
            ['user_id' => 3235, 'role' => 'superadmin_persediaan'],
            ['user_id' => 3243, 'role' => 'superadmin_persediaan'],
        ];

        foreach ($admins as $admin) {
            $exists = DB::table('persediaan_admins')
                ->where('user_id', $admin['user_id'])
                ->exists();

            if (! $exists) {
                DB::table('persediaan_admins')->insert([
                    'user_id'    => $admin['user_id'],
                    'satker'     => null,
                    'role'       => $admin['role'],
                    'is_active'  => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        $units = ['Keperawatan', 'Kebidanan', 'TLM', 'Direktorat'];
        $sampleBarang = [
            ['kode' => 'ATK001', 'nama' => 'Pulpen',      'satuan' => 'Buah', 'awal' => 150, 'min' => 30, 'harga' => 2500],
            ['kode' => 'ATK002', 'nama' => 'Kertas A4',   'satuan' => 'Rim',  'awal' => 50,  'min' => 10, 'harga' => 55000],
            ['kode' => 'ATK003', 'nama' => 'Stapler',     'satuan' => 'Buah', 'awal' => 20,  'min' => 5,  'harga' => 35000],
            ['kode' => 'ATK004', 'nama' => 'Map Plastik', 'satuan' => 'Buah', 'awal' => 35,  'min' => 10, 'harga' => 5000],
        ];

        foreach ($units as $unit) {
            foreach ($sampleBarang as $barang) {
                $exists = DB::table('persediaan_barang')
                    ->where('kode_barang', $barang['kode'])
                    ->where('unit_kerja', $unit)
                    ->exists();

                if ($exists) {
                    continue;
                }

                $barangId = DB::table('persediaan_barang')->insertGetId([
                    'kode_barang'  => $barang['kode'],
                    'nama_barang'  => $barang['nama'],
                    'satuan'       => $barang['satuan'],
                    'unit_kerja'   => $unit,
                    'stok_awal'    => $barang['awal'],
                    'stok_minimum' => $barang['min'],
                    'harga_satuan' => $barang['harga'],
                    'is_active'    => true,
                    'created_at'   => $now,
                    'updated_at'   => $now,
                ]);

                DB::table('persediaan_stock_movements')->insert([
                    'barang_id'  => $barangId,
                    'unit_kerja' => $unit,
                    'tipe'       => 'stok_awal',
                    'ref_table'  => 'persediaan_barang',
                    'ref_id'     => $barangId,
                    'qty_masuk'  => $barang['awal'],
                    'qty_keluar' => 0,
                    'keterangan' => 'Stok awal master barang',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down()
    {
        DB::table('persediaan_admins')->whereIn('user_id', [3235, 3243])->delete();

        $sampleKode = ['ATK001', 'ATK002', 'ATK003', 'ATK004'];
        $barangIds = DB::table('persediaan_barang')->whereIn('kode_barang', $sampleKode)->pluck('id');
        DB::table('persediaan_stock_movements')
            ->where('tipe', 'stok_awal')
            ->whereIn('barang_id', $barangIds)
            ->delete();
        DB::table('persediaan_barang')->whereIn('kode_barang', $sampleKode)->delete();
    }
}
