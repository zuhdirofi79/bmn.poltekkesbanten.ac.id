<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBhpKebersihanPermintaanTable extends Migration
{
    public function up()
    {
        Schema::create('bhp_kebersihan_permintaan', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nomor_permintaan')->unique();
            $table->unsignedBigInteger('barang_id');
            $table->string('nama_pengambil');
            $table->string('no_wa_pengambil', 20);
            $table->date('tanggal_pengambilan');
            $table->integer('jumlah_diminta');
            $table->enum('status', [
                'pending',
                'disetujui',
                'ditolak',
                'siap_diambil',
                'sudah_diambil',
            ])->default('pending');
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->text('verification_note')->nullable();
            $table->unsignedBigInteger('processed_by')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->text('process_note')->nullable();
            $table->timestamps();

            $table->foreign('barang_id')->references('id')->on('bhp_kebersihan_barang');
            $table->index(['status', 'created_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('bhp_kebersihan_permintaan');
    }
}
