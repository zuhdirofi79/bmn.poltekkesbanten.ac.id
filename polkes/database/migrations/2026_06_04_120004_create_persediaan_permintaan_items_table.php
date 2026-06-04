<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePersediaanPermintaanItemsTable extends Migration
{
    public function up()
    {
        Schema::create('persediaan_permintaan_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('permintaan_id');
            $table->unsignedBigInteger('barang_id')->nullable();
            $table->string('kode_barang');
            $table->string('nama_barang');
            $table->string('satuan')->default('Buah');
            $table->integer('stok_tersedia')->default(0);
            $table->integer('jumlah_diminta')->default(0);
            $table->integer('jumlah_disetujui')->nullable();
            $table->timestamps();

            $table->index('permintaan_id');
            $table->index('barang_id');

            $table->foreign('permintaan_id')
                ->references('id')->on('persediaan_permintaan')
                ->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('persediaan_permintaan_items');
    }
}
