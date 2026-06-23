<?php

declare(strict_types=1);

namespace App\Services\BhpKebersihan;

use App\Models\BhpKebersihan\BhpKebersihanBarang;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class BarcodeService
{
    public const UPLOAD_REL_DIR = 'uploads/barcode/bhp-kebersihan';

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
        return public_path(self::UPLOAD_REL_DIR);
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

    public function assetUrl(?string $relativePath): ?string
    {
        if ($relativePath === null || $relativePath === '') {
            return null;
        }

        return asset($relativePath);
    }

    public function hasValidFile(BhpKebersihanBarang $barang): bool
    {
        if ($barang->barcode_path === null || $barang->barcode_path === '') {
            return false;
        }

        return is_file(public_path($barang->barcode_path));
    }

    /**
     * Generate QR PNG (encode URL publik detail barang) dan simpan ke disk.
     */
    public function generateAndSave(BhpKebersihanBarang $barang, int $size = 400): string
    {
        if ($barang->barcode_token === null || $barang->barcode_token === '') {
            $barang->barcode_token = $this->generateToken();
        }

        $dir = $this->uploadDir();
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $relativePath = $this->relativePath($barang);
        $fullPath = public_path($relativePath);

        if ($barang->barcode_path !== null
            && $barang->barcode_path !== $relativePath
            && is_file(public_path($barang->barcode_path))) {
            @unlink(public_path($barang->barcode_path));
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
