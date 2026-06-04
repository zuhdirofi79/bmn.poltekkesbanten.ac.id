<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Akun pemohon PERSEDIAAN (terpisah dari users / admin BMN / admin sewa).
 * Dipakai oleh guard 'persediaan'. Login memakai NIP + password.
 */
class CreatePersediaanPemohonTable extends Migration
{
    public function up()
    {
        Schema::create('persediaan_pemohon', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nama');
            $table->string('nip')->unique();
            $table->string('email')->nullable();
            $table->string('nomor_wa')->nullable();
            $table->string('jabatan')->nullable();
            $table->enum('unit_kerja', ['Keperawatan', 'Kebidanan', 'TLM', 'Direktorat']);
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();

            $table->index('unit_kerja');
        });
    }

    public function down()
    {
        Schema::dropIfExists('persediaan_pemohon');
    }
}
