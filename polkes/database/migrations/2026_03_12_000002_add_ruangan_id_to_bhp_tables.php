<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds ruangan_id FK to barangbhp_alat and barangbhp_bahan.
 * Location is always derived through the room: item → ruangan → lab_locations.
 * Run: php artisan migrate
 */
class AddRuanganIdToBhpTables extends Migration
{
    public function up()
    {
        Schema::table('barangbhp_alat', function (Blueprint $table) {
            $table->unsignedInteger('ruangan_id')->nullable()->after('kondisi');
            $table->index('ruangan_id', 'idx_bhp_alat_ruangan_id');
            $table->foreign('ruangan_id', 'fk_bhp_alat_ruangan')
                  ->references('id')->on('ruangan')
                  ->onDelete('set null');
        });

        Schema::table('barangbhp_bahan', function (Blueprint $table) {
            $table->unsignedInteger('ruangan_id')->nullable()->after('tgl_expired');
            $table->index('ruangan_id', 'idx_bhp_bahan_ruangan_id');
            $table->foreign('ruangan_id', 'fk_bhp_bahan_ruangan')
                  ->references('id')->on('ruangan')
                  ->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::table('barangbhp_alat', function (Blueprint $table) {
            $table->dropForeign('fk_bhp_alat_ruangan');
            $table->dropIndex('idx_bhp_alat_ruangan_id');
            $table->dropColumn('ruangan_id');
        });

        Schema::table('barangbhp_bahan', function (Blueprint $table) {
            $table->dropForeign('fk_bhp_bahan_ruangan');
            $table->dropIndex('idx_bhp_bahan_ruangan_id');
            $table->dropColumn('ruangan_id');
        });
    }
}
