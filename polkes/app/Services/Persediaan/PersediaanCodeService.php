<?php

declare(strict_types=1);

namespace App\Services\Persediaan;

use App\Models\Persediaan\PersediaanBarang;
use App\Support\Persediaan\PublicUploadPath;
use App\Support\Persediaan\UnitKerja;
use Illuminate\Support\Facades\Log;

class PersediaanCodeService
{
    public function __construct(private Code128Renderer $renderer)
    {
    }

    public function payloadFor(PersediaanBarang $barang): string
    {
        $existing = trim((string) ($barang->barcode_payload ?? ''));
        if ($existing !== '') {
            return $existing;
        }

        return $this->makePayload((string) $barang->unit_kerja, (string) $barang->kode_barang);
    }

    public function makePayload(string $unitKerja, string $kodeBarang): string
    {
        return UnitKerja::kode($unitKerja) . '|' . trim($kodeBarang);
    }

    /**
     * @return array{unit_kode: string, kode_barang: string}|null
     */
    public function parsePayload(string $raw): ?array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        if (! str_contains($raw, '|')) {
            return [
                'unit_kode'   => '',
                'kode_barang' => $raw,
            ];
        }

        [$unitKode, $kode] = explode('|', $raw, 2);
        $unitKode = strtoupper(trim($unitKode));
        $kode = trim($kode);
        if ($unitKode === '' || $kode === '') {
            return null;
        }

        return [
            'unit_kode'   => $unitKode,
            'kode_barang' => $kode,
        ];
    }

    public function unitFromKode(string $unitKode): ?string
    {
        $unitKode = strtoupper(trim($unitKode));
        foreach (UnitKerja::KODE as $unit => $kode) {
            if ($kode === $unitKode) {
                return $unit;
            }
        }

        return null;
    }

    /**
     * Resolve Code 128. Jangan ambil barang unit lain jika kode bentrok.
     */
    public function resolve(string $raw, ?string $unitContext = null): ?PersediaanBarang
    {
        $parsed = $this->parsePayload($raw);
        if ($parsed === null) {
            return null;
        }

        $query = PersediaanBarang::query()->where('kode_barang', $parsed['kode_barang']);

        if ($parsed['unit_kode'] !== '') {
            $unit = $this->unitFromKode($parsed['unit_kode']);
            if ($unit === null) {
                return null;
            }
            $query->where('unit_kerja', $unit);
        } elseif ($unitContext !== null && $unitContext !== '') {
            $query->where('unit_kerja', $unitContext);
        } else {
            $matches = $query->get();
            if ($matches->count() !== 1) {
                return null;
            }

            return $matches->first();
        }

        return $query->first();
    }

    public function filename(PersediaanBarang $barang): string
    {
        $kode = preg_replace('/[^A-Za-z0-9\-_]/', '', str_replace([' ', '/', '\\'], '-', (string) $barang->kode_barang)) ?: 'barang';

        return 'prs-' . (int) $barang->id . '-' . $kode . '.png';
    }

    public function relativePath(PersediaanBarang $barang): string
    {
        return PublicUploadPath::BARCODE_REL_DIR . '/' . $this->filename($barang);
    }

    public function fileUrl(?PersediaanBarang $barang): ?string
    {
        if ($barang === null) {
            return null;
        }

        $relative = PublicUploadPath::normalizeRelative($barang->barcode_path) ?? $this->relativePath($barang);
        $absolute = PublicUploadPath::firstExistingFile($relative);
        if ($absolute === null) {
            return null;
        }

        $url = PublicUploadPath::publicUrl($relative);
        $mtime = @filemtime($absolute);
        if ($mtime) {
            $url .= (str_contains($url, '?') ? '&' : '?') . 't=' . $mtime;
        }

        return $url !== '' ? $url : null;
    }

    public function generateAndSave(PersediaanBarang $barang): string
    {
        $payloadBefore = (string) ($barang->barcode_payload ?? '');
        $payload = $this->payloadFor($barang);
        if ($payloadBefore !== '' && $payloadBefore !== $payload) {
            $payload = $payloadBefore;
        }

        if ((string) $barang->barcode_payload !== $payload) {
            $barang->barcode_payload = $payload;
            $barang->save();
        }

        $png = $this->renderer->render($payload, (string) $barang->nama_barang, (string) $barang->kode_barang);
        $relative = $this->relativePath($barang);
        $fullPath = PublicUploadPath::absolute($relative);
        PublicUploadPath::ensureDir(dirname($fullPath));

        $tmpPath = $fullPath . '.tmp-' . bin2hex(random_bytes(4));
        if (file_put_contents($tmpPath, $png) === false) {
            Log::error('Gagal menulis barcode Persediaan', ['path' => $tmpPath]);
            throw new \RuntimeException('Gagal menyimpan barcode. Periksa permission folder unggahan.');
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
            throw new \RuntimeException('Gagal menimpa file barcode. File lama tetap dipertahankan.');
        }

        $barang->barcode_path = $relative;
        $barang->save();

        return $relative;
    }
}
