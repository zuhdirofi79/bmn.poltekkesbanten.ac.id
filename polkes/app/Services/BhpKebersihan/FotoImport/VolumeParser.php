<?php

declare(strict_types=1);

namespace App\Services\BhpKebersihan\FotoImport;

final class VolumeParser
{
    private const SATUAN_PATTERN = 'jerigen|jerrigen|pack|pak|pcs|pc|buah|botol|bungkus|roll|karton|dus|liter|ltr|kg|gram|gr|unit|lembar|box|lusin|set';

    /**
     * @return array{jumlah: int|null, satuan: string, raw: string, needs_review: bool}
     */
    public static function split(mixed $jumlah, mixed $satuan = null): array
    {
        $satuanStr = self::clean((string) ($satuan ?? ''));
        $jumlahStr = is_numeric($jumlah) ? (string) $jumlah : self::clean((string) ($jumlah ?? ''));

        if ($satuanStr !== '' && is_numeric(str_replace(',', '.', $jumlahStr))) {
            return [
                'jumlah' => self::toInt($jumlahStr),
                'satuan' => self::stripParen($satuanStr),
                'raw' => trim($jumlahStr . ' ' . $satuanStr),
                'needs_review' => false,
            ];
        }

        $combined = trim($jumlahStr . ' ' . $satuanStr);
        $combined = preg_replace('/\s+/u', ' ', $combined) ?? $combined;

        if ($combined === '') {
            return ['jumlah' => null, 'satuan' => '', 'raw' => '', 'needs_review' => true];
        }

        if (preg_match('/^(\d{1,7}(?:[.,]\d{1,2})?)\s+(.+)$/u', $combined, $m) === 1) {
            return [
                'jumlah' => self::toInt($m[1]),
                'satuan' => self::stripParen(self::clean($m[2])),
                'raw' => $combined,
                'needs_review' => false,
            ];
        }

        $satuanRe = self::SATUAN_PATTERN;
        if (preg_match('/(\d{1,7}(?:[.,]\d{1,2})?)\s*(' . $satuanRe . ')\b(.*)$/iu', $combined, $m) === 1) {
            $satuanOut = self::clean($m[2] . ' ' . $m[3]);

            return [
                'jumlah' => self::toInt($m[1]),
                'satuan' => self::stripParen($satuanOut) ?: self::clean($m[2]),
                'raw' => $combined,
                'needs_review' => false,
            ];
        }

        if (preg_match('/^(\d{1,7})$/', $combined, $m) === 1) {
            return ['jumlah' => self::toInt($m[1]), 'satuan' => '', 'raw' => $combined, 'needs_review' => true];
        }

        return ['jumlah' => null, 'satuan' => self::stripParen($combined), 'raw' => $combined, 'needs_review' => true];
    }

    public static function toInt(string $value): int
    {
        $value = str_replace([' ', ','], ['', '.'], $value);
        if (str_contains($value, '.')) {
            $value = explode('.', $value, 2)[0];
        }

        return max(0, (int) $value);
    }

    public static function normalizeKey(string $nama, string $merk, string $satuan): string
    {
        return self::fold($nama) . '|' . self::fold($merk) . '|' . self::fold($satuan);
    }

    public static function fold(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return $value;
    }

    public static function clean(string $value): string
    {
        $value = str_replace(["\u{00A0}", "\t"], ' ', $value);
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
        $value = trim($value, " \t\n\r\0\x0B-|");

        return $value;
    }

    public static function stripParen(string $satuan): string
    {
        $satuan = trim(preg_replace('/\s*\([^)]*\)\s*/u', ' ', $satuan) ?? $satuan);
        $satuan = self::clean($satuan);

        return $satuan;
    }
}
