<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Soft delete administratif. Tidak mengubah status operasional
 * dan tidak menyentuh stock movement.
 */
class AddSoftDeletesToPersediaanBarangAndPermintaan extends Migration
{
    public function up(): void
    {
        $this->addSoftDeletes('persediaan_barang');
        $this->addSoftDeletes('persediaan_permintaan');
    }

    public function down(): void
    {
        $this->dropSoftDeletes('persediaan_barang');
        $this->dropSoftDeletes('persediaan_permintaan');
    }

    private function addSoftDeletes(string $table): void
    {
        if (! Schema::hasTable($table) || Schema::hasColumn($table, 'deleted_at')) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->softDeletes();
            $blueprint->index('deleted_at');
        });
    }

    private function dropSoftDeletes(string $table): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'deleted_at')) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->dropIndex($table . '_deleted_at_index');
            $blueprint->dropSoftDeletes();
        });
    }
}
