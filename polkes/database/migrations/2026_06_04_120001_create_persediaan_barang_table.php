<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Master barang PERSEDIAAN (barang habis pakai) per unit kerja.
 * Berbeda dari aset_bmn (aset tetap/inventaris).
 */
class CreatePersediaanBarangTable extends Migration
{
    public function up()
    {
        Schema::create('persediaan_barang', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('kode_barang');
            $table->string('nama_barang');
            $table->string('satuan')->default('Buah');
            $table->enum('unit_kerja', ['Keperawatan', 'Kebidanan', 'TLM', 'Direktorat']);
            $table->integer('stok_awal')->default(0);
            $table->integer('stok_minimum')->default(0);
            $table->decimal('harga_satuan', 15, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('unit_kerja');
            $table->index('kode_barang');
            $table->unique(['kode_barang', 'unit_kerja']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('persediaan_barang');
    }
}
