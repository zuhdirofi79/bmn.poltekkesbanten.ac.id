<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBmnSewaTarifsTable extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bmn_sewa_tarifs')) {
            return;
        }

        Schema::create('bmn_sewa_tarifs', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('kategori', 100);
            $table->string('nama_layanan', 255);
            $table->string('satuan', 100);
            $table->decimal('tarif_umum', 15, 2);
            $table->decimal('tarif_pegawai', 15, 2)->nullable();
            $table->tinyInteger('is_active')->default(1);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['kategori', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bmn_sewa_tarifs');
    }
}
