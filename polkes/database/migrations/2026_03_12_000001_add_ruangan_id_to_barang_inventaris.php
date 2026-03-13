<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds ruangan_id FK to barang_inventaris.
 *
 * The existing `ruang` (varchar) column is intentionally preserved
 * for backward compatibility. Old rows keep ruang text; new rows
 * will have both ruangan_id and ruang populated.
 *
 * Run: php artisan migrate
 * Rollback safe: drops only the FK + index + column added here.
 */
class AddRuanganIdToBarangInventaris extends Migration
{
    public function up()
    {
        Schema::table('barang_inventaris', function (Blueprint $table) {
            $table->unsignedInteger('ruangan_id')->nullable()->after('ruang');
            $table->index('ruangan_id', 'idx_bi_ruangan_id');
            $table->foreign('ruangan_id', 'fk_bi_ruangan')
                  ->references('id')
                  ->on('ruangan')
                  ->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::table('barang_inventaris', function (Blueprint $table) {
            $table->dropForeign('fk_bi_ruangan');
            $table->dropIndex('idx_bi_ruangan_id');
            $table->dropColumn('ruangan_id');
        });
    }
}
