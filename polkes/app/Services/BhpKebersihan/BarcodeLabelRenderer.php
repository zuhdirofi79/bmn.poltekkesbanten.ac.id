<?php

declare(strict_types=1);

namespace App\Services\BhpKebersihan;

use Illuminate\Support\Facades\Http;

/**
 * Label QR horizontal: QR + logo Poltekkes di tengah, nama & kode di kanan.
 */
final class BarcodeLabelRenderer
{
    public const CANVAS_W = 1800;

    public const CANVAS_H = 800;

    public const QR_SIZE = 704;

    public const PAD = 48;

    public const LOGO_RATIO = 0.22;

    /** @var callable|null */
    private $qrPngFetcher;

    public function __construct(?callable $qrPngFetcher = null)
    {
        $this->qrPngFetcher = $qrPngFetcher;
    }

    public function render(string $scanUrl, string $namaBarang, string $kodeBarang): string
    {
        $qr = $this->qrWithLogo($scanUrl);
        $canvas = imagecreatetruecolor(self::CANVAS_W, self::CANVAS_H);
        if ($canvas === false) {
            imagedestroy($qr);
            throw new \RuntimeException('Gagal membuat kanvas label barcode.');
        }

        $white = imagecolorallocate($canvas, 255, 255, 255);
        $black = imagecolorallocate($canvas, 0, 0, 0);
        $line = imagecolorallocate($canvas, 40, 40, 40);
        imagefilledrectangle($canvas, 0, 0, self::CANVAS_W, self::CANVAS_H, $white);

        $qrY = (int) ((self::CANVAS_H - self::QR_SIZE) / 2);
        imagecopyresampled(
            $canvas,
            $qr,
            self::PAD,
            $qrY,
            0,
            0,
            self::QR_SIZE,
            self::QR_SIZE,
            imagesx($qr),
            imagesy($qr)
        );
        imagedestroy($qr);

        $textX = self::PAD + self::QR_SIZE + 40;
        $textW = self::CANVAS_W - $textX - self::PAD;
        $font = $this->boldFont();
        $nama = trim($namaBarang) !== '' ? trim($namaBarang) : 'Nama Barang';
        $kode = trim($kodeBarang) !== '' ? trim($kodeBarang) : 'Kode Barang';

        $nameSize = 54;
        $nameLines = $this->fitLines($nama, $font, $nameSize, $textW, 3);
        $codeSize = 54;
        $codeLines = $this->fitLines($kode, $font, $codeSize, $textW, 2);

        $nameH = count($nameLines) * (int) round($nameSize * 1.25);
        $codeH = count($codeLines) * (int) round($codeSize * 1.25);
        $blockH = $nameH + 28 + $codeH;
        $blockY = (int) ((self::CANVAS_H - $blockH) / 2);

        $nameMax = 0;
        foreach ($nameLines as $lineText) {
            $nameMax = max($nameMax, $this->textWidth($lineText, $font, $nameSize));
        }
        $codeMax = 0;
        foreach ($codeLines as $lineText) {
            $codeMax = max($codeMax, $this->textWidth($lineText, $font, $codeSize));
        }
        $ruleW = min($textW, max($nameMax, $codeMax, 180) + 12);

        $y = $blockY + $nameSize;
        foreach ($nameLines as $lineText) {
            imagettftext($canvas, $nameSize, 0, $textX, $y, $black, $font, $lineText);
            $y += (int) round($nameSize * 1.25);
        }

        $ruleY = $y + 4;
        imageline($canvas, $textX, $ruleY, $textX + $ruleW, $ruleY, $line);
        imageline($canvas, $textX, $ruleY + 1, $textX + $ruleW, $ruleY + 1, $line);

        $y = $ruleY + 18 + $codeSize;
        foreach ($codeLines as $lineText) {
            imagettftext($canvas, $codeSize, 0, $textX, $y, $black, $font, $lineText);
            $y += (int) round($codeSize * 1.25);
        }

        ob_start();
        imagepng($canvas, null, 6);
        $png = (string) ob_get_clean();
        imagedestroy($canvas);

        if ($png === '' || strlen($png) < 200) {
            throw new \RuntimeException('Gagal merender PNG label barcode.');
        }

        return $png;
    }

    /**
     * @return \GdImage
     */
    private function qrWithLogo(string $scanUrl)
    {
        $raw = $this->fetchQrPng($scanUrl, 700);
        $qr = imagecreatefromstring($raw);
        if ($qr === false) {
            throw new \RuntimeException('QR PNG tidak valid.');
        }

        $qr = $this->ensureTrueColor($qr);
        $w = imagesx($qr);
        $h = imagesy($qr);
        $cx = (int) ($w / 2);
        $cy = (int) ($h / 2);
        $diameter = (int) round(min($w, $h) * self::LOGO_RATIO);
        $white = imagecolorallocate($qr, 255, 255, 255);
        imagefilledellipse($qr, $cx, $cy, $diameter, $diameter, $white);

        $logo = $this->loadLogo();
        if ($logo !== null) {
            $logoSize = (int) round($diameter * 0.78);
            $dstX = (int) ($cx - $logoSize / 2);
            $dstY = (int) ($cy - $logoSize / 2);
            imagecopyresampled(
                $qr,
                $logo,
                $dstX,
                $dstY,
                0,
                0,
                $logoSize,
                $logoSize,
                imagesx($logo),
                imagesy($logo)
            );
            imagedestroy($logo);
        }

        return $qr;
    }

    private function fetchQrPng(string $data, int $size): string
    {
        if ($this->qrPngFetcher !== null) {
            return (string) ($this->qrPngFetcher)($data, $size);
        }

        $apiUrl = sprintf(
            'https://api.qrserver.com/v1/create-qr-code/?size=%1$dx%1$d&ecc=H&margin=1&qzone=1&format=png&color=000000&bgcolor=ffffff&data=%2$s',
            $size,
            urlencode($data)
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
            report($e);
        }

        $ctx = stream_context_create(['http' => ['timeout' => 30], 'ssl' => ['verify_peer' => true]]);
        $raw = @file_get_contents($apiUrl, false, $ctx);
        if (! is_string($raw) || strlen($raw) < 100) {
            throw new \RuntimeException('Gagal generate gambar QR barcode.');
        }

        return $raw;
    }

    /**
     * @return \GdImage|null
     */
    private function loadLogo()
    {
        foreach ([public_path('img/logo.png'), public_path('img/fav.png')] as $path) {
            if (! is_file($path)) {
                continue;
            }
            $img = @imagecreatefromstring((string) file_get_contents($path));
            if ($img === false) {
                continue;
            }
            $img = $this->ensureTrueColor($img);
            $this->makeDarkBackgroundTransparent($img);

            return $img;
        }

        return null;
    }

    /**
     * @param  \GdImage  $img
     */
    private function makeDarkBackgroundTransparent($img): void
    {
        imagealphablending($img, false);
        imagesavealpha($img, true);
        $transparent = imagecolorallocatealpha($img, 0, 0, 0, 127);
        $w = imagesx($img);
        $h = imagesy($img);
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $rgba = imagecolorat($img, $x, $y);
                $r = ($rgba >> 16) & 0xFF;
                $g = ($rgba >> 8) & 0xFF;
                $b = $rgba & 0xFF;
                if ($r < 28 && $g < 28 && $b < 28) {
                    imagesetpixel($img, $x, $y, $transparent);
                }
            }
        }
        imagealphablending($img, true);
    }

    /**
     * @param  \GdImage  $img
     * @return \GdImage
     */
    private function ensureTrueColor($img)
    {
        if (imageistruecolor($img)) {
            imagealphablending($img, true);
            imagesavealpha($img, true);

            return $img;
        }

        $w = imagesx($img);
        $h = imagesy($img);
        $true = imagecreatetruecolor($w, $h);
        imagealphablending($true, false);
        imagesavealpha($true, true);
        $transparent = imagecolorallocatealpha($true, 0, 0, 0, 127);
        imagefilledrectangle($true, 0, 0, $w, $h, $transparent);
        imagealphablending($true, true);
        imagecopy($true, $img, 0, 0, 0, 0, $w, $h);
        imagedestroy($img);

        return $true;
    }

    private function boldFont(): string
    {
        $candidates = [
            base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ttf'),
            'C:/Windows/Fonts/arialbd.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
            '/usr/share/fonts/truetype/freefont/FreeSansBold.ttf',
        ];
        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        throw new \RuntimeException('Font TTF bold tidak ditemukan untuk merender label barcode.');
    }

    /**
     * @return list<string>
     */
    private function fitLines(string $text, string $font, int &$size, int $maxWidth, int $maxLines): array
    {
        while ($size >= 28) {
            $lines = $this->wrap($text, $font, $size, $maxWidth);
            $tooWide = false;
            foreach ($lines as $line) {
                if ($this->textWidth($line, $font, $size) > $maxWidth) {
                    $tooWide = true;
                    break;
                }
            }
            if (count($lines) <= $maxLines && ! $tooWide) {
                return $lines;
            }
            $size -= 2;
        }

        return $this->wrap($text, $font, $size, $maxWidth);
    }

    /**
     * @return list<string>
     */
    private function wrap(string $text, string $font, int $size, int $maxWidth): array
    {
        $words = preg_split('/\s+/u', $text) ?: [$text];
        $lines = [];
        $current = '';
        foreach ($words as $word) {
            $try = $current === '' ? $word : $current . ' ' . $word;
            if ($this->textWidth($try, $font, $size) <= $maxWidth) {
                $current = $try;
            } else {
                if ($current !== '') {
                    $lines[] = $current;
                }
                $current = $word;
            }
        }
        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines !== [] ? $lines : [$text];
    }

    private function textWidth(string $text, string $font, int $size): int
    {
        $box = imagettfbbox($size, 0, $font, $text);
        if ($box === false) {
            return strlen($text) * $size;
        }

        return (int) abs($box[2] - $box[0]);
    }
}
