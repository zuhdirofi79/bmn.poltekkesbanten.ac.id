<?php

declare(strict_types=1);

namespace App\Services\Persediaan;

use Illuminate\Support\Str;

/**
 * Menyimpan tanda tangan digital (canvas dataURL PNG) ke folder publik.
 * Mengembalikan path relatif (mis. uploads/persediaan/ttd/xxx.png) yang
 * bisa dipakai langsung dengan helper asset().
 */
class SignatureService
{
    /**
     * @param  string  $dataUrl  data:image/png;base64,....
     * @return string|null path relatif ke public/, null jika input tidak valid
     */
    public function storeBase64(?string $dataUrl, string $subdir = 'uploads/persediaan/ttd'): ?string
    {
        if ($dataUrl === null || trim($dataUrl) === '') {
            return null;
        }

        if (! preg_match('/^data:image\/(png|jpeg|jpg);base64,/', $dataUrl, $m)) {
            return null;
        }

        $ext = $m[1] === 'jpeg' ? 'jpg' : $m[1];
        $encoded = substr($dataUrl, strpos($dataUrl, ',') + 1);
        $binary = base64_decode(strtr($encoded, ' ', '+'), true);

        if ($binary === false || strlen($binary) < 64) {
            return null;
        }

        $dir = public_path($subdir);
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $filename = 'ttd_' . date('Ymd_His') . '_' . Str::random(8) . '.' . $ext;
        $relative = trim($subdir, '/') . '/' . $filename;

        file_put_contents(public_path($relative), $binary);

        return $relative;
    }
}
