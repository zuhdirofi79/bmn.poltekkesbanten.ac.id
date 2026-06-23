<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddBarcodePathToBhpKebersihanBarangTable extends Migration
{
    public function up()
    {
        Schema::table('bhp_kebersihan_barang', function (Blueprint $table) {
            $table->string('barcode_path', 255)->nullable()->after('barcode_token');
        });
    }

    public function down()
    {
        Schema::table('bhp_kebersihan_barang', function (Blueprint $table) {
            $table->dropColumn('barcode_path');
        });
    }
}
