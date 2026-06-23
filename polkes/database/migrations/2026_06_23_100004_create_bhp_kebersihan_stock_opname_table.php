<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBhpKebersihanStockOpnameTable extends Migration
{
    public function up()
    {
        Schema::create('bhp_kebersihan_stock_opname', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->date('tanggal_opname');
            $table->text('catatan')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('bhp_kebersihan_stock_opname_details', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('opname_id');
            $table->unsignedBigInteger('barang_id');
            $table->integer('stok_sistem');
            $table->integer('stok_fisik');
            $table->integer('selisih');
            $table->text('keterangan')->nullable();
            $table->timestamps();

            $table->foreign('opname_id')->references('id')->on('bhp_kebersihan_stock_opname')->onDelete('cascade');
            $table->foreign('barang_id')->references('id')->on('bhp_kebersihan_barang');
        });
    }

    public function down()
    {
        Schema::dropIfExists('bhp_kebersihan_stock_opname_details');
        Schema::dropIfExists('bhp_kebersihan_stock_opname');
    }
}
