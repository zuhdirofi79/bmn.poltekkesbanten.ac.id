<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBmnSewaPengajuanTable extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bmn_sewa_pengajuan')) {
            return;
        }

        Schema::create('bmn_sewa_pengajuan', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('kode_sewa', 100)->unique();
            $table->enum('jenis_penyewa', ['umum', 'pegawai']);
            $table->string('nama_lengkap', 255);
            $table->string('nomor_wa', 30);
            $table->string('email', 255);
            $table->unsignedBigInteger('tarif_id');
            $table->date('tanggal_mulai_sewa');
            $table->date('tanggal_rencana_kembali');
            $table->text('tujuan_peminjaman');
            $table->string('surat_resmi_path', 255)->nullable();
            $table->string('bukti_bayar_path', 255)->nullable();
            $table->text('catatan')->nullable();
            $table->enum('payment_status', ['pending', 'confirmed', 'rejected'])->default('pending');
            $table->unsignedBigInteger('admin_payment_by')->nullable();
            $table->timestamp('admin_payment_at')->nullable();
            $table->text('admin_payment_note')->nullable();
            $table->enum('coordination_status', ['pending', 'sent_to_team', 'done'])->default('pending');
            $table->enum('status', [
                'pending',
                'payment_review',
                'payment_confirmed',
                'coordinated',
                'approved',
                'rejected',
                'completed',
            ])->default('pending');
            $table->timestamps();

            $table->foreign('tarif_id')->references('id')->on('bmn_sewa_tarifs');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bmn_sewa_pengajuan');
    }
}
