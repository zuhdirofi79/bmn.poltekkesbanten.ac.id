<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('peminjaman_aset_bmn_documents')) {
            return;
        }

        Schema::create('peminjaman_aset_bmn_documents', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('peminjaman_aset_bmn_id');
            $table->string('file_path', 255);
            $table->string('original_name', 255)->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedInteger('file_size')->nullable();
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->timestamps();

            $table->foreign('peminjaman_aset_bmn_id', 'fk_pmnj_doc_header')
                ->references('id')
                ->on('peminjaman_aset_bmn')
                ->onDelete('cascade');

            $table->index('peminjaman_aset_bmn_id', 'pmnj_doc_header_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('peminjaman_aset_bmn_documents');
    }
};
