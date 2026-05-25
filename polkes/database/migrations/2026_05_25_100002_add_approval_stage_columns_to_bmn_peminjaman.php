<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Workflow approval 2 verifikator berurutan + final report untuk peminjaman aset BMN.
 * Defensive: cek hasColumn / hasTable sebelum alter.
 */
class AddApprovalStageColumnsToBmnPeminjaman extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('peminjaman_aset_bmn')) {
            return;
        }

        Schema::table('peminjaman_aset_bmn', function (Blueprint $table): void {
            if (! Schema::hasColumn('peminjaman_aset_bmn', 'approval_stage')) {
                $table->enum('approval_stage', [
                    'verifikator_1',
                    'verifikator_2',
                    'final_report',
                    'completed',
                    'rejected',
                ])->default('verifikator_1')->after('status');
            }

            if (! Schema::hasColumn('peminjaman_aset_bmn', 'verifier_1_id')) {
                $table->unsignedBigInteger('verifier_1_id')->nullable()->after('approval_stage');
            }
            if (! Schema::hasColumn('peminjaman_aset_bmn', 'verifier_1_approved_at')) {
                $table->timestamp('verifier_1_approved_at')->nullable()->after('verifier_1_id');
            }
            if (! Schema::hasColumn('peminjaman_aset_bmn', 'verifier_1_note')) {
                $table->text('verifier_1_note')->nullable()->after('verifier_1_approved_at');
            }

            if (! Schema::hasColumn('peminjaman_aset_bmn', 'verifier_2_id')) {
                $table->unsignedBigInteger('verifier_2_id')->nullable()->after('verifier_1_note');
            }
            if (! Schema::hasColumn('peminjaman_aset_bmn', 'verifier_2_approved_at')) {
                $table->timestamp('verifier_2_approved_at')->nullable()->after('verifier_2_id');
            }
            if (! Schema::hasColumn('peminjaman_aset_bmn', 'verifier_2_note')) {
                $table->text('verifier_2_note')->nullable()->after('verifier_2_approved_at');
            }

            if (! Schema::hasColumn('peminjaman_aset_bmn', 'final_report_by')) {
                $table->unsignedBigInteger('final_report_by')->nullable()->after('verifier_2_note');
            }
            if (! Schema::hasColumn('peminjaman_aset_bmn', 'final_report_at')) {
                $table->timestamp('final_report_at')->nullable()->after('final_report_by');
            }
            if (! Schema::hasColumn('peminjaman_aset_bmn', 'final_report_note')) {
                $table->text('final_report_note')->nullable()->after('final_report_at');
            }

            if (! Schema::hasColumn('peminjaman_aset_bmn', 'rejected_stage')) {
                $table->string('rejected_stage', 50)->nullable()->after('final_report_note');
            }
            if (! Schema::hasColumn('peminjaman_aset_bmn', 'rejected_reason')) {
                $table->text('rejected_reason')->nullable()->after('rejected_stage');
            }
        });

        $this->addIndexIfMissing('peminjaman_aset_bmn', 'peminjaman_aset_bmn_approval_stage_idx', 'approval_stage');
        $this->addIndexIfMissing('peminjaman_aset_bmn', 'peminjaman_aset_bmn_verifier_1_idx', 'verifier_1_id');
        $this->addIndexIfMissing('peminjaman_aset_bmn', 'peminjaman_aset_bmn_verifier_2_idx', 'verifier_2_id');
        $this->addIndexIfMissing('peminjaman_aset_bmn', 'peminjaman_aset_bmn_final_report_by_idx', 'final_report_by');

        $this->addForeignKeyIfMissing(
            'peminjaman_aset_bmn',
            'verifier_1_id',
            'fk_peminjaman_aset_bmn_verifier_1',
            'users',
            'id'
        );
        $this->addForeignKeyIfMissing(
            'peminjaman_aset_bmn',
            'verifier_2_id',
            'fk_peminjaman_aset_bmn_verifier_2',
            'users',
            'id'
        );
        $this->addForeignKeyIfMissing(
            'peminjaman_aset_bmn',
            'final_report_by',
            'fk_peminjaman_aset_bmn_final_report_by',
            'users',
            'id'
        );

        $this->backfillApprovalStage();
    }

    public function down(): void
    {
        if (! Schema::hasTable('peminjaman_aset_bmn')) {
            return;
        }

        Schema::table('peminjaman_aset_bmn', function (Blueprint $table): void {
            $this->dropForeignKeyIfExists('peminjaman_aset_bmn', 'fk_peminjaman_aset_bmn_verifier_1');
            $this->dropForeignKeyIfExists('peminjaman_aset_bmn', 'fk_peminjaman_aset_bmn_verifier_2');
            $this->dropForeignKeyIfExists('peminjaman_aset_bmn', 'fk_peminjaman_aset_bmn_final_report_by');

            $this->dropIndexIfExists('peminjaman_aset_bmn', 'peminjaman_aset_bmn_approval_stage_idx');
            $this->dropIndexIfExists('peminjaman_aset_bmn', 'peminjaman_aset_bmn_verifier_1_idx');
            $this->dropIndexIfExists('peminjaman_aset_bmn', 'peminjaman_aset_bmn_verifier_2_idx');
            $this->dropIndexIfExists('peminjaman_aset_bmn', 'peminjaman_aset_bmn_final_report_by_idx');

            $columns = [
                'approval_stage',
                'verifier_1_id',
                'verifier_1_approved_at',
                'verifier_1_note',
                'verifier_2_id',
                'verifier_2_approved_at',
                'verifier_2_note',
                'final_report_by',
                'final_report_at',
                'final_report_note',
                'rejected_stage',
                'rejected_reason',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('peminjaman_aset_bmn', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function backfillApprovalStage(): void
    {
        if (! Schema::hasColumn('peminjaman_aset_bmn', 'approval_stage')) {
            return;
        }

        DB::table('peminjaman_aset_bmn')
            ->whereIn('status', ['approved', 'dipinjam', 'dikembalikan'])
            ->update(['approval_stage' => 'completed']);

        DB::table('peminjaman_aset_bmn')
            ->where('status', 'rejected')
            ->update(['approval_stage' => 'rejected']);

        DB::table('peminjaman_aset_bmn')
            ->where('status', 'pending')
            ->update(['approval_stage' => 'verifikator_1']);
    }

    private function addIndexIfMissing(string $table, string $indexName, string $column): void
    {
        if (! Schema::hasColumn($table, $column) || $this->indexExists($table, $indexName)) {
            return;
        }

        try {
            Schema::table($table, function (Blueprint $blueprint) use ($column, $indexName): void {
                $blueprint->index($column, $indexName);
            });
        } catch (\Throwable) {
            // Index mungkin sudah ada (race / duplicate run).
        }
    }

    private function addForeignKeyIfMissing(
        string $table,
        string $column,
        string $constraintName,
        string $refTable,
        string $refColumn
    ): void {
        if (! Schema::hasColumn($table, $column) || $this->foreignKeyExists($table, $constraintName)) {
            return;
        }

        try {
            Schema::table($table, function (Blueprint $blueprint) use ($column, $constraintName, $refTable, $refColumn): void {
                $blueprint->foreign($column, $constraintName)
                    ->references($refColumn)
                    ->on($refTable)
                    ->onDelete('set null');
            });
        } catch (\Throwable) {
            // FK mungkin sudah ada (race / duplicate run).
        }
    }

    private function dropForeignKeyIfExists(string $table, string $constraintName): void
    {
        if (! $this->foreignKeyExists($table, $constraintName)) {
            return;
        }

        try {
            Schema::table($table, function (Blueprint $blueprint) use ($constraintName): void {
                $blueprint->dropForeign($constraintName);
            });
        } catch (\Throwable) {
            // Ignore jika FK sudah dihapus.
        }
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if (! $this->indexExists($table, $indexName)) {
            return;
        }

        try {
            Schema::table($table, function (Blueprint $blueprint) use ($indexName): void {
                $blueprint->dropIndex($indexName);
            });
        } catch (\Throwable) {
            // Ignore jika index sudah dihapus.
        }
    }

    private function indexExists(string $table, string $indexName): bool
    {
        if (DB::getDriverName() === 'sqlite') {
            $rows = DB::select("PRAGMA index_list('{$table}')");

            return collect($rows)->contains(fn ($row) => ($row->name ?? null) === $indexName);
        }

        return collect(DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName]))->isNotEmpty();
    }

    private function foreignKeyExists(string $table, string $constraintName): bool
    {
        if (DB::getDriverName() === 'sqlite') {
            return false;
        }

        return collect(DB::select(
            'SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND CONSTRAINT_NAME = ?',
            [$table, $constraintName]
        ))->isNotEmpty();
    }
}
