<?php

declare(strict_types=1);

namespace App\Services\BhpKebersihan;

use App\Models\BhpKebersihan\BhpKebersihanBarang;
use App\Support\BhpKebersihan\PublicUploadPath;
use Illuminate\Support\Str;

class BarcodeService
{
    public const UPLOAD_REL_DIR = PublicUploadPath::BARCODE_REL_DIR;

    public function __construct(private BarcodeLabelRenderer $labels)
    {
    }

    public function generateToken(): string
    {
        do {
            $token = Str::lower(Str::random(32));
        } while (BhpKebersihanBarang::where('barcode_token', $token)->exists());

        return $token;
    }

    public function publicUrl(string $token): string
    {
        return url('/public/bhp-kebersihan/barang/' . $token);
    }

    public function uploadDir(): string
    {
        return PublicUploadPath::barcodeDir();
    }

    public function sanitizeKode(string $kode): string
    {
        $kode = trim($kode);
        $kode = str_replace([' ', '/', '\\'], '-', $kode);
        $kode = preg_replace('/[^A-Za-z0-9\-_]/', '', $kode) ?? '';

        return $kode !== '' ? $kode : 'barang';
    }

    public function filename(BhpKebersihanBarang $barang): string
    {
        return 'bhp-' . $barang->id . '-' . $this->sanitizeKode($barang->kode_barang) . '.png';
    }

    public function relativePath(BhpKebersihanBarang $barang): string
    {
        return self::UPLOAD_REL_DIR . '/' . $this->filename($barang);
    }

    /**
     * URL publik gambar barcode (bukan path filesystem).
     */
    public function assetUrl(?string $relativePath): ?string
    {
        $relative = PublicUploadPath::normalizeRelative($relativePath);
        if ($relative === null) {
            return null;
        }

        $url = PublicUploadPath::publicUrl($relative);

        return $url !== '' ? $url : null;
    }

    /**
     * Cari file fisik tanpa generate QR baru.
     * Urutan: barcode_path → path dari id+kode → glob token di folder barcode.
     */
    public function resolveRelativePath(BhpKebersihanBarang $barang): ?string
    {
        $candidates = [];

        $fromDb = PublicUploadPath::normalizeRelative($barang->barcode_path);
        if ($fromDb !== null) {
            $candidates[] = $fromDb;
        }

        if ($barang->id) {
            $candidates[] = $this->relativePath($barang);
        }

        $candidates = array_values(array_unique($candidates));

        foreach ($candidates as $relative) {
            PublicUploadPath::ensureOnPublicDisk($relative);
            if (PublicUploadPath::firstExistingFile($relative) !== null) {
                return $relative;
            }
        }

        if ($barang->barcode_token) {
            $found = $this->findFileByTokenHint($barang);
            if ($found !== null) {
                PublicUploadPath::ensureOnPublicDisk($found);

                return $found;
            }
        }

        return null;
    }

    public function hasValidFile(BhpKebersihanBarang $barang): bool
    {
        return $this->resolveRelativePath($barang) !== null;
    }

    /**
     * File label horizontal sudah ada. Hanya diagnostik — Generate Semua
     * tetap meregenerate meski method ini true.
     */
    public function hasNewLabelFile(BhpKebersihanBarang $barang): bool
    {
        $absolute = $this->absoluteFilePath($barang);
        if ($absolute === null || ! is_file($absolute)) {
            return false;
        }

        $info = @getimagesize($absolute);
        if ($info === false || ($info[0] ?? 0) < 200 || ($info[1] ?? 0) < 100) {
            return false;
        }

        return $info[0] >= (int) ($info[1] * 1.3);
    }

    public function fileUrl(BhpKebersihanBarang $barang): ?string
    {
        $relative = $this->resolveRelativePath($barang);
        if ($relative === null) {
            return null;
        }

        $url = $this->assetUrl($relative);
        if ($url === null || $url === '') {
            return null;
        }

        $absolute = PublicUploadPath::firstExistingFile($relative);
        if ($absolute !== null) {
            $mtime = @filemtime($absolute);
            if ($mtime) {
                $url .= (str_contains($url, '?') ? '&' : '?') . 't=' . $mtime;
            }
        }

        return $url;
    }

    public function absoluteFilePath(BhpKebersihanBarang $barang): ?string
    {
        $relative = $this->resolveRelativePath($barang);
        if ($relative === null) {
            return null;
        }

        return PublicUploadPath::firstExistingFile($relative);
    }

    /**
     * @return array<string,mixed>
     */
    public function diagnose(BhpKebersihanBarang $barang): array
    {
        $relative = $this->resolveRelativePath($barang);
        $absolute = $relative !== null ? PublicUploadPath::firstExistingFile($relative) : null;

        return [
            'barcode_token' => $barang->barcode_token,
            'barcode_path' => $barang->barcode_path,
            'resolved_relative' => $relative,
            'physical_file_path' => $absolute,
            'file_exists' => $absolute !== null && is_file($absolute),
            'public_url' => $relative !== null ? $this->assetUrl($relative) : null,
            'scan_url' => $barang->barcode_token ? $this->publicUrl($barang->barcode_token) : null,
            'uploads_root' => PublicUploadPath::uploadsRoot(),
            'uploads_url' => PublicUploadPath::uploadsUrl(),
        ];
    }

    /**
     * Generate label QR PNG (encode URL publik detail barang) dan simpan ke disk publik.
     * Tidak mengganti barcode_token yang sudah ada. File lama dipertahankan sampai PNG baru valid.
     */
    public function generateAndSave(BhpKebersihanBarang $barang, int $size = 400): string
    {
        unset($size);

        if ($barang->barcode_token === null || $barang->barcode_token === '') {
            $barang->barcode_token = $this->generateToken();
            $barang->save();
        }

        $dir = $this->uploadDir();
        PublicUploadPath::ensureDir($dir);

        $relativePath = $this->relativePath($barang);
        $fullPath = PublicUploadPath::absolute($relativePath);

        $targetUrl = $this->publicUrl($barang->barcode_token);
        $png = $this->labels->render(
            $targetUrl,
            (string) $barang->nama_barang,
            (string) $barang->kode_barang
        );

        $tmpPath = $fullPath . '.tmp-' . bin2hex(random_bytes(4));
        if (file_put_contents($tmpPath, $png) === false) {
            throw new \RuntimeException('Gagal menyimpan file barcode sementara ke ' . $relativePath);
        }

        $info = @getimagesize($tmpPath);
        if ($info === false || ($info[0] ?? 0) < 200 || ($info[1] ?? 0) < 100) {
            @unlink($tmpPath);
            throw new \RuntimeException('File barcode hasil generate tidak valid.');
        }

        $this->replaceFileKeepingOldOnFailure($tmpPath, $fullPath);

        $oldRelative = PublicUploadPath::normalizeRelative($barang->barcode_path);
        if ($oldRelative !== null && $oldRelative !== $relativePath) {
            foreach (PublicUploadPath::candidateAbsolutePaths($oldRelative) as $oldFile) {
                if (is_file($oldFile) && $oldFile !== $fullPath) {
                    @unlink($oldFile);
                }
            }
        }

        $barang->barcode_path = $relativePath;
        $barang->save();

        return $relativePath;
    }

    private function replaceFileKeepingOldOnFailure(string $tmpPath, string $fullPath): void
    {
        $replaced = false;
        if (is_file($fullPath)) {
            $replaced = @copy($tmpPath, $fullPath) && is_file($fullPath);
        } else {
            $replaced = @rename($tmpPath, $fullPath) && is_file($fullPath);
            if (! $replaced) {
                $replaced = @copy($tmpPath, $fullPath) && is_file($fullPath);
            }
        }

        $tmpReal = is_file($tmpPath) ? realpath($tmpPath) : false;
        $fullReal = is_file($fullPath) ? realpath($fullPath) : false;
        if ($tmpReal !== false && $tmpReal !== $fullReal) {
            @unlink($tmpPath);
        }

        if (! $replaced || ! is_file($fullPath)) {
            throw new \RuntimeException('Gagal menimpa file barcode. File lama tetap dipertahankan.');
        }
    }

    private function findFileByTokenHint(BhpKebersihanBarang $barang): ?string
    {
        if (! $barang->id) {
            return null;
        }

        $expected = $this->filename($barang);
        foreach ([PublicUploadPath::barcodeDir(), public_path(self::UPLOAD_REL_DIR)] as $dir) {
            $dir = rtrim(str_replace('\\', '/', $dir), '/');
            if (! is_dir($dir)) {
                continue;
            }
            $path = $dir . '/' . $expected;
            if (is_file($path)) {
                return self::UPLOAD_REL_DIR . '/' . $expected;
            }
        }

        return null;
    }
}
