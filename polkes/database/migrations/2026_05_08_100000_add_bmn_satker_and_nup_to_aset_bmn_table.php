<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bmn_satker')) {
            Schema::create('bmn_satker', function (Blueprint $table): void {
                $table->id();
                $table->string('kode', 50)->nullable();
                $table->string('nama', 191);
                $table->string('slug', 191)->nullable();
                $table->unsignedInteger('sort_order')->default(0);
            });
        }

        if (Schema::hasTable('bmn_satker') && DB::table('bmn_satker')->count() === 0) {
            DB::table('bmn_satker')->insert([
                'kode' => 'DEF',
                'nama' => 'Satker Default',
                'slug' => 'satker-default',
                'sort_order' => 0,
            ]);
        }

        if (! Schema::hasTable('aset_bmn')) {
            return;
        }

        Schema::table('aset_bmn', function (Blueprint $table): void {
            if (! Schema::hasColumn('aset_bmn', 'satker_id')) {
                $table->unsignedBigInteger('satker_id')->nullable()->after('status');
            }
            if (! Schema::hasColumn('aset_bmn', 'nup')) {
                $table->string('nup', 50)->nullable()->after('kode_barang');
            }
        });

        // Drop unique index on nup if it exists, then add a plain index.
        // NUP is allowed to be duplicated per business rule.
        try {
            Schema::table('aset_bmn', function (Blueprint $table): void {
                $table->dropUnique(['nup']);
            });
        } catch (\Throwable) {
            // No unique index to drop – that's fine.
        }

        try {
            Schema::table('aset_bmn', function (Blueprint $table): void {
                $table->index('nup');
            });
        } catch (\Throwable) {
            // Index may already exist.
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('aset_bmn')) {
            return;
        }

        try {
            Schema::table('aset_bmn', function (Blueprint $table): void {
                $table->dropIndex(['nup']);
            });
        } catch (\Throwable) {
        }
    }
};
