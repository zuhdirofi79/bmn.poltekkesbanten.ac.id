<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('aset_bmn') || Schema::hasColumn('aset_bmn', 'merk')) {
            return;
        }

        Schema::table('aset_bmn', function (Blueprint $table): void {
            $table->string('merk', 191)->nullable()->after('nama_barang');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('aset_bmn') || ! Schema::hasColumn('aset_bmn', 'merk')) {
            return;
        }

        Schema::table('aset_bmn', function (Blueprint $table): void {
            $table->dropColumn('merk');
        });
    }
};
