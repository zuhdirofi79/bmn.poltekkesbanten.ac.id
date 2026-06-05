<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menambah unit PPPK dan Pimpinan ke kolom enum unit_kerja (non-destruktif).
 */
return new class extends Migration
{
    private const ENUM = "'Keperawatan','Kebidanan','TLM','Direktorat','PPPK','Pimpinan'";

    /** @var list<string> */
    private const TABLES = [
        'persediaan_barang',
        'persediaan_pemohon',
        'persediaan_permintaan',
        'persediaan_pembelian',
        'persediaan_stock_movements',
        'persediaan_stock_opname',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'unit_kerja')) {
                continue;
            }

            $nullable = $table === 'persediaan_pembelian' ? 'NULL' : 'NOT NULL';
            DB::statement("ALTER TABLE `{$table}` MODIFY `unit_kerja` ENUM(" . self::ENUM . ") {$nullable}");
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'unit_kerja')) {
                continue;
            }

            $nullable = $table === 'persediaan_pembelian' ? 'NULL' : 'NOT NULL';
            DB::statement("ALTER TABLE `{$table}` MODIFY `unit_kerja` ENUM('Keperawatan','Kebidanan','TLM','Direktorat') {$nullable}");
        }
    }
};
