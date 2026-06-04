<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePersediaanPembelianTable extends Migration
{
    public function up()
    {
        Schema::create('persediaan_pembelian', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nomor_faktur');
            $table->string('penyedia')->nullable();
            $table->date('tanggal');
            $table->enum('unit_kerja', ['Keperawatan', 'Kebidanan', 'TLM', 'Direktorat'])->nullable();
            $table->decimal('total', 15, 2)->default(0);
            $table->string('faktur_path')->nullable();
            $table->string('bast_path')->nullable();
            $table->string('invoice_path')->nullable();
            $table->string('foto_barang_path')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index('tanggal');
            $table->index('unit_kerja');
        });
    }

    public function down()
    {
        Schema::dropIfExists('persediaan_pembelian');
    }
}
