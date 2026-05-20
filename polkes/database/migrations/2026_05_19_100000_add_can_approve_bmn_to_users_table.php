<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pisah konsep: role (Spatie), jurusan (text), can_approve_bmn (flag).
 * Hanya admin dengan can_approve_bmn = 1 yang berhak ACC peminjaman aset BMN.
 */
class AddCanApproveBmnToUsersTable extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'can_approve_bmn')) {
                $table->boolean('can_approve_bmn')
                    ->default(false)
                    ->after('role');
            }
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->index('can_approve_bmn', 'users_can_approve_bmn_idx');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            try {
                $table->dropIndex('users_can_approve_bmn_idx');
            } catch (\Throwable $e) {
                // index mungkin tidak ada di lingkungan lain; abaikan.
            }
        });

        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'can_approve_bmn')) {
                $table->dropColumn('can_approve_bmn');
            }
        });
    }
}
