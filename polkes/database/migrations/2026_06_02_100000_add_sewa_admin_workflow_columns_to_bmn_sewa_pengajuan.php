<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration aman/idempotent untuk menambah kolom workflow Admin Sewa BMN
 * (forwarding pembayaran ke Bu Indah + koordinasi Bu Retno/Pak Singgih +
 * metadata upload + public token untuk halaman cek status).
 *
 * Tidak mengubah kolom existing. Aman dijalankan berulang.
 */
class AddSewaAdminWorkflowColumnsToBmnSewaPengajuan extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bmn_sewa_pengajuan')) {
            return;
        }

        Schema::table('bmn_sewa_pengajuan', function (Blueprint $table): void {
            if (! Schema::hasColumn('bmn_sewa_pengajuan', 'payment_forwarded_by')) {
                $table->unsignedBigInteger('payment_forwarded_by')->nullable()->after('admin_payment_note');
            }
            if (! Schema::hasColumn('bmn_sewa_pengajuan', 'payment_forwarded_at')) {
                $table->timestamp('payment_forwarded_at')->nullable()->after('payment_forwarded_by');
            }
            if (! Schema::hasColumn('bmn_sewa_pengajuan', 'coordination_by')) {
                $table->unsignedBigInteger('coordination_by')->nullable()->after('coordination_status');
            }
            if (! Schema::hasColumn('bmn_sewa_pengajuan', 'coordination_at')) {
                $table->timestamp('coordination_at')->nullable()->after('coordination_by');
            }
            if (! Schema::hasColumn('bmn_sewa_pengajuan', 'coordination_note')) {
                $table->text('coordination_note')->nullable()->after('coordination_at');
            }
            if (! Schema::hasColumn('bmn_sewa_pengajuan', 'public_token')) {
                $table->string('public_token', 100)->nullable()->after('coordination_note');
            }
            if (! Schema::hasColumn('bmn_sewa_pengajuan', 'bukti_bayar_original_name')) {
                $table->string('bukti_bayar_original_name', 255)->nullable()->after('public_token');
            }
            if (! Schema::hasColumn('bmn_sewa_pengajuan', 'surat_resmi_original_name')) {
                $table->string('surat_resmi_original_name', 255)->nullable()->after('bukti_bayar_original_name');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('bmn_sewa_pengajuan')) {
            return;
        }

        Schema::table('bmn_sewa_pengajuan', function (Blueprint $table): void {
            foreach ([
                'surat_resmi_original_name',
                'bukti_bayar_original_name',
                'public_token',
                'coordination_note',
                'coordination_at',
                'coordination_by',
                'payment_forwarded_at',
                'payment_forwarded_by',
            ] as $col) {
                if (Schema::hasColumn('bmn_sewa_pengajuan', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
}
