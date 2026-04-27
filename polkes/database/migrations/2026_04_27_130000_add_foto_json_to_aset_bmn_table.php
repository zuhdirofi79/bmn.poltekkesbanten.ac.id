<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('aset_bmn') && ! Schema::hasColumn('aset_bmn', 'foto')) {
            Schema::table('aset_bmn', function (Blueprint $table): void {
                $table->json('foto')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('aset_bmn') && Schema::hasColumn('aset_bmn', 'foto')) {
            Schema::table('aset_bmn', function (Blueprint $table): void {
                $table->dropColumn('foto');
            });
        }
    }
};
