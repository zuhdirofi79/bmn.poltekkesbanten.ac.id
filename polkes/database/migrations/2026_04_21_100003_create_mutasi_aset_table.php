<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMutasiAsetTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('mutasi_aset', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('aset_id');
            $table->unsignedBigInteger('dari_lokasi_id')->nullable();
            $table->unsignedBigInteger('ke_lokasi_id');
            $table->date('tanggal_mutasi');
            $table->text('keterangan')->nullable();
            $table->timestamps();

            $table->foreign('aset_id')->references('id')->on('aset_bmn')->onDelete('cascade');
            $table->foreign('dari_lokasi_id')->references('id')->on('lokasi_aset')->onDelete('set null');
            $table->foreign('ke_lokasi_id')->references('id')->on('lokasi_aset')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('mutasi_aset');
    }
}
