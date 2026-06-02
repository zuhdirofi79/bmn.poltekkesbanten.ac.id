<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kolom pengajuan peminjaman internal publik + screening Rumah Tangga.
 */
class AddPublicInternalScreeningToPeminjamanAsetBmn extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('peminjaman_aset_bmn')) {
            return;
        }

        $this->extendApprovalStageEnum();

        Schema::table('peminjaman_aset_bmn', function (Blueprint $table): void {
            if (! Schema::hasColumn('peminjaman_aset_bmn', 'requested_barang')) {
                $table->text('requested_barang')->nullable()->after('tujuan_peminjaman');
            }
            if (! Schema::hasColumn('peminjaman_aset_bmn', 'screened_by')) {
                $table->unsignedBigInteger('screened_by')->nullable()->after('final_report_note');
            }
            if (! Schema::hasColumn('peminjaman_aset_bmn', 'screened_at')) {
                $table->timestamp('screened_at')->nullable()->after('screened_by');
            }
            if (! Schema::hasColumn('peminjaman_aset_bmn', 'screening_status')) {
                $table->enum('screening_status', ['pending', 'tersedia', 'tidak_tersedia', 'perlu_konfirmasi'])
                    ->default('pending')
                    ->after('screened_at');
            }
            if (! Schema::hasColumn('peminjaman_aset_bmn', 'screening_note')) {
                $table->text('screening_note')->nullable()->after('screening_status');
            }
            if (! Schema::hasColumn('peminjaman_aset_bmn', 'kondisi_foto_path')) {
                $table->string('kondisi_foto_path', 255)->nullable()->after('screening_note');
            }
            if (! Schema::hasColumn('peminjaman_aset_bmn', 'kondisi_foto_original_name')) {
                $table->string('kondisi_foto_original_name', 255)->nullable()->after('kondisi_foto_path');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('peminjaman_aset_bmn')) {
            return;
        }

        Schema::table('peminjaman_aset_bmn', function (Blueprint $table): void {
            foreach ([
                'kondisi_foto_original_name',
                'kondisi_foto_path',
                'screening_note',
                'screening_status',
                'screened_at',
                'screened_by',
                'requested_barang',
            ] as $col) {
                if (Schema::hasColumn('peminjaman_aset_bmn', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }

    private function extendApprovalStageEnum(): void
    {
        if (! Schema::hasColumn('peminjaman_aset_bmn', 'approval_stage')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE `peminjaman_aset_bmn` MODIFY `approval_stage` ENUM(
                'verifikator_1',
                'verifikator_2',
                'final_report',
                'completed',
                'rejected',
                'screening_rumah_tangga',
                'final_hendri'
            ) DEFAULT 'verifikator_1'");
        } elseif ($driver === 'sqlite') {
            // SQLite tests: kolom sudah fleksibel sebagai string.
        }
    }
}
