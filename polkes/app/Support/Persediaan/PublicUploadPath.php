<?php

declare(strict_types=1);

namespace App\Support\Persediaan;

/**
 * FILESYSTEM PATH vs PUBLIC URL untuk unggahan Persediaan (QR/barcode).
 * Pola sama dengan BHP, tanpa coupling ke modul BHP.
 */
final class PublicUploadPath
{
    public const QR_AKSES_REL_DIR = 'uploads/persediaan/qr/akses';

    public const QR_RAK_REL_DIR = 'uploads/persediaan/qr/rak';

    public const BARCODE_REL_DIR = 'uploads/persediaan/barcode/barang';

    public static function uploadsRoot(): string
    {
        $explicit = rtrim((string) (config('persediaan.uploads_path') ?? ''), '/\\');
        if ($explicit !== '') {
            return str_replace('\\', '/', $explicit);
        }

        $bmnFoto = rtrim((string) (config('bmn.foto_path') ?? ''), '/\\');
        if ($bmnFoto !== '') {
            return rtrim(str_replace('\\', '/', dirname($bmnFoto)), '/');
        }

        return rtrim(str_replace('\\', '/', (string) public_path('uploads')), '/');
    }

    public static function uploadsUrl(): string
    {
        $explicit = rtrim((string) (config('persediaan.uploads_url') ?? ''), '/');
        if ($explicit !== '') {
            return $explicit;
        }

        $bmnFoto = rtrim((string) (config('bmn.foto_url') ?? ''), '/');
        if ($bmnFoto !== '') {
            return rtrim(dirname($bmnFoto), '/');
        }

        return rtrim((string) url('/uploads'), '/');
    }

    public static function normalizeRelative(?string $path): ?string
    {
        if ($path === null) {
            return null;
        }

        $path = trim(str_replace('\\', '/', $path));
        if ($path === '') {
            return null;
        }

        if (preg_match('#^[A-Za-z]:/#', $path) === 1
            || str_starts_with($path, '/home/')
            || str_starts_with($path, '/tmp/')
            || str_contains($path, '/storage/app/')
            || str_starts_with($path, 'storage/app/')
            || str_starts_with($path, 'storage/')) {
            $base = basename($path);
            if ($base === '' || $base === '.' || $base === '..') {
                return null;
            }

            return self::BARCODE_REL_DIR . '/' . $base;
        }

        $path = ltrim($path, '/');
        if (str_contains($path, '..')) {
            return null;
        }

        return $path;
    }

    public static function absolute(string $relativePath): string
    {
        $relative = self::normalizeRelative($relativePath) ?? ltrim(str_replace('\\', '/', $relativePath), '/');
        $relative = self::stripUploadsPrefix($relative);

        return self::uploadsRoot() . '/' . $relative;
    }

    public static function publicUrl(string $relativePath): string
    {
        $relative = self::normalizeRelative($relativePath);
        if ($relative === null) {
            return '';
        }

        $relative = self::stripUploadsPrefix($relative);

        return self::uploadsUrl() . '/' . $relative;
    }

    /**
     * @return list<string>
     */
    public static function candidateAbsolutePaths(string $relativePath): array
    {
        $relative = self::normalizeRelative($relativePath);
        if ($relative === null) {
            return [];
        }

        $configured = self::absolute($relative);
        $legacy = rtrim(str_replace('\\', '/', (string) public_path($relative)), '/');

        $paths = [$configured];
        if ($legacy !== $configured) {
            $paths[] = $legacy;
        }

        return array_values(array_unique($paths));
    }

    public static function firstExistingFile(string $relativePath): ?string
    {
        foreach (self::candidateAbsolutePaths($relativePath) as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    public static function ensureDir(string $absoluteDir): void
    {
        if (! is_dir($absoluteDir)) {
            if (! @mkdir($absoluteDir, 0755, true) && ! is_dir($absoluteDir)) {
                throw new \RuntimeException('Gagal membuat folder unggahan: ' . $absoluteDir);
            }
        }
    }

    public static function qrAksesDir(): string
    {
        return self::absolute(self::QR_AKSES_REL_DIR);
    }

    public static function qrRakDir(): string
    {
        return self::absolute(self::QR_RAK_REL_DIR);
    }

    public static function barcodeDir(): string
    {
        return self::absolute(self::BARCODE_REL_DIR);
    }

    private static function stripUploadsPrefix(string $relative): string
    {
        if (str_starts_with($relative, 'uploads/')) {
            return substr($relative, strlen('uploads/'));
        }

        return $relative;
    }
}
