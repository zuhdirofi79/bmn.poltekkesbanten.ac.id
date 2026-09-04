<?php

declare(strict_types=1);

namespace App\Support\BhpKebersihan;

/**
 * Memisahkan FILESYSTEM PATH vs PUBLIC URL untuk unggahan BHP Kebersihan.
 *
 * Shared hosting (Hostinger): docroot = public_html, sementara Laravel
 * public_path() sering menunjuk ke laravel-bmn/public — file yang disimpan
 * di sana tidak bisa diakses browser lewat /uploads/...
 *
 * Resolusi root unggahan:
 *   1. BHP_KEBERSIHAN_UPLOADS_PATH / URL
 *   2. dirname(BMN_FOTO_BASE_PATH / URL)  → .../public_html/uploads
 *   3. public_path('uploads') / url('/uploads')
 */
final class PublicUploadPath
{
    public const BARCODE_REL_DIR = 'uploads/barcode/bhp-kebersihan';

    public const FOTO_REL_DIR = 'uploads/foto_bhp_kebersihan';

    public static function uploadsRoot(): string
    {
        $explicit = rtrim((string) (config('bhp_kebersihan.uploads_path') ?? ''), '/\\');
        if ($explicit !== '') {
            return $explicit;
        }

        $bmnFoto = rtrim((string) (config('bmn.foto_path') ?? ''), '/\\');
        if ($bmnFoto !== '') {
            return rtrim(str_replace('\\', '/', dirname($bmnFoto)), '/');
        }

        return rtrim(str_replace('\\', '/', (string) public_path('uploads')), '/');
    }

    public static function uploadsUrl(): string
    {
        $explicit = rtrim((string) (config('bhp_kebersihan.uploads_url') ?? ''), '/');
        if ($explicit !== '') {
            return $explicit;
        }

        $bmnFoto = rtrim((string) (config('bmn.foto_url') ?? ''), '/');
        if ($bmnFoto !== '') {
            return rtrim(dirname($bmnFoto), '/');
        }

        return rtrim((string) url('/uploads'), '/');
    }

    /**
     * Path relatif web yang aman (tanpa drive Windows, /home, /tmp, /storage/app).
     */
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
            if (str_contains($path, 'foto_bhp_kebersihan')) {
                return self::FOTO_REL_DIR . '/' . $base;
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

    /**
     * Salin file dari lokasi Laravel public/ ke disk publik (public_html) jika perlu.
     * Tidak meregenerate barcode — hanya memindahkan salinan fisik.
     */
    public static function ensureOnPublicDisk(string $relativePath): ?string
    {
        $relative = self::normalizeRelative($relativePath);
        if ($relative === null) {
            return null;
        }

        $target = self::absolute($relative);
        if (is_file($target)) {
            return $target;
        }

        $source = self::firstExistingFile($relative);
        if ($source === null || $source === $target) {
            return is_file($target) ? $target : null;
        }

        $dir = dirname($target);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if (@copy($source, $target) && is_file($target)) {
            return $target;
        }

        return is_file($source) ? $source : null;
    }

    public static function barcodeDir(): string
    {
        return self::absolute(self::BARCODE_REL_DIR);
    }

    public static function fotoDir(): string
    {
        return self::absolute(self::FOTO_REL_DIR);
    }

    public static function ensureDir(string $absoluteDir): void
    {
        if (! is_dir($absoluteDir)) {
            mkdir($absoluteDir, 0755, true);
        }
    }

    /**
     * Document root tempat browser membaca /img/logo.png.
     * Pada Hostinger ini public_html, bukan folder public Laravel.
     */
    public static function documentRoot(): string
    {
        return rtrim(str_replace('\\', '/', dirname(self::uploadsRoot())), '/');
    }

    public static function isDeprecatedLogoFile(string $path): bool
    {
        $base = strtolower(basename(str_replace('\\', '/', $path)));

        return in_array($base, ['logo1.png', 'fav1.png', 'fav.png'], true);
    }

    /**
     * Kandidat path logo resmi barcode: hanya logo.png (Kemenkes Poltekkes Banten).
     * Urutan: env eksplisit → public_html/img/logo.png → public/img/logo.png.
     *
     * @return list<string>
     */
    public static function officialLogoCandidatePaths(): array
    {
        $paths = [];

        $explicit = trim((string) (config('bhp_kebersihan.barcode_logo_path') ?? ''));
        if ($explicit !== '') {
            $paths[] = $explicit;
        }

        $paths[] = self::documentRoot() . '/img/logo.png';
        $paths[] = rtrim(str_replace('\\', '/', (string) public_path('img/logo.png')), '/');
        $paths[] = rtrim(str_replace('\\', '/', dirname((string) public_path())), '/') . '/img/logo.png';
        $paths[] = rtrim(str_replace('\\', '/', dirname((string) base_path())), '/') . '/img/logo.png';

        $normalized = [];
        foreach ($paths as $path) {
            $path = rtrim(str_replace('\\', '/', $path), '/');
            if ($path === '' || in_array($path, $normalized, true)) {
                continue;
            }
            $normalized[] = $path;
        }

        return $normalized;
    }

    /**
     * Path filesystem logo resmi untuk compositing QR.
     * Tidak pernah memakai logo1.png / fav1.png / fav.png.
     */
    public static function officialLogoAbsolutePath(): string
    {
        foreach (self::officialLogoCandidatePaths() as $path) {
            if (self::isDeprecatedLogoFile($path)) {
                continue;
            }
            if (strtolower(basename($path)) !== 'logo.png') {
                continue;
            }
            if (is_file($path) && (int) filesize($path) > 100) {
                return $path;
            }
        }

        throw new \RuntimeException('Logo Poltekkes Banten terbaru tidak ditemukan.');
    }

    private static function stripUploadsPrefix(string $relative): string
    {
        if (str_starts_with($relative, 'uploads/')) {
            return substr($relative, strlen('uploads/'));
        }

        return $relative;
    }
}
