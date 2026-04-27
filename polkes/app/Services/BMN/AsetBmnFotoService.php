<?php

declare(strict_types=1);

namespace App\Services\BMN;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;

class AsetBmnFotoService
{
    public const MAX_ORIG_WIDTH = 1280;

    public const THUMB_W = 200;

    public const THUMB_H = 200;

    public const JPG_FULL_QUALITY = 75;

    public const JPG_THUMB_QUALITY = 70;

    /** Path web & JSON (selalu relatif domain, bukan path absolut server). */
    public const BASE_REL = 'uploads/foto_bmn';

    public const THUMB_REL = 'uploads/foto_bmn/thumbs';

    /** Maks. item {full, thumb} per aset. */
    public const MAX_FOTO_PER_ASET = 2;

    public function ensureDirectories(): void
    {
        $basePath = bmn_upload_path();
        $thumbPath = bmn_upload_path('thumbs');
        if (! is_dir($basePath)) {
            mkdir($basePath, 0775, true);
        }
        if (! is_dir($thumbPath)) {
            mkdir($thumbPath, 0775, true);
        }

        Log::info('BMN UPLOAD PATH', [
            'base' => $basePath,
            'thumb' => $thumbPath,
            'config_bmn_foto_path' => trim((string) (config('bmn.foto_path') ?? '')) !== ''
                ? (string) config('bmn.foto_path')
                : '(default: public_path uploads/foto_bmn)',
        ]);
    }

    /**
     * @param  list<UploadedFile>  $files
     * @return list<array{full: string, thumb: string}>
     */
    public function processUploads(
        array $files,
        string $namaBarang,
        string $kodeBarang,
        ?string $nup,
        ?int $maxItems = null
    ): array {
        $limit = $maxItems ?? self::MAX_FOTO_PER_ASET;
        if ($limit < 1) {
            $limit = self::MAX_FOTO_PER_ASET;
        }
        $files = \array_slice(\array_values($files), 0, $limit);

        $this->ensureDirectories();
        $slugNama = Str::slug($namaBarang) !== '' ? Str::slug($namaBarang) : 'aset';
        $slugKode = Str::slug($kodeBarang) !== '' ? Str::slug($kodeBarang) : 'kode';
        $nupPart = ($nup !== null && $nup !== '') ? Str::slug($nup) : 'no-nup';

        $fotoPaths = [];
        $i = 1;
        $ts = time();
        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }
            if (! $file->isValid()) {
                continue;
            }

            $baseName = $slugNama.'_'.$nupPart.'_'.$slugKode.'_'.$ts.'_'.$i;
            $filename = $baseName.'.jpg';
            $thumbname = $baseName.'_thumb.jpg';

            $fullPublicRel = self::BASE_REL.'/'.$filename;
            $thumbPublicRel = self::THUMB_REL.'/'.$thumbname;
            $fullFilePath = bmn_upload_path($filename);
            $thumbFilePath = bmn_upload_path('thumbs'.\DIRECTORY_SEPARATOR.$thumbname);

            $pair = [
                'full' => '/'.$fullPublicRel,
                'thumb' => '/'.$thumbPublicRel,
            ];
            try {
                Image::make($file)
                    ->orientate()
                    ->resize(self::MAX_ORIG_WIDTH, null, function ($c): void {
                        $c->aspectRatio();
                        $c->upsize();
                    })
                    ->save($fullFilePath, self::JPG_FULL_QUALITY, 'jpg');

                Image::make($file)
                    ->orientate()
                    ->fit(self::THUMB_W, self::THUMB_H)
                    ->save($thumbFilePath, self::JPG_THUMB_QUALITY, 'jpg');
            } catch (\Throwable $e) {
                $this->deleteLocalFiles($fotoPaths);
                $this->deleteLocalFiles([$pair]);

                throw $e;
            }

            $fotoPaths[] = $pair;
            $i++;
        }

        return $fotoPaths;
    }

    /**
     * Hapus file lokal; abaikan URL eksternal (impor / CDN).
     * Path di disk diresolusi lewat bmn_upload_path() (sinkron dengan hosting public_html).
     *
     * @param  list<array{full?: string, thumb?: string}>|null  $items
     */
    public function deleteLocalFiles(?array $items): void
    {
        if ($items === null || $items === []) {
            return;
        }
        foreach ($items as $img) {
            if (! is_array($img)) {
                continue;
            }
            $this->unlinkStoredWebPath($img['full'] ?? null, false);
            $this->unlinkStoredWebPath($img['thumb'] ?? null, true);
        }
    }

    private function unlinkStoredWebPath(?string $publicPathOrUrl, bool $isThumb): void
    {
        if ($publicPathOrUrl === null || $publicPathOrUrl === '') {
            return;
        }
        if (Str::startsWith($publicPathOrUrl, ['http://', 'https://'])) {
            return;
        }
        $name = basename(parse_url($publicPathOrUrl, PHP_URL_PATH) ?? $publicPathOrUrl);
        if ($name === '' || $name === '.' || $name === '..') {
            return;
        }
        $abs = $isThumb
            ? bmn_upload_path('thumbs'.\DIRECTORY_SEPARATOR.$name)
            : bmn_upload_path($name);
        if (is_file($abs)) {
            @unlink($abs);
        }
    }
}
