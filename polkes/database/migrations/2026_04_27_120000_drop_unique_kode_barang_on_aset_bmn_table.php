<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('aset_bmn')) {
            return;
        }

        Schema::table('aset_bmn', function (Blueprint $table): void {
            try {
                $table->dropUnique(['kode_barang']);
            } catch (\Throwable) {
                // Index name may differ or already removed (non-fatal).
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('aset_bmn')) {
            return;
        }

        Schema::table('aset_bmn', function (Blueprint $table): void {
            try {
                $table->unique('kode_barang');
            } catch (\Throwable) {
            }
        });
    }
};
