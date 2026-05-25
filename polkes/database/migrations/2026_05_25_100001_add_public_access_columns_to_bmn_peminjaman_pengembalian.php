<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kolom akses publik (tanpa login) untuk modul Peminjaman & Pengembalian BMN.
 * Defensive: cek hasColumn / hasTable sebelum alter.
 */
class AddPublicAccessColumnsToBmnPeminjamanPengembalian extends Migration
{
    public function up(): void
    {
        $this->alterPeminjamanAsetBmn();
        $this->alterPengembalianAsetBmn();
    }

    public function down(): void
    {
        $this->revertPeminjamanAsetBmn();
        $this->revertPengembalianAsetBmn();
    }

    private function alterPeminjamanAsetBmn(): void
    {
        if (! Schema::hasTable('peminjaman_aset_bmn')) {
            return;
        }

        if (Schema::hasColumn('peminjaman_aset_bmn', 'user_id')) {
            $this->makeUserIdNullable('peminjaman_aset_bmn');
        }

        Schema::table('peminjaman_aset_bmn', function (Blueprint $table): void {
            if (! Schema::hasColumn('peminjaman_aset_bmn', 'public_nama')) {
                $table->string('public_nama', 255)->nullable()->after('jurusan');
            }
            if (! Schema::hasColumn('peminjaman_aset_bmn', 'public_no_wa')) {
                $table->string('public_no_wa', 30)->nullable()->after('public_nama');
            }
            if (! Schema::hasColumn('peminjaman_aset_bmn', 'public_email')) {
                $table->string('public_email', 255)->nullable()->after('public_no_wa');
            }
            if (! Schema::hasColumn('peminjaman_aset_bmn', 'public_email_verified_at')) {
                $table->timestamp('public_email_verified_at')->nullable()->after('public_email');
            }
            if (! Schema::hasColumn('peminjaman_aset_bmn', 'public_wa_verified_at')) {
                $table->timestamp('public_wa_verified_at')->nullable()->after('public_email_verified_at');
            }
            if (! Schema::hasColumn('peminjaman_aset_bmn', 'public_email_otp')) {
                $table->string('public_email_otp', 255)->nullable()->after('public_wa_verified_at');
            }
            if (! Schema::hasColumn('peminjaman_aset_bmn', 'public_wa_otp')) {
                $table->string('public_wa_otp', 255)->nullable()->after('public_email_otp');
            }
            if (! Schema::hasColumn('peminjaman_aset_bmn', 'public_otp_expires_at')) {
                $table->timestamp('public_otp_expires_at')->nullable()->after('public_wa_otp');
            }
            if (! Schema::hasColumn('peminjaman_aset_bmn', 'public_verified_at')) {
                $table->timestamp('public_verified_at')->nullable()->after('public_otp_expires_at');
            }
            if (! Schema::hasColumn('peminjaman_aset_bmn', 'public_token')) {
                $table->char('public_token', 64)->nullable()->after('public_verified_at');
            }
            if (! Schema::hasColumn('peminjaman_aset_bmn', 'public_otp_attempts')) {
                $table->unsignedTinyInteger('public_otp_attempts')->default(0)->after('public_token');
            }
            if (! Schema::hasColumn('peminjaman_aset_bmn', 'verification_status')) {
                $table->enum('verification_status', ['not_required', 'pending', 'verified', 'failed'])
                    ->default('not_required')
                    ->after('public_otp_attempts');
            }
            if (! Schema::hasColumn('peminjaman_aset_bmn', 'source')) {
                $table->enum('source', ['login', 'public'])->default('login')->after('verification_status');
            }
        });
    }

    private function alterPengembalianAsetBmn(): void
    {
        if (! Schema::hasTable('pengembalian_aset_bmn')) {
            return;
        }

        if (Schema::hasColumn('pengembalian_aset_bmn', 'diterima_by')) {
            $this->makeDiterimaByNullable('pengembalian_aset_bmn');
        } elseif (Schema::hasColumn('pengembalian_aset_bmn', 'received_by')) {
            $this->makeReceivedByNullable('pengembalian_aset_bmn');
        }

        Schema::table('pengembalian_aset_bmn', function (Blueprint $table): void {
            $fkColumn = Schema::hasColumn('pengembalian_aset_bmn', 'peminjaman_aset_bmn_id')
                ? 'peminjaman_aset_bmn_id'
                : 'peminjaman_id';

            if (! Schema::hasColumn('pengembalian_aset_bmn', 'public_nama')) {
                $table->string('public_nama', 255)->nullable()->after($fkColumn);
            }
            if (! Schema::hasColumn('pengembalian_aset_bmn', 'public_no_wa')) {
                $table->string('public_no_wa', 30)->nullable()->after('public_nama');
            }
            if (! Schema::hasColumn('pengembalian_aset_bmn', 'public_email')) {
                $table->string('public_email', 255)->nullable()->after('public_no_wa');
            }
            if (! Schema::hasColumn('pengembalian_aset_bmn', 'public_email_verified_at')) {
                $table->timestamp('public_email_verified_at')->nullable()->after('public_email');
            }
            if (! Schema::hasColumn('pengembalian_aset_bmn', 'public_wa_verified_at')) {
                $table->timestamp('public_wa_verified_at')->nullable()->after('public_email_verified_at');
            }
            if (! Schema::hasColumn('pengembalian_aset_bmn', 'public_email_otp')) {
                $table->string('public_email_otp', 255)->nullable()->after('public_wa_verified_at');
            }
            if (! Schema::hasColumn('pengembalian_aset_bmn', 'public_wa_otp')) {
                $table->string('public_wa_otp', 255)->nullable()->after('public_email_otp');
            }
            if (! Schema::hasColumn('pengembalian_aset_bmn', 'public_otp_expires_at')) {
                $table->timestamp('public_otp_expires_at')->nullable()->after('public_wa_otp');
            }
            if (! Schema::hasColumn('pengembalian_aset_bmn', 'public_verified_at')) {
                $table->timestamp('public_verified_at')->nullable()->after('public_otp_expires_at');
            }
            if (! Schema::hasColumn('pengembalian_aset_bmn', 'public_token')) {
                $table->char('public_token', 64)->nullable()->after('public_verified_at');
            }
            if (! Schema::hasColumn('pengembalian_aset_bmn', 'public_otp_attempts')) {
                $table->unsignedTinyInteger('public_otp_attempts')->default(0)->after('public_token');
            }
            if (! Schema::hasColumn('pengembalian_aset_bmn', 'verification_status')) {
                $table->enum('verification_status', ['not_required', 'pending', 'verified', 'failed'])
                    ->default('not_required')
                    ->after('public_otp_attempts');
            }
            if (! Schema::hasColumn('pengembalian_aset_bmn', 'source')) {
                $table->enum('source', ['login', 'public'])->default('login')->after('verification_status');
            }
            if (! Schema::hasColumn('pengembalian_aset_bmn', 'admin_verified_by')) {
                $table->unsignedBigInteger('admin_verified_by')->nullable()->after('source');
            }
            if (! Schema::hasColumn('pengembalian_aset_bmn', 'admin_verified_at')) {
                $table->timestamp('admin_verified_at')->nullable()->after('admin_verified_by');
            }
            if (! Schema::hasColumn('pengembalian_aset_bmn', 'admin_verification_note')) {
                $table->text('admin_verification_note')->nullable()->after('admin_verified_at');
            }
            if (! Schema::hasColumn('pengembalian_aset_bmn', 'admin_status')) {
                $table->enum('admin_status', ['pending', 'approved', 'rejected'])->nullable()->after('admin_verification_note');
            }
        });
    }

    private function makeUserIdNullable(string $table): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE `'.$table.'` MODIFY `user_id` BIGINT UNSIGNED NULL');
        } elseif ($driver === 'sqlite') {
            // SQLite tests: kolom sudah nullable jika tabel dibuat fresh; skip alter ketat.
        } else {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->unsignedBigInteger('user_id')->nullable()->change();
            });
        }
    }

    private function makeDiterimaByNullable(string $table): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE `'.$table.'` MODIFY `diterima_by` BIGINT UNSIGNED NULL');
        }
    }

    private function makeReceivedByNullable(string $table): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE `'.$table.'` MODIFY `received_by` BIGINT UNSIGNED NULL');
        }
    }

    private function revertPeminjamanAsetBmn(): void
    {
        if (! Schema::hasTable('peminjaman_aset_bmn')) {
            return;
        }

        Schema::table('peminjaman_aset_bmn', function (Blueprint $table): void {
            foreach ([
                'source', 'verification_status', 'public_otp_attempts', 'public_token',
                'public_verified_at', 'public_otp_expires_at', 'public_wa_otp', 'public_email_otp',
                'public_wa_verified_at', 'public_email_verified_at', 'public_email', 'public_no_wa', 'public_nama',
            ] as $col) {
                if (Schema::hasColumn('peminjaman_aset_bmn', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }

    private function revertPengembalianAsetBmn(): void
    {
        if (! Schema::hasTable('pengembalian_aset_bmn')) {
            return;
        }

        Schema::table('pengembalian_aset_bmn', function (Blueprint $table): void {
            foreach ([
                'admin_status', 'admin_verification_note', 'admin_verified_at', 'admin_verified_by',
                'source', 'verification_status', 'public_otp_attempts', 'public_token',
                'public_verified_at', 'public_otp_expires_at', 'public_wa_otp', 'public_email_otp',
                'public_wa_verified_at', 'public_email_verified_at', 'public_email', 'public_no_wa', 'public_nama',
            ] as $col) {
                if (Schema::hasColumn('pengembalian_aset_bmn', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
}
