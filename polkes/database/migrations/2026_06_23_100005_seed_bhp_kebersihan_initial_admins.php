<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class SeedBhpKebersihanInitialAdmins extends Migration
{
    public function up()
    {
        $now = now();
        $rows = [
            ['user_id' => 3240, 'role' => 'admin_bhp_verifikator'],
            ['user_id' => 3244, 'role' => 'admin_bhp_pengambilan'],
            ['user_id' => 3239, 'role' => 'admin_bhp_master'],
        ];

        foreach ($rows as $row) {
            DB::table('bhp_kebersihan_admins')->updateOrInsert(
                ['user_id' => $row['user_id']],
                [
                    'role'       => $row['role'],
                    'is_active'  => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }
    }

    public function down()
    {
        DB::table('bhp_kebersihan_admins')
            ->whereIn('user_id', [3240, 3244, 3239])
            ->delete();
    }
}
