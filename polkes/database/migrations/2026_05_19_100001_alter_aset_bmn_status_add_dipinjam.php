<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Status aset_bmn diperluas dengan nilai "dipinjam" supaya satu aset tidak bisa
 * dipinjam ganda. Kolom existing bertipe VARCHAR; perubahan ke ENUM dilakukan
 * via raw SQL agar nilai legacy ('aktif', 'dihapus', 'dipindahtangankan') aman.
 */
class AlterAsetBmnStatusAddDipinjam extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('aset_bmn') || ! Schema::hasColumn('aset_bmn', 'status')) {
            return;
        }

        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        // Normalisasi nilai existing supaya konsisten dengan enum target.
        DB::statement("UPDATE aset_bmn SET status = 'aktif' WHERE status IS NULL OR status = ''");

        DB::statement(
            "ALTER TABLE aset_bmn MODIFY status ENUM('aktif','dipinjam','dihapus','dipindahtangankan') NOT NULL DEFAULT 'aktif'"
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('aset_bmn') || ! Schema::hasColumn('aset_bmn', 'status')) {
            return;
        }

        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        // Aset yang sedang "dipinjam" dikembalikan ke status netral sebelum revert.
        DB::statement("UPDATE aset_bmn SET status = 'aktif' WHERE status = 'dipinjam'");

        DB::statement(
            "ALTER TABLE aset_bmn MODIFY status VARCHAR(50) NOT NULL DEFAULT 'aktif'"
        );
    }
}
