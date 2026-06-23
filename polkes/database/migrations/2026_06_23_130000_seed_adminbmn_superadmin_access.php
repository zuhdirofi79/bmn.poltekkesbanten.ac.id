<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Idempotent: berikan user adminbmn (id 3233) akses superadmin ke modul BHP Kebersihan
 * dan flag can_approve_bmn untuk modul peminjaman BMN.
 *
 * Modul Admin Sewa BMN tidak memakai tabel admin — akses via konstanta di App\User.
 */
class SeedAdminbmnSuperadminAccess extends Migration
{
    private const ADMINBMN_USER_ID = 3233;

    public function up()
    {
        $now = now();

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'can_approve_bmn')) {
            DB::table('users')
                ->where('id', self::ADMINBMN_USER_ID)
                ->update([
                    'can_approve_bmn' => 1,
                    'updated_at'      => $now,
                ]);
        }

        if (Schema::hasTable('bhp_kebersihan_admins')) {
            DB::table('bhp_kebersihan_admins')->updateOrInsert(
                ['user_id' => self::ADMINBMN_USER_ID],
                [
                    'role'       => 'superadmin_bhp',
                    'is_active'  => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }
    }

    public function down()
    {
        if (Schema::hasTable('bhp_kebersihan_admins')) {
            DB::table('bhp_kebersihan_admins')
                ->where('user_id', self::ADMINBMN_USER_ID)
                ->delete();
        }

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'can_approve_bmn')) {
            DB::table('users')
                ->where('id', self::ADMINBMN_USER_ID)
                ->update([
                    'can_approve_bmn' => 0,
                    'updated_at'      => now(),
                ]);
        }
    }
}
