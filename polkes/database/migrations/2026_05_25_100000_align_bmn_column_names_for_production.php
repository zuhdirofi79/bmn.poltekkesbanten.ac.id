<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Selaraskan nama kolom BMN dengan skema production (alias legacy → utama).
 * Defensive: hanya menambah kolom yang belum ada, lalu backfill dari alias.
 */
class AlignBmnColumnNamesForProduction extends Migration
{
    public function up(): void
    {
        $this->alignPeminjamanHeader();
        $this->alignPeminjamanDetail();
        $this->alignPengembalian();
    }

    public function down(): void
    {
        // Non-destructive alignment; no rollback.
    }

    private function alignPeminjamanHeader(): void
    {
        if (! Schema::hasTable('peminjaman_aset_bmn')) {
            return;
        }

        Schema::table('peminjaman_aset_bmn', function (Blueprint $table): void {
            if (! Schema::hasColumn('peminjaman_aset_bmn', 'kode_peminjaman')
                && Schema::hasColumn('peminjaman_aset_bmn', 'kode_transaksi')) {
                $table->string('kode_peminjaman', 32)->nullable()->after('id');
            }
            if (! Schema::hasColumn('peminjaman_aset_bmn', 'rejected_by')
                && Schema::hasColumn('peminjaman_aset_bmn', 'alasan_reject')) {
                $table->unsignedBigInteger('rejected_by')->nullable();
            }
            if (! Schema::hasColumn('peminjaman_aset_bmn', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable();
            }
        });

        if (Schema::hasColumn('peminjaman_aset_bmn', 'kode_peminjaman')
            && Schema::hasColumn('peminjaman_aset_bmn', 'kode_transaksi')) {
            DB::table('peminjaman_aset_bmn')
                ->whereNull('kode_peminjaman')
                ->update(['kode_peminjaman' => DB::raw('kode_transaksi')]);
        }
    }

    private function alignPeminjamanDetail(): void
    {
        if (! Schema::hasTable('peminjaman_aset_bmn_detail')) {
            return;
        }

        Schema::table('peminjaman_aset_bmn_detail', function (Blueprint $table): void {
            if (! Schema::hasColumn('peminjaman_aset_bmn_detail', 'peminjaman_aset_bmn_id')
                && Schema::hasColumn('peminjaman_aset_bmn_detail', 'peminjaman_id')) {
                $table->unsignedBigInteger('peminjaman_aset_bmn_id')->nullable()->after('id');
            }
            if (! Schema::hasColumn('peminjaman_aset_bmn_detail', 'aset_bmn_id')
                && Schema::hasColumn('peminjaman_aset_bmn_detail', 'aset_id')) {
                $table->unsignedBigInteger('aset_bmn_id')->nullable()->after('peminjaman_id');
            }
            if (! Schema::hasColumn('peminjaman_aset_bmn_detail', 'kondisi_awal')) {
                $table->string('kondisi_awal', 50)->nullable();
            }
            if (! Schema::hasColumn('peminjaman_aset_bmn_detail', 'kondisi_kembali')) {
                $table->string('kondisi_kembali', 50)->nullable();
            }
            if (! Schema::hasColumn('peminjaman_aset_bmn_detail', 'status_item')) {
                $table->string('status_item', 50)->nullable();
            }
            if (! Schema::hasColumn('peminjaman_aset_bmn_detail', 'catatan')) {
                $table->text('catatan')->nullable();
            }
        });

        if (Schema::hasColumn('peminjaman_aset_bmn_detail', 'peminjaman_aset_bmn_id')
            && Schema::hasColumn('peminjaman_aset_bmn_detail', 'peminjaman_id')) {
            DB::table('peminjaman_aset_bmn_detail')
                ->whereNull('peminjaman_aset_bmn_id')
                ->update(['peminjaman_aset_bmn_id' => DB::raw('peminjaman_id')]);
        }

        if (Schema::hasColumn('peminjaman_aset_bmn_detail', 'aset_bmn_id')
            && Schema::hasColumn('peminjaman_aset_bmn_detail', 'aset_id')) {
            DB::table('peminjaman_aset_bmn_detail')
                ->whereNull('aset_bmn_id')
                ->update(['aset_bmn_id' => DB::raw('aset_id')]);
        }

        if (Schema::hasColumn('peminjaman_aset_bmn_detail', 'kondisi_awal')
            && Schema::hasColumn('peminjaman_aset_bmn_detail', 'kondisi_pinjam')) {
            DB::table('peminjaman_aset_bmn_detail')
                ->whereNull('kondisi_awal')
                ->update(['kondisi_awal' => DB::raw('kondisi_pinjam')]);
        }
    }

    private function alignPengembalian(): void
    {
        if (! Schema::hasTable('pengembalian_aset_bmn')) {
            return;
        }

        Schema::table('pengembalian_aset_bmn', function (Blueprint $table): void {
            if (! Schema::hasColumn('pengembalian_aset_bmn', 'peminjaman_aset_bmn_id')
                && Schema::hasColumn('pengembalian_aset_bmn', 'peminjaman_id')) {
                $table->unsignedBigInteger('peminjaman_aset_bmn_id')->nullable()->after('id');
            }
            if (! Schema::hasColumn('pengembalian_aset_bmn', 'diterima_by')
                && Schema::hasColumn('pengembalian_aset_bmn', 'received_by')) {
                $table->unsignedBigInteger('diterima_by')->nullable()->after('peminjaman_id');
            }
        });

        if (Schema::hasColumn('pengembalian_aset_bmn', 'peminjaman_aset_bmn_id')
            && Schema::hasColumn('pengembalian_aset_bmn', 'peminjaman_id')) {
            DB::table('pengembalian_aset_bmn')
                ->whereNull('peminjaman_aset_bmn_id')
                ->update(['peminjaman_aset_bmn_id' => DB::raw('peminjaman_id')]);
        }

        if (Schema::hasColumn('pengembalian_aset_bmn', 'diterima_by')
            && Schema::hasColumn('pengembalian_aset_bmn', 'received_by')) {
            DB::table('pengembalian_aset_bmn')
                ->whereNull('diterima_by')
                ->update(['diterima_by' => DB::raw('received_by')]);
        }
    }
}
