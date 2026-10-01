<?php

declare(strict_types=1);

namespace App\Support\Persediaan;

use DateTimeInterface;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Parser tanggal Indonesia.
 * 06/08/2026 = 6 Agustus 2026, bukan 8 Juni 2026.
 * Tidak memakai strtotime untuk string dengan garis miring.
 */
final class PersediaanDateParser
{
    public static function toDateString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return self::valid($value->format('Y'), $value->format('m'), $value->format('d'));
        }

        if (is_int($value) || is_float($value) || (is_string($value) && is_numeric(trim($value)))) {
            return self::fromExcelSerial((float) $value);
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        $raw = preg_replace('/\s+\d{1,2}:\d{2}(:\d{2})?$/', '', $raw) ?? $raw;

        if (preg_match('/^(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})$/', $raw, $m)) {
            return self::valid($m[1], $m[2], $m[3]);
        }

        if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $raw, $m)) {
            return self::valid($m[3], $m[2], $m[1]);
        }

        return null;
    }

    public static function formatIndonesia(?string $iso): string
    {
        if ($iso === null || ! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $iso, $m)) {
            return '';
        }

        return sprintf('%02d/%02d/%04d', (int) $m[3], (int) $m[2], (int) $m[1]);
    }

    private static function fromExcelSerial(float $serial): ?string
    {
        if ($serial < 1 || $serial > 80000) {
            return null;
        }

        try {
            $dt = ExcelDate::excelToDateTimeObject($serial);
        } catch (\Throwable) {
            return null;
        }

        return self::valid($dt->format('Y'), $dt->format('m'), $dt->format('d'));
    }

    private static function valid(string $year, string $month, string $day): ?string
    {
        $y = (int) $year;
        $m = (int) $month;
        $d = (int) $day;

        if ($y < 1900 || $y > 2100 || ! checkdate($m, $d, $y)) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $y, $m, $d);
    }
}
