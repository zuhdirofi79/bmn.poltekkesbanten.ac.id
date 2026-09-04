<?php

declare(strict_types=1);

namespace App\Services\BhpKebersihan;

use App\Models\BhpKebersihan\BhpKebersihanBarang;
use App\Support\BhpKebersihan\PublicUploadPath;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class FotoService
{
    public const MAX_BYTES = 5242880; // 5 MB

    /** @var list<string> */
    public const ALLOWED_EXT = ['jpg', 'jpeg', 'png', 'webp'];

    public function store(BhpKebersihanBarang $barang, UploadedFile $file): string
    {
        $ext = strtolower((string) $file->getClientOriginalExtension());
        if ($ext === '' || ! in_array($ext, self::ALLOWED_EXT, true)) {
            $mime = (string) $file->getMimeType();
            $ext = match (true) {
                str_contains($mime, 'jpeg') => 'jpg',
                str_contains($mime, 'png') => 'png',
                str_contains($mime, 'webp') => 'webp',
                default => 'jpg',
            };
        }

        $originalName = $file->getClientOriginalName();
        $filename = $this->buildFilename(
            (string) $barang->kode_barang,
            (string) $barang->nama_barang,
            $barang->merk,
            $ext,
        );

        $dir = PublicUploadPath::fotoDir();
        PublicUploadPath::ensureDir($dir);

        $absolute = $dir . '/' . $filename;
        $oldPath = $barang->foto_path;
        $file->move($dir, $filename);

        if (! is_file($absolute)) {
            throw new \RuntimeException('Gagal menyimpan foto barang ke ' . PublicUploadPath::FOTO_REL_DIR);
        }

        $relative = PublicUploadPath::FOTO_REL_DIR . '/' . $filename;
        $barang->foto_path = $relative;
        $barang->foto_original_name = $originalName;
        $barang->save();

        if ($oldPath && $oldPath !== $relative) {
            $this->deleteStored($oldPath);
        }

        return $relative;
    }

    public function buildFilename(string $kode, string $nama, ?string $merk, string $ext): string
    {
        $ext = strtolower($ext);
        $ext = preg_replace('/[^a-z0-9]/', '', $ext) ?? '';
        if ($ext === '' || ! in_array($ext, self::ALLOWED_EXT, true)) {
            $ext = 'jpg';
        }

        $parts = [
            $this->sanitizeSegment($kode, 'barang'),
            $this->sanitizeSegment($nama, 'nama'),
            $this->sanitizeSegment((string) $merk, 'tanpa-merk'),
            (string) time(),
            Str::lower(Str::random(6)),
        ];

        $base = implode('-', $parts);
        if (strlen($base) > 180) {
            $base = substr($base, 0, 180);
        }

        return $base . '.' . $ext;
    }

    public function sanitizeSegment(string $value, string $fallback = 'item'): string
    {
        $value = strtolower(trim($value));
        $value = str_replace(['\\', '/', '..'], '', $value);
        $value = preg_replace('/\s+/', '-', $value) ?? '';
        $value = preg_replace('/[^a-z0-9\-_]/', '', $value) ?? '';
        $value = trim($value, '-_');

        return $value !== '' ? $value : $fallback;
    }

    public function publicUrl(?string $relativePath): ?string
    {
        $relative = PublicUploadPath::normalizeRelative($relativePath);
        if ($relative === null) {
            return null;
        }

        if (PublicUploadPath::firstExistingFile($relative) === null) {
            PublicUploadPath::ensureOnPublicDisk($relative);
            if (PublicUploadPath::firstExistingFile($relative) === null) {
                return null;
            }
        }

        $url = PublicUploadPath::publicUrl($relative);

        return $url !== '' ? $url : null;
    }

    public function hasFile(?string $relativePath): bool
    {
        $relative = PublicUploadPath::normalizeRelative($relativePath);

        return $relative !== null && PublicUploadPath::firstExistingFile($relative) !== null;
    }

    public function deleteStored(?string $relativePath): void
    {
        $relative = PublicUploadPath::normalizeRelative($relativePath);
        if ($relative === null || ! str_starts_with($relative, PublicUploadPath::FOTO_REL_DIR . '/')) {
            return;
        }

        foreach (PublicUploadPath::candidateAbsolutePaths($relative) as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }
}
