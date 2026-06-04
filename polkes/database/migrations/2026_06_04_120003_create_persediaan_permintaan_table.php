<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePersediaanPermintaanTable extends Migration
{
    public function up()
    {
        Schema::create('persediaan_permintaan', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nomor_permintaan')->unique();
            $table->unsignedBigInteger('pemohon_id');
            $table->enum('unit_kerja', ['Keperawatan', 'Kebidanan', 'TLM', 'Direktorat']);
            $table->date('tanggal_permintaan');
            $table->text('keterangan')->nullable();
            $table->enum('status', [
                'draft',
                'diajukan',
                'menunggu_persetujuan',
                'disetujui',
                'ditolak',
                'barang_dikeluarkan',
                'selesai',
            ])->default('draft');
            $table->string('tanda_tangan_pemohon_path')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->string('tanda_tangan_admin_path')->nullable();
            $table->text('catatan_admin')->nullable();
            $table->timestamps();

            $table->index('pemohon_id');
            $table->index('unit_kerja');
            $table->index('status');
        });
    }

    public function down()
    {
        Schema::dropIfExists('persediaan_permintaan');
    }
}
