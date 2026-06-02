<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration aman/idempotent: menambahkan kolom perhitungan biaya sewa
 * (jumlah hari & total biaya) ke tabel bmn_sewa_pengajuan.
 *
 * Tidak mengubah kolom existing. Aman dijalankan berulang.
 */
class AddTotalBiayaToBmnSewaPengajuan extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bmn_sewa_pengajuan')) {
            return;
        }

        Schema::table('bmn_sewa_pengajuan', function (Blueprint $table): void {
            if (! Schema::hasColumn('bmn_sewa_pengajuan', 'jumlah_hari_sewa')) {
                $table->unsignedInteger('jumlah_hari_sewa')
                    ->nullable()
                    ->after('tanggal_rencana_kembali');
            }
            if (! Schema::hasColumn('bmn_sewa_pengajuan', 'total_biaya')) {
                $table->decimal('total_biaya', 15, 2)
                    ->nullable()
                    ->after('jumlah_hari_sewa');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('bmn_sewa_pengajuan')) {
            return;
        }

        Schema::table('bmn_sewa_pengajuan', function (Blueprint $table): void {
            foreach (['total_biaya', 'jumlah_hari_sewa'] as $col) {
                if (Schema::hasColumn('bmn_sewa_pengajuan', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
}
