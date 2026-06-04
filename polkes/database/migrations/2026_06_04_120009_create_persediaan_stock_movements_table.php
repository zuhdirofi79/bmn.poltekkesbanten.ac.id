<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Buku kas stok persediaan (sumber kebenaran untuk stok akhir).
 * Stok Akhir = stok_awal + SUM(qty_masuk) - SUM(qty_keluar).
 */
class CreatePersediaanStockMovementsTable extends Migration
{
    public function up()
    {
        Schema::create('persediaan_stock_movements', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('barang_id');
            $table->enum('unit_kerja', ['Keperawatan', 'Kebidanan', 'TLM', 'Direktorat']);
            $table->enum('tipe', [
                'stok_awal',
                'pembelian',
                'permintaan_keluar',
                'opname_adjustment',
            ]);
            $table->string('ref_table')->nullable();
            $table->unsignedBigInteger('ref_id')->nullable();
            $table->integer('qty_masuk')->default(0);
            $table->integer('qty_keluar')->default(0);
            $table->string('keterangan')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index('barang_id');
            $table->index('unit_kerja');
            $table->index('tipe');
            $table->index(['ref_table', 'ref_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('persediaan_stock_movements');
    }
}
