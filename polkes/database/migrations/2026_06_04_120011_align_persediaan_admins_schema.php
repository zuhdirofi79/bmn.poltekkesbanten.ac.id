<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menyesuaikan skema lama persediaan_admins (unit_kerja / enum role) ke satker + varchar role.
 */
class AlignPersediaanAdminsSchema extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('persediaan_admins')) {
            return;
        }

        if (! Schema::hasColumn('persediaan_admins', 'unit_kerja')) {
            return;
        }

        Schema::table('persediaan_admins', function (Blueprint $table) {
            $table->string('satker', 100)->nullable()->after('role');
        });

        DB::statement('UPDATE persediaan_admins SET satker = unit_kerja WHERE unit_kerja IS NOT NULL');

        Schema::table('persediaan_admins', function (Blueprint $table) {
            $table->dropColumn('unit_kerja');
        });

        // Ubah role dari enum ke varchar jika perlu (MySQL).
        try {
            DB::statement('ALTER TABLE persediaan_admins MODIFY role VARCHAR(100) NOT NULL');
        } catch (\Throwable $e) {
            // Abaikan jika sudah varchar.
        }
    }

    public function down()
    {
        if (! Schema::hasTable('persediaan_admins') || Schema::hasColumn('persediaan_admins', 'unit_kerja')) {
            return;
        }

        Schema::table('persediaan_admins', function (Blueprint $table) {
            $table->string('unit_kerja')->nullable()->after('role');
        });

        DB::statement('UPDATE persediaan_admins SET unit_kerja = satker WHERE satker IS NOT NULL');

        Schema::table('persediaan_admins', function (Blueprint $table) {
            $table->dropColumn('satker');
        });
    }
}
