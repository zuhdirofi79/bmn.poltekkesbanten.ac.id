<?php

declare(strict_types=1);

namespace App\Services\BMN;

/**
 * Normalisasi nomor WhatsApp Indonesia ke format 62xxxxxxxx.
 */
class BmnWaNumberNormalizer
{
    public static function normalize(?string $raw): string
    {
        $digits = preg_replace('/\D+/', '', (string) $raw) ?? '';

        if ($digits === '') {
            return '';
        }

        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        }

        if (str_starts_with($digits, '8')) {
            $digits = '62'.$digits;
        }

        return $digits;
    }
}
