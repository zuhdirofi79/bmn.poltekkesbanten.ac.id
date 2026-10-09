<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * kode_barang adalah kode bisnis, bukan identitas teknis.
 * Identitas teknis tetap persediaan_barang.id.
 */
class DropUniqueKodeBarangOnPersediaanBarang extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('persediaan_barang')) {
            return;
        }

        $unique = 'persediaan_barang_kode_barang_unit_kerja_unique';
        if ($this->indexExists('persediaan_barang', $unique)) {
            Schema::table('persediaan_barang', function (Blueprint $table): void {
                $table->dropUnique(['kode_barang', 'unit_kerja']);
            });
        }

        $plain = 'persediaan_barang_kode_barang_index';
        if (! $this->indexExists('persediaan_barang', $plain)) {
            Schema::table('persediaan_barang', function (Blueprint $table): void {
                $table->index('kode_barang');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('persediaan_barang')) {
            return;
        }

        $duplicate = DB::table('persediaan_barang')
            ->select('kode_barang', 'unit_kerja')
            ->groupBy('kode_barang', 'unit_kerja')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($duplicate) {
            return;
        }

        $unique = 'persediaan_barang_kode_barang_unit_kerja_unique';
        if (! $this->indexExists('persediaan_barang', $unique)) {
            Schema::table('persediaan_barang', function (Blueprint $table): void {
                $table->unique(['kode_barang', 'unit_kerja']);
            });
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        $connection = Schema::getConnection();
        $driver = $connection->getDriverName();

        if ($driver === 'mysql') {
            $rows = $connection->select('SHOW INDEX FROM `' . $table . '`');
            foreach ($rows as $row) {
                if (($row->Key_name ?? '') === $index) {
                    return true;
                }
            }

            return false;
        }

        if ($driver === 'sqlite') {
            $rows = $connection->select("PRAGMA index_list('" . $table . "')");
            foreach ($rows as $row) {
                if (($row->name ?? '') === $index) {
                    return true;
                }
            }
        }

        return false;
    }
}
