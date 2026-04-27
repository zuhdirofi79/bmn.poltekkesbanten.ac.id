<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('aset_bmn')) {
            return;
        }

        if (! Schema::hasColumn('aset_bmn', 'deleted_at')) {
            return;
        }

        DB::table('aset_bmn')
            ->where('status', 'dihapus')
            ->whereNull('deleted_at')
            ->update(['deleted_at' => now()]);
    }

    public function down(): void
    {
        // Irreversible: tidak mengembalikan status bisnis lama.
    }
};
