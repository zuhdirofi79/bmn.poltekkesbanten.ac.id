<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAsetBmnTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('aset_bmn', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('kode_barang')->unique();
            $table->string('nama_barang');
            $table->unsignedBigInteger('kategori_id');
            $table->decimal('nilai_perolehan', 15, 2)->default(0);
            $table->date('tanggal_perolehan')->nullable();
            $table->string('kondisi');
            $table->unsignedBigInteger('lokasi_id');
            $table->string('status')->default('aktif');
            $table->timestamps();

            $table->foreign('kategori_id')->references('id')->on('kategori_aset')->onDelete('restrict');
            $table->foreign('lokasi_id')->references('id')->on('lokasi_aset')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('aset_bmn');
    }
}
