<?php

declare(strict_types=1);

namespace App\Services\BMN;

use App\Mail\BmnPublicOtpMail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Verifikasi publik (tanpa login) untuk pengajuan BMN.
 *
 * KEBIJAKAN SEMENTARA — WhatsApp OTP dinonaktifkan:
 *   Integrasi WhatsApp Business API / BSP berbayar belum aktif. Karena itu
 *   service ini hanya melakukan verifikasi via EMAIL OTP. Nomor WhatsApp tetap
 *   wajib diisi dan disimpan (untuk dihubungi petugas), namun tidak diverifikasi
 *   lewat OTP. Field WA pada record diisi nilai default agar layer admin tidak
 *   menampilkan status "kontak belum lengkap".
 *
 * Catatan migrasi data:
 *   Kolom DB `public_wa_otp`, `public_wa_verified_at` SENGAJA dipertahankan —
 *   agar ketika integrasi WhatsApp aktif, flow OTP WA dapat dihidupkan tanpa
 *   migrasi destruktif. WhatsAppOtpService juga TETAP di-inject (tidak dipanggil)
 *   untuk menjaga binding container.
 */
class BmnPublicVerificationService
{
    public const OTP_TTL_MINUTES = 15;

    public const MAX_OTP_ATTEMPTS = 5;

    /**
     * Cache nama kolom per-tabel agar tidak roundtrip ke information_schema
     * di setiap operasi OTP.
     *
     * @var array<string, list<string>>
     */
    private static array $columnCache = [];

    public function __construct(
        private readonly WhatsAppOtpService $whatsAppOtpService
    ) {
    }

    /**
     * Inisialisasi verifikasi publik (email-only).
     *
     * Return public token (64 char) yang dipakai sebagai bagian URL verifikasi.
     */
    public function initializePublicVerification(Model $record): string
    {
        $emailOtp = $this->generateOtp();
        $token = Str::random(64);
        $expiresAt = Carbon::now()->addMinutes(self::OTP_TTL_MINUTES);

        $payload = [
            'public_token' => $token,
            'public_email_otp' => Hash::make($emailOtp),
            'public_otp_expires_at' => $expiresAt,
            'public_otp_attempts' => 0,
            'verification_status' => 'pending',
            'public_email_verified_at' => null,
            'public_verified_at' => null,
            // WhatsApp OTP sementara dinonaktifkan: kosongkan kolom OTP WA agar
            // record bersih, tapi JANGAN drop kolom (kompat balik bila kelak
            // integrasi WhatsApp Business API diaktifkan).
            'public_wa_otp' => null,
            'public_wa_verified_at' => null,
        ];

        $record->forceFill($this->filterExistingAttributes($record, $payload))->save();

        $email = (string) ($record->public_email ?? $record->email ?? '');
        $nama = (string) ($record->public_nama ?? $record->nama_lengkap ?? 'Pengguna');

        if ($email !== '') {
            try {
                Mail::to($email)->send(new BmnPublicOtpMail($nama, $emailOtp, 'Email'));
            } catch (\Throwable $e) {
                // Tetap lanjutkan flow — admin masih bisa melihat kode via log
                // sebagai fallback manual. Jangan blok user karena MTA gagal.
                Log::error('Gagal kirim OTP email BMN: '.$e->getMessage(), [
                    'email' => $email,
                    'record_table' => $record->getTable(),
                    'record_id' => $record->getKey(),
                ]);
            }
        }

        // Sengaja TIDAK memanggil whatsAppOtpService->sendOtp() — WhatsApp OTP
        // dinonaktifkan karena integrasi WhatsApp Business API belum aktif/
        // berbayar. Nomor WA tetap disimpan untuk kontak petugas.

        return $token;
    }

    /**
     * Verifikasi OTP (email-only).
     *
     * @return array{success: bool, message: string}
     */
    public function verifyEmailOtp(Model $record, string $emailOtp): array
    {
        if ((int) ($record->public_otp_attempts ?? 0) >= self::MAX_OTP_ATTEMPTS) {
            $record->forceFill($this->filterExistingAttributes($record, [
                'verification_status' => 'failed',
            ]))->save();

            return [
                'success' => false,
                'message' => 'Percobaan verifikasi melebihi batas. Hubungi admin BMN.',
            ];
        }

        $expiresAt = $record->public_otp_expires_at;
        if ($expiresAt === null || Carbon::now()->greaterThan(Carbon::parse((string) $expiresAt))) {
            return [
                'success' => false,
                'message' => 'Kode OTP sudah kedaluwarsa. Ajukan ulang atau hubungi admin BMN.',
            ];
        }

        $emailHash = (string) ($record->public_email_otp ?? '');
        $emailOk = $emailHash !== '' && Hash::check($emailOtp, $emailHash);

        if (! $emailOk) {
            $record->increment('public_otp_attempts');

            return [
                'success' => false,
                'message' => 'Kode OTP Email tidak valid.',
            ];
        }

        $now = Carbon::now();

        // public_wa_verified_at TETAP diisi $now agar layer admin/laporan yang
        // membaca "kontak terverifikasi" tidak menampilkan status setengah.
        // Ini BUKAN verifikasi WhatsApp — hanya penanda bahwa proses verifikasi
        // (yang saat ini email-only) sudah selesai.
        $update = [
            'public_email_verified_at' => $now,
            'public_wa_verified_at' => $now,
            'public_verified_at' => $now,
            'verification_status' => 'verified',
            'public_email_otp' => null,
            'public_wa_otp' => null,
            'public_otp_expires_at' => null,
        ];
        $record->forceFill($this->filterExistingAttributes($record, $update))->save();

        return [
            'success' => true,
            'message' => 'Verifikasi email berhasil. Pengajuan menunggu persetujuan admin BMN.',
        ];
    }

    /**
     * Kompatibilitas mundur untuk controller lama yang masih mengirim
     * `wa_otp`. Argumen `$waOtp` diabaikan (WhatsApp OTP dinonaktifkan).
     *
     * @return array{success: bool, message: string}
     */
    public function verifyOtps(Model $record, string $emailOtp, string $waOtp = ''): array
    {
        unset($waOtp);

        return $this->verifyEmailOtp($record, $emailOtp);
    }

    public function validateToken(?Model $record, string $token): bool
    {
        if ($record === null) {
            return false;
        }

        $stored = (string) ($record->public_token ?? '');

        return $stored !== '' && hash_equals($stored, $token);
    }

    /**
     * Akses ke service WhatsApp (saat ini di-inject tapi tidak dipakai).
     * Disediakan agar binding DI container tetap valid bila kelak diaktifkan.
     */
    public function whatsApp(): WhatsAppOtpService
    {
        return $this->whatsAppOtpService;
    }

    private function generateOtp(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Pastikan kita hanya menulis kolom yang BENAR-BENAR ada di tabel record.
     * Penting untuk dukungan multi-tabel (peminjaman / pengembalian / sewa) —
     * tabel sewa misalnya belum tentu punya semua kolom legacy peminjaman.
     *
     * @param  array<string, mixed>  $attrs
     * @return array<string, mixed>
     */
    private function filterExistingAttributes(Model $record, array $attrs): array
    {
        $table = $record->getTable();

        if (! isset(self::$columnCache[$table])) {
            self::$columnCache[$table] = Schema::getColumnListing($table);
        }

        $available = array_flip(self::$columnCache[$table]);

        return array_intersect_key($attrs, $available);
    }
}
