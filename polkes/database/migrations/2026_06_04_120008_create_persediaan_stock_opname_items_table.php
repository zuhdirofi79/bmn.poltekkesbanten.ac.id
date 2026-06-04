<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePersediaanStockOpnameItemsTable extends Migration
{
    public function up()
    {
        Schema::create('persediaan_stock_opname_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('opname_id');
            $table->unsignedBigInteger('barang_id')->nullable();
            $table->string('kode_barang');
            $table->string('nama_barang');
            $table->string('satuan')->default('Buah');
            $table->integer('stok_sistem')->default(0);
            $table->integer('stok_fisik')->default(0);
            $table->integer('selisih')->default(0);
            $table->enum('kondisi', [
                'sesuai',
                'selisih_kurang',
                'selisih_lebih',
                'rusak',
                'kadaluarsa',
            ])->default('sesuai');
            $table->string('keterangan')->nullable();
            $table->timestamps();

            $table->index('opname_id');
            $table->index('barang_id');

            $table->foreign('opname_id')
                ->references('id')->on('persediaan_stock_opname')
                ->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('persediaan_stock_opname_items');
    }
}
