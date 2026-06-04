<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePersediaanPembelianItemsTable extends Migration
{
    public function up()
    {
        Schema::create('persediaan_pembelian_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('pembelian_id');
            $table->unsignedBigInteger('barang_id')->nullable();
            $table->string('kode_barang');
            $table->string('nama_barang');
            $table->string('satuan')->default('Buah');
            $table->decimal('harga_satuan', 15, 2)->default(0);
            $table->integer('jumlah')->default(0);
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->timestamps();

            $table->index('pembelian_id');
            $table->index('barang_id');

            $table->foreign('pembelian_id')
                ->references('id')->on('persediaan_pembelian')
                ->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('persediaan_pembelian_items');
    }
}
