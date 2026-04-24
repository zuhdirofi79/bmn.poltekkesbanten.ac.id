<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('aset_bmn') && ! Schema::hasColumn('aset_bmn', 'lokasi')) {
            Schema::table('aset_bmn', function (Blueprint $table) {
                $table->string('lokasi', 255)->nullable();
            });
        }

        if (! Schema::hasTable('aset_bmn') || ! Schema::hasColumn('aset_bmn', 'lokasi_id')) {
            return;
        }

        if (Schema::getConnection()->getDriverName() === 'mysql'
            && Schema::hasTable('lokasi_aset')) {
            DB::statement('
                UPDATE aset_bmn a
                INNER JOIN lokasi_aset l ON l.id = a.lokasi_id
                SET a.lokasi = l.nama_lokasi
                WHERE a.lokasi IS NULL OR a.lokasi = ""
            ');

            try {
                Schema::table('aset_bmn', function (Blueprint $table) {
                    $table->dropForeign(['lokasi_id']);
                });
            } catch (\Throwable $e) {
                // Kunci asing mungkin nama lain / sudah dihapus
            }

            DB::statement('ALTER TABLE aset_bmn MODIFY lokasi_id BIGINT UNSIGNED NULL');

            Schema::table('aset_bmn', function (Blueprint $table) {
                $table->foreign('lokasi_id')->references('id')->on('lokasi_aset')->onDelete('restrict');
            });
        } elseif (Schema::getConnection()->getDriverName() === 'sqlite') {
            // SQLite: tidak mengubah FK di sini; kolom `lokasi` sudah tersedia (tanpa null lokasi_id).
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('aset_bmn') && Schema::getConnection()->getDriverName() === 'mysql' && Schema::hasColumn('aset_bmn', 'lokasi_id')) {
            try {
                Schema::table('aset_bmn', function (Blueprint $table) {
                    $table->dropForeign(['lokasi_id']);
                });
            } catch (\Throwable $e) {
            }
            try {
                DB::statement('ALTER TABLE aset_bmn MODIFY lokasi_id BIGINT UNSIGNED NOT NULL');
            } catch (\Throwable $e) {
            }
            Schema::table('aset_bmn', function (Blueprint $table) {
                $table->foreign('lokasi_id')->references('id')->on('lokasi_aset')->onDelete('restrict');
            });
        }

        if (Schema::hasTable('aset_bmn') && Schema::hasColumn('aset_bmn', 'lokasi')) {
            Schema::table('aset_bmn', function (Blueprint $table) {
                $table->dropColumn('lokasi');
            });
        }
    }
};
