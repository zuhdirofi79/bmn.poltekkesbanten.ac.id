<?php

declare(strict_types=1);

namespace App\Services\Persediaan;

use App\Support\Persediaan\PublicUploadPath;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Generate PNG QR dari URL publik. Token tidak diubah di sini.
 */
class QrImageService
{
    /** @var callable|null */
    private $pngFetcher;

    public function __construct(?callable $pngFetcher = null)
    {
        $this->pngFetcher = $pngFetcher;
    }

    public function pngForUrl(string $url, int $size = 400): string
    {
        if ($this->pngFetcher !== null) {
            return (string) ($this->pngFetcher)($url, $size);
        }

        $apiUrl = sprintf(
            'https://api.qrserver.com/v1/create-qr-code/?size=%1$dx%1$d&ecc=M&margin=2&qzone=1&format=png&color=000000&bgcolor=ffffff&data=%2$s',
            $size,
            urlencode($url)
        );

        try {
            $response = Http::timeout(30)->get($apiUrl);
            if ($response->successful()) {
                $body = $response->body();
                if ($body !== '' && strlen($body) >= 100) {
                    return $body;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Persediaan QR HTTP gagal: ' . $e->getMessage());
        }

        $ctx = stream_context_create(['http' => ['timeout' => 30], 'ssl' => ['verify_peer' => true]]);
        $raw = @file_get_contents($apiUrl, false, $ctx);
        if (! is_string($raw) || strlen($raw) < 100) {
            throw new \RuntimeException('Gagal generate gambar QR. Periksa koneksi ke layanan QR.');
        }

        return $raw;
    }

    public function saveRelative(string $relativePath, string $png): string
    {
        $relative = PublicUploadPath::normalizeRelative($relativePath) ?? ltrim(str_replace('\\', '/', $relativePath), '/');
        $fullPath = PublicUploadPath::absolute($relative);
        PublicUploadPath::ensureDir(dirname($fullPath));

        $tmpPath = $fullPath . '.tmp-' . bin2hex(random_bytes(4));
        if (file_put_contents($tmpPath, $png) === false) {
            Log::error('Gagal menulis file QR sementara', ['path' => $tmpPath]);
            throw new \RuntimeException('Gagal menyimpan file QR. Periksa permission folder unggahan.');
        }

        if (is_file($fullPath)) {
            $ok = @copy($tmpPath, $fullPath);
        } else {
            $ok = @rename($tmpPath, $fullPath);
            if (! $ok) {
                $ok = @copy($tmpPath, $fullPath);
            }
        }

        if (is_file($tmpPath)) {
            $tmpReal = realpath($tmpPath);
            $fullReal = is_file($fullPath) ? realpath($fullPath) : false;
            if ($tmpReal !== false && $tmpReal !== $fullReal) {
                @unlink($tmpPath);
            }
        }

        if (! $ok || ! is_file($fullPath)) {
            Log::error('Gagal menimpa file QR', ['path' => $fullPath]);
            throw new \RuntimeException('Gagal menyimpan file QR. File lama tetap dipertahankan.');
        }

        return $relative;
    }
}
