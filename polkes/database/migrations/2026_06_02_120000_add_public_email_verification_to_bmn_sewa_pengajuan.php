<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration aman/idempotent untuk menambah kolom verifikasi EMAIL publik
 * pada bmn_sewa_pengajuan.
 *
 * Konteks:
 *   - Pengajuan sewa publik (tanpa login) wajib memverifikasi email pemohon
 *     untuk memastikan kontak valid.
 *   - WhatsApp OTP sementara dinonaktifkan karena integrasi WhatsApp Business
 *     API belum aktif/berbayar. Kolom WA verification disediakan agar bila
 *     kelak integrasi WA aktif tidak perlu migrasi destruktif.
 *
 * Tidak mengubah/menghapus kolom existing. Aman dijalankan berulang.
 */
class AddPublicEmailVerificationToBmnSewaPengajuan extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bmn_sewa_pengajuan')) {
            return;
        }

        Schema::table('bmn_sewa_pengajuan', function (Blueprint $table): void {
            if (! Schema::hasColumn('bmn_sewa_pengajuan', 'public_email_otp')) {
                $table->string('public_email_otp', 255)->nullable()->after('public_token');
            }
            if (! Schema::hasColumn('bmn_sewa_pengajuan', 'public_wa_otp')) {
                // Disediakan untuk masa depan (WA OTP). Saat ini selalu null.
                $table->string('public_wa_otp', 255)->nullable()->after('public_email_otp');
            }
            if (! Schema::hasColumn('bmn_sewa_pengajuan', 'public_otp_expires_at')) {
                $table->timestamp('public_otp_expires_at')->nullable()->after('public_wa_otp');
            }
            if (! Schema::hasColumn('bmn_sewa_pengajuan', 'public_otp_attempts')) {
                $table->unsignedSmallInteger('public_otp_attempts')->default(0)->after('public_otp_expires_at');
            }
            if (! Schema::hasColumn('bmn_sewa_pengajuan', 'public_email_verified_at')) {
                $table->timestamp('public_email_verified_at')->nullable()->after('public_otp_attempts');
            }
            if (! Schema::hasColumn('bmn_sewa_pengajuan', 'public_wa_verified_at')) {
                $table->timestamp('public_wa_verified_at')->nullable()->after('public_email_verified_at');
            }
            if (! Schema::hasColumn('bmn_sewa_pengajuan', 'public_verified_at')) {
                $table->timestamp('public_verified_at')->nullable()->after('public_wa_verified_at');
            }
            if (! Schema::hasColumn('bmn_sewa_pengajuan', 'verification_status')) {
                // Sengaja pakai string (bukan enum) untuk fleksibilitas.
                // Nilai konsensus: 'pending' | 'verified' | 'failed' | 'not_required'.
                $table->string('verification_status', 20)->default('pending')->after('public_verified_at');
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
                'verification_status',
                'public_verified_at',
                'public_wa_verified_at',
                'public_email_verified_at',
                'public_otp_attempts',
                'public_otp_expires_at',
                'public_wa_otp',
                'public_email_otp',
            ] as $col) {
                if (Schema::hasColumn('bmn_sewa_pengajuan', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
}
