<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel pengembalian aset BMN.
 * 1 transaksi peminjaman tepat 1 record pengembalian (unique pada peminjaman_id).
 * Histori transaksi tetap permanen karena pengembalian tidak menghapus peminjaman.
 */
class CreatePengembalianAsetBmnTable extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pengembalian_aset_bmn')) {
            return;
        }

        Schema::create('pengembalian_aset_bmn', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('peminjaman_id');
            $table->unsignedBigInteger('received_by');

            $table->date('tanggal_kembali');

            $table->enum('kondisi_kembali', [
                'baik',
                'rusak_ringan',
                'rusak_berat',
            ])->default('baik');

            $table->text('catatan_pengembalian')->nullable();
            $table->timestamps();

            $table->foreign('peminjaman_id', 'fk_pengembalian_peminjaman')
                ->references('id')->on('peminjaman_aset_bmn')->onDelete('cascade');

            $table->foreign('received_by', 'fk_pengembalian_receiver')
                ->references('id')->on('users')->onDelete('restrict');

            $table->unique('peminjaman_id', 'pengembalian_aset_bmn_uq');
            $table->index('received_by', 'pengembalian_aset_bmn_receiver_idx');
            $table->index('tanggal_kembali', 'pengembalian_aset_bmn_tgl_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengembalian_aset_bmn');
    }
}
