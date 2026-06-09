<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bmn_sewa_tarif_fotos')) {
            return;
        }

        Schema::create('bmn_sewa_tarif_fotos', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tarif_id');
            $table->string('foto_path', 255);
            $table->string('foto_original_name', 255)->nullable();
            $table->string('caption', 255)->nullable();
            $table->integer('sort_order')->default(0);
            $table->unsignedTinyInteger('is_primary')->default(0);
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->timestamps();

            $table->foreign('tarif_id')
                ->references('id')
                ->on('bmn_sewa_tarifs')
                ->onDelete('cascade');

            $table->index(['tarif_id', 'sort_order']);
            $table->index(['tarif_id', 'is_primary']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bmn_sewa_tarif_fotos');
    }
};
