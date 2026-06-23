<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Idempotent: berikan Pak Singgih (user_id 3240) akses penuh superadmin BHP Kebersihan.
 */
class PromoteSinggihSuperadminBhp extends Migration
{
    private const SINGGIH_USER_ID = 3240;

    public function up()
    {
        if (! Schema::hasTable('bhp_kebersihan_admins')) {
            return;
        }

        $now = now();

        DB::table('bhp_kebersihan_admins')->updateOrInsert(
            ['user_id' => self::SINGGIH_USER_ID],
            [
                'role'       => 'superadmin_bhp',
                'is_active'  => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );
    }

    public function down()
    {
        if (! Schema::hasTable('bhp_kebersihan_admins')) {
            return;
        }

        DB::table('bhp_kebersihan_admins')->updateOrInsert(
            ['user_id' => self::SINGGIH_USER_ID],
            [
                'role'       => 'admin_bhp_verifikator',
                'is_active'  => 1,
                'updated_at' => now(),
            ],
        );
    }
}
