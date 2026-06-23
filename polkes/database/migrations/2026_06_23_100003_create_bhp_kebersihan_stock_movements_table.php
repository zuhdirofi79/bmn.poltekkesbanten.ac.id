<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBhpKebersihanStockMovementsTable extends Migration
{
    public function up()
    {
        Schema::create('bhp_kebersihan_stock_movements', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('barang_id');
            $table->enum('tipe', ['masuk', 'keluar', 'koreksi_opname']);
            $table->enum('sumber', ['permintaan', 'opname', 'manual']);
            $table->unsignedBigInteger('ref_id')->nullable();
            $table->integer('jumlah');
            $table->integer('stok_sebelum');
            $table->integer('stok_sesudah');
            $table->text('catatan')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('barang_id')->references('id')->on('bhp_kebersihan_barang');
            $table->index(['barang_id', 'created_at']);
            $table->index(['tipe', 'sumber']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('bhp_kebersihan_stock_movements');
    }
}
