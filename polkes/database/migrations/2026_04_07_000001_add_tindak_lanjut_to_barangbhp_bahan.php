<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tindak lanjut untuk BHP bahan medis expired: dimusnahkan | digunakan kembali
 */
class AddTindakLanjutToBarangbhpBahan extends Migration
{
    public function up()
    {
        Schema::table('barangbhp_bahan', function (Blueprint $table) {
            $table->string('tindak_lanjut', 50)->nullable()->after('ruangan_id');
        });
    }

    public function down()
    {
        Schema::table('barangbhp_bahan', function (Blueprint $table) {
            $table->dropColumn('tindak_lanjut');
        });
    }
}
