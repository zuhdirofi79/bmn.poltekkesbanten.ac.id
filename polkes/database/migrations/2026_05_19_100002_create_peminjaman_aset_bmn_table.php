<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel induk transaksi peminjaman aset BMN.
 * Sengaja terpisah penuh dari modul BHP (peminjaman/peminjaman_bhp legacy).
 */
class CreatePeminjamanAsetBmnTable extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('peminjaman_aset_bmn')) {
            return;
        }

        Schema::create('peminjaman_aset_bmn', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('kode_transaksi', 32)->unique();

            $table->unsignedBigInteger('user_id');
            $table->string('jurusan', 50)->nullable();

            $table->string('tujuan_peminjaman');
            $table->date('tanggal_pinjam');
            $table->date('rencana_kembali');
            $table->text('catatan')->nullable();

            $table->enum('status', [
                'pending',
                'approved',
                'rejected',
                'dipinjam',
                'dikembalikan',
            ])->default('pending');

            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('alasan_reject')->nullable();

            $table->timestamps();

            $table->foreign('user_id', 'fk_peminjaman_aset_bmn_user')
                ->references('id')->on('users')->onDelete('cascade');

            $table->foreign('approved_by', 'fk_peminjaman_aset_bmn_approver')
                ->references('id')->on('users')->onDelete('set null');

            $table->index('status', 'peminjaman_aset_bmn_status_idx');
            $table->index('user_id', 'peminjaman_aset_bmn_user_idx');
            $table->index('approved_by', 'peminjaman_aset_bmn_approver_idx');
            $table->index('tanggal_pinjam', 'peminjaman_aset_bmn_tgl_pinjam_idx');
            $table->index(['status', 'user_id'], 'peminjaman_aset_bmn_status_user_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('peminjaman_aset_bmn');
    }
}
