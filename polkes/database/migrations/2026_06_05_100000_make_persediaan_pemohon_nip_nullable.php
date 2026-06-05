<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * NIP/NIDN/NIK opsional saat registrasi pemohon Persediaan.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('persediaan_pemohon') || ! Schema::hasColumn('persediaan_pemohon', 'nip')) {
            return;
        }

        Schema::table('persediaan_pemohon', function (Blueprint $table) {
            $table->string('nip', 100)->nullable()->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('persediaan_pemohon') || ! Schema::hasColumn('persediaan_pemohon', 'nip')) {
            return;
        }

        Schema::table('persediaan_pemohon', function (Blueprint $table) {
            $table->string('nip', 50)->nullable(false)->change();
        });
    }
};
