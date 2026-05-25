<?php

declare(strict_types=1);

namespace App\Services\BMN;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Placeholder/configurable WhatsApp OTP sender.
 * Jika provider belum dikonfigurasi, OTP hanya di-log (development).
 */
class WhatsAppOtpService
{
    public function sendOtp(string $phoneNumber, string $otp, string $context = 'BMN'): bool
    {
        $provider = (string) config('services.whatsapp.provider', 'log');
        $message = sprintf('[%s] Kode verifikasi Anda: %s. Berlaku 10 menit.', $context, $otp);

        if ($provider === '' || $provider === 'log') {
            Log::info('WhatsApp OTP (log-only)', [
                'to' => $phoneNumber,
                'otp' => $otp,
                'context' => $context,
            ]);

            return true;
        }

        $apiUrl = (string) config('services.whatsapp.api_url', '');
        $token = (string) config('services.whatsapp.token', '');

        if ($apiUrl === '' || $token === '') {
            Log::warning('WhatsApp OTP provider configured but api_url/token missing', [
                'to' => $phoneNumber,
                'context' => $context,
            ]);

            return false;
        }

        try {
            $response = Http::withToken($token)
                ->timeout(15)
                ->post($apiUrl, [
                    'to' => $phoneNumber,
                    'message' => $message,
                ]);

            if (! $response->successful()) {
                Log::warning('WhatsApp OTP API non-success', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('WhatsApp OTP send failed: '.$e->getMessage(), [
                'to' => $phoneNumber,
                'context' => $context,
            ]);

            return false;
        }
    }
}
