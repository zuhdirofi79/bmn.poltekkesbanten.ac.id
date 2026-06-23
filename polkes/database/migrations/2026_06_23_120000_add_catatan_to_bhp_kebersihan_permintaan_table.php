<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCatatanToBhpKebersihanPermintaanTable extends Migration
{
    public function up()
    {
        Schema::table('bhp_kebersihan_permintaan', function (Blueprint $table) {
            $table->text('catatan')->nullable()->after('jumlah_diminta');
        });
    }

    public function down()
    {
        Schema::table('bhp_kebersihan_permintaan', function (Blueprint $table) {
            $table->dropColumn('catatan');
        });
    }
}
