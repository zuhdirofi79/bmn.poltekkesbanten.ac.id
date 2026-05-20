<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Detail aset per transaksi peminjaman (multi-aset per pengajuan).
 * Unique (peminjaman_id, aset_id) mencegah duplikasi baris pada satu transaksi.
 */
class CreatePeminjamanAsetBmnDetailTable extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('peminjaman_aset_bmn_detail')) {
            return;
        }

        Schema::create('peminjaman_aset_bmn_detail', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('peminjaman_id');
            $table->unsignedBigInteger('aset_id');

            $table->string('kondisi_pinjam', 50)->nullable();
            $table->text('catatan_item')->nullable();
            $table->timestamps();

            $table->foreign('peminjaman_id', 'fk_pmnj_detail_header')
                ->references('id')->on('peminjaman_aset_bmn')->onDelete('cascade');

            $table->foreign('aset_id', 'fk_pmnj_detail_aset')
                ->references('id')->on('aset_bmn')->onDelete('restrict');

            $table->unique(['peminjaman_id', 'aset_id'], 'pmnj_detail_uq');
            $table->index('aset_id', 'pmnj_detail_aset_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('peminjaman_aset_bmn_detail');
    }
}
