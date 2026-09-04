<?php

declare(strict_types=1);

namespace App\Services\BhpKebersihan;

use App\Models\BhpKebersihan\BhpKebersihanBarang;
use App\Support\BhpKebersihan\PublicUploadPath;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class BarcodeService
{
    public const UPLOAD_REL_DIR = PublicUploadPath::BARCODE_REL_DIR;

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

    public function fileUrl(BhpKebersihanBarang $barang): ?string
    {
        $relative = $this->resolveRelativePath($barang);
        if ($relative === null) {
            return null;
        }

        return $this->assetUrl($relative);
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
     * Generate QR PNG (encode URL publik detail barang) dan simpan ke disk publik.
     */
    public function generateAndSave(BhpKebersihanBarang $barang, int $size = 400): string
    {
        if ($barang->barcode_token === null || $barang->barcode_token === '') {
            $barang->barcode_token = $this->generateToken();
        }

        $dir = $this->uploadDir();
        PublicUploadPath::ensureDir($dir);

        $relativePath = $this->relativePath($barang);
        $fullPath = PublicUploadPath::absolute($relativePath);

        $oldRelative = PublicUploadPath::normalizeRelative($barang->barcode_path);
        if ($oldRelative !== null && $oldRelative !== $relativePath) {
            foreach (PublicUploadPath::candidateAbsolutePaths($oldRelative) as $oldFile) {
                if (is_file($oldFile) && $oldFile !== $fullPath) {
                    @unlink($oldFile);
                }
            }
        }

        $targetUrl = $this->publicUrl($barang->barcode_token);
        $png = $this->fetchQrPng($targetUrl, $size);

        if (file_put_contents($fullPath, $png) === false) {
            throw new \RuntimeException('Gagal menyimpan file barcode ke ' . $relativePath);
        }

        $barang->barcode_path = $relativePath;
        $barang->save();

        return $relativePath;
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

    private function fetchQrPng(string $data, int $size): string
    {
        $apiUrl = sprintf(
            'https://api.qrserver.com/v1/create-qr-code/?size=%dx%d&format=png&data=%s',
            $size,
            $size,
            urlencode($data),
        );

        $response = Http::timeout(30)->get($apiUrl);

        if (! $response->successful()) {
            throw new \RuntimeException('Gagal generate gambar QR barcode.');
        }

        $body = $response->body();
        if ($body === '' || strlen($body) < 100) {
            throw new \RuntimeException('Respon generate barcode tidak valid.');
        }

        return $body;
    }
}
