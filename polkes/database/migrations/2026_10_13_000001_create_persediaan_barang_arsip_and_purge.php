<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Arsip identitas master barang yang dihapus permanen dari sampah,
 * plus antrean batch purge. Tidak menghapus histori transaksi.
 */
class CreatePersediaanBarangArsipAndPurge extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('persediaan_barang_arsip')) {
            Schema::create('persediaan_barang_arsip', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('original_barang_id')->unique();
                $table->string('kode_barang');
                $table->string('nama_barang');
                $table->string('satuan')->nullable();
                $table->string('unit_kerja');
                $table->integer('stok_awal')->default(0);
                $table->integer('stok_minimum')->default(0);
                $table->decimal('harga_satuan', 15, 2)->default(0);
                $table->boolean('is_active')->default(true);
                $table->string('barcode_payload')->nullable();
                $table->unsignedBigInteger('rak_id')->nullable();
                $table->timestamp('soft_deleted_at')->nullable();
                $table->unsignedBigInteger('archived_by')->nullable();
                $table->timestamp('archived_at')->nullable();
                $table->timestamps();

                $table->index('kode_barang');
                $table->index('unit_kerja');
            });
        }

        if (! Schema::hasTable('persediaan_purge_batches')) {
            Schema::create('persediaan_purge_batches', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->string('public_id', 40)->unique();
                $table->unsignedBigInteger('admin_id')->nullable();
                $table->string('mode', 20);
                $table->string('status', 20)->default('running');
                $table->unsignedInteger('total')->default(0);
                $table->unsignedInteger('processed')->default(0);
                $table->unsignedInteger('succeeded')->default(0);
                $table->unsignedInteger('failed')->default(0);
                $table->string('step_token', 40)->nullable();
                $table->timestamp('locked_at')->nullable();
                $table->timestamps();

                $table->index('status');
            });
        }

        if (! Schema::hasTable('persediaan_purge_logs')) {
            Schema::create('persediaan_purge_logs', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('batch_id');
                $table->unsignedBigInteger('original_barang_id');
                $table->string('kode_barang')->nullable();
                $table->string('nama_barang')->nullable();
                $table->string('unit_kerja')->nullable();
                $table->string('status', 20)->default('pending');
                $table->boolean('history_archived')->default(false);
                $table->string('message', 255)->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->timestamps();

                $table->unique(['batch_id', 'original_barang_id']);
                $table->index(['batch_id', 'status', 'id']);
            });
        }

        $this->addSnapshotColumns('persediaan_stock_movements', true);
        $this->addSnapshotColumns('persediaan_rak_movements', false);
    }

    public function down(): void
    {
        Schema::dropIfExists('persediaan_purge_logs');
        Schema::dropIfExists('persediaan_purge_batches');
        Schema::dropIfExists('persediaan_barang_arsip');

        foreach (['persediaan_stock_movements', 'persediaan_rak_movements'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            Schema::table($table, function (Blueprint $blueprint) use ($table): void {
                foreach (['kode_barang', 'nama_barang', 'satuan', 'harga_satuan'] as $column) {
                    if ($column === 'harga_satuan' && $table === 'persediaan_rak_movements') {
                        continue;
                    }
                    if (Schema::hasColumn($table, $column)) {
                        $blueprint->dropColumn($column);
                    }
                }
            });
        }
    }

    private function addSnapshotColumns(string $table, bool $withHarga): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($table, $withHarga): void {
            if (! Schema::hasColumn($table, 'kode_barang')) {
                $blueprint->string('kode_barang')->nullable();
            }
            if (! Schema::hasColumn($table, 'nama_barang')) {
                $blueprint->string('nama_barang')->nullable();
            }
            if (! Schema::hasColumn($table, 'satuan')) {
                $blueprint->string('satuan')->nullable();
            }
            if ($withHarga && ! Schema::hasColumn($table, 'harga_satuan')) {
                $blueprint->decimal('harga_satuan', 15, 2)->nullable();
            }
        });
    }
}
