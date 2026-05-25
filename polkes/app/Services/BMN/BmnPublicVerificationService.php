<?php

declare(strict_types=1);

namespace App\Services\BMN;

use App\Mail\BmnPublicOtpMail;
use App\Models\BMN\PeminjamanAsetBmn;
use App\Models\BMN\PengembalianAsetBmn;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class BmnPublicVerificationService
{
    public const OTP_TTL_MINUTES = 10;

    public const MAX_OTP_ATTEMPTS = 5;

    public function __construct(
        private readonly WhatsAppOtpService $whatsAppOtpService
    ) {
    }

    /**
     * @param  PeminjamanAsetBmn|PengembalianAsetBmn  $record
     */
    public function initializePublicVerification(Model $record): string
    {
        $emailOtp = $this->generateOtp();
        $waOtp = $this->generateOtp();
        $token = Str::random(64);
        $expiresAt = Carbon::now()->addMinutes(self::OTP_TTL_MINUTES);

        $record->forceFill([
            'public_token' => $token,
            'public_email_otp' => Hash::make($emailOtp),
            'public_wa_otp' => Hash::make($waOtp),
            'public_otp_expires_at' => $expiresAt,
            'public_otp_attempts' => 0,
            'verification_status' => 'pending',
            'public_email_verified_at' => null,
            'public_wa_verified_at' => null,
            'public_verified_at' => null,
        ])->save();

        $email = (string) ($record->public_email ?? '');
        $wa = (string) ($record->public_no_wa ?? '');
        $nama = (string) ($record->public_nama ?? 'Pengguna');

        if ($email !== '') {
            try {
                Mail::to($email)->send(new BmnPublicOtpMail($nama, $emailOtp, 'Email'));
            } catch (\Throwable $e) {
                Log::error('Gagal kirim OTP email BMN: '.$e->getMessage(), ['email' => $email]);
            }
        }

        if ($wa !== '') {
            $this->whatsAppOtpService->sendOtp($wa, $waOtp, 'BMN');
        }

        return $token;
    }

    /**
     * @param  PeminjamanAsetBmn|PengembalianAsetBmn  $record
     * @return array{success: bool, message: string}
     */
    public function verifyOtps(Model $record, string $emailOtp, string $waOtp): array
    {
        if ((int) ($record->public_otp_attempts ?? 0) >= self::MAX_OTP_ATTEMPTS) {
            $record->update(['verification_status' => 'failed']);

            return [
                'success' => false,
                'message' => 'Percobaan verifikasi melebihi batas. Hubungi admin BMN.',
            ];
        }

        $expiresAt = $record->public_otp_expires_at;
        if ($expiresAt === null || Carbon::now()->greaterThan($expiresAt)) {
            return [
                'success' => false,
                'message' => 'Kode OTP sudah kedaluwarsa. Ajukan ulang atau hubungi admin BMN.',
            ];
        }

        $emailHash = (string) ($record->public_email_otp ?? '');
        $waHash = (string) ($record->public_wa_otp ?? '');

        $emailOk = $emailHash !== '' && Hash::check($emailOtp, $emailHash);
        $waOk = $waHash !== '' && Hash::check($waOtp, $waHash);

        if (! $emailOk || ! $waOk) {
            $record->increment('public_otp_attempts');

            return [
                'success' => false,
                'message' => 'Kode OTP Email atau WhatsApp tidak valid.',
            ];
        }

        $now = Carbon::now();
        $record->update([
            'public_email_verified_at' => $now,
            'public_wa_verified_at' => $now,
            'public_verified_at' => $now,
            'verification_status' => 'verified',
            'public_email_otp' => null,
            'public_wa_otp' => null,
            'public_otp_expires_at' => null,
        ]);

        return [
            'success' => true,
            'message' => 'Verifikasi kontak berhasil. Pengajuan menunggu persetujuan admin BMN.',
        ];
    }

    /**
     * @param  PeminjamanAsetBmn|PengembalianAsetBmn|null  $record
     */
    public function validateToken(?Model $record, string $token): bool
    {
        if ($record === null) {
            return false;
        }

        $stored = (string) ($record->public_token ?? '');

        return $stored !== '' && hash_equals($stored, $token);
    }

    private function generateOtp(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }
}
