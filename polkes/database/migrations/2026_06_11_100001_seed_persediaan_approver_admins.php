<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seed admin persediaan: superadmin + approver unit kerja.
 * Idempotent — aman dijalankan ulang.
 */
class SeedPersediaanApproverAdmins extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('persediaan_admins')) {
            return;
        }

        $now = now();

        $rows = [
            ['user_id' => 3235, 'role' => 'superadmin_persediaan', 'satker' => null],
            ['user_id' => 3243, 'role' => 'superadmin_persediaan', 'satker' => null],
            ['user_id' => 3238, 'role' => 'admin_persediaan_keperawatan', 'satker' => 'Keperawatan'],
            ['user_id' => 3236, 'role' => 'admin_persediaan_kebidanan', 'satker' => 'Kebidanan'],
            ['user_id' => 3237, 'role' => 'admin_persediaan_tlm', 'satker' => 'TLM'],
        ];

        foreach ($rows as $row) {
            $exists = DB::table('persediaan_admins')
                ->where('user_id', $row['user_id'])
                ->exists();

            if ($exists) {
                DB::table('persediaan_admins')
                    ->where('user_id', $row['user_id'])
                    ->update([
                        'role'       => $row['role'],
                        'satker'     => $row['satker'],
                        'is_active'  => 1,
                        'updated_at' => $now,
                    ]);
            } else {
                DB::table('persediaan_admins')->insert([
                    'user_id'    => $row['user_id'],
                    'role'       => $row['role'],
                    'satker'     => $row['satker'],
                    'is_active'  => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down()
    {
        if (! Schema::hasTable('persediaan_admins')) {
            return;
        }

        DB::table('persediaan_admins')->whereIn('user_id', [3236, 3237, 3238])->delete();
    }
}
