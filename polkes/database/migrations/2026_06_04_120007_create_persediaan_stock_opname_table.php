<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePersediaanStockOpnameTable extends Migration
{
    public function up()
    {
        Schema::create('persediaan_stock_opname', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->enum('unit_kerja', ['Keperawatan', 'Kebidanan', 'TLM', 'Direktorat']);
            $table->date('tanggal_opname');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->index('unit_kerja');
            $table->index('tanggal_opname');
        });
    }

    public function down()
    {
        Schema::dropIfExists('persediaan_stock_opname');
    }
}
