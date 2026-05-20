<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambah kolom dokumen pendukung BMN:
 *   - peminjaman_aset_bmn.nota_dinas_path           : path PDF Nota Dinas (public disk).
 *   - peminjaman_aset_bmn.nota_dinas_original_name  : nama asli file upload (untuk download).
 *   - pengembalian_aset_bmn.bukti_foto_path         : path foto bukti kondisi (public disk).
 *   - pengembalian_aset_bmn.bukti_foto_original_name: nama asli file upload.
 *
 * Defensive: cek hasTable + hasColumn agar aman dijalankan ulang dan tidak
 * merusak schema production yang sudah ada (tidak menyentuh kolom existing).
 */
class AddNotaDinasAndBuktiFotoColumns extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('peminjaman_aset_bmn')) {
            Schema::table('peminjaman_aset_bmn', function (Blueprint $table): void {
                if (! Schema::hasColumn('peminjaman_aset_bmn', 'nota_dinas_path')) {
                    $table->string('nota_dinas_path', 255)->nullable()->after('catatan');
                }
            });

            Schema::table('peminjaman_aset_bmn', function (Blueprint $table): void {
                if (! Schema::hasColumn('peminjaman_aset_bmn', 'nota_dinas_original_name')) {
                    $table->string('nota_dinas_original_name', 255)->nullable()->after('nota_dinas_path');
                }
            });
        }

        if (Schema::hasTable('pengembalian_aset_bmn')) {
            Schema::table('pengembalian_aset_bmn', function (Blueprint $table): void {
                if (! Schema::hasColumn('pengembalian_aset_bmn', 'bukti_foto_path')) {
                    $table->string('bukti_foto_path', 255)->nullable()->after('catatan_pengembalian');
                }
            });

            Schema::table('pengembalian_aset_bmn', function (Blueprint $table): void {
                if (! Schema::hasColumn('pengembalian_aset_bmn', 'bukti_foto_original_name')) {
                    $table->string('bukti_foto_original_name', 255)->nullable()->after('bukti_foto_path');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('peminjaman_aset_bmn')) {
            Schema::table('peminjaman_aset_bmn', function (Blueprint $table): void {
                if (Schema::hasColumn('peminjaman_aset_bmn', 'nota_dinas_original_name')) {
                    $table->dropColumn('nota_dinas_original_name');
                }
            });

            Schema::table('peminjaman_aset_bmn', function (Blueprint $table): void {
                if (Schema::hasColumn('peminjaman_aset_bmn', 'nota_dinas_path')) {
                    $table->dropColumn('nota_dinas_path');
                }
            });
        }

        if (Schema::hasTable('pengembalian_aset_bmn')) {
            Schema::table('pengembalian_aset_bmn', function (Blueprint $table): void {
                if (Schema::hasColumn('pengembalian_aset_bmn', 'bukti_foto_original_name')) {
                    $table->dropColumn('bukti_foto_original_name');
                }
            });

            Schema::table('pengembalian_aset_bmn', function (Blueprint $table): void {
                if (Schema::hasColumn('pengembalian_aset_bmn', 'bukti_foto_path')) {
                    $table->dropColumn('bukti_foto_path');
                }
            });
        }
    }
}
