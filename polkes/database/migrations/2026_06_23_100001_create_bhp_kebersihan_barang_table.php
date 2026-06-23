<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBhpKebersihanBarangTable extends Migration
{
    public function up()
    {
        Schema::create('bhp_kebersihan_barang', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('kode_barang')->unique();
            $table->string('nama_barang');
            $table->string('satuan')->default('Buah');
            $table->integer('stok_awal')->default(0);
            $table->decimal('harga_satuan', 15, 2)->nullable();
            $table->string('barcode_token', 64)->unique();
            $table->enum('status', ['aktif', 'nonaktif'])->default('aktif');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('kode_barang');
        });
    }

    public function down()
    {
        Schema::dropIfExists('bhp_kebersihan_barang');
    }
}
