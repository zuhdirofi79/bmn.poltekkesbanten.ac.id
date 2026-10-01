<?php

declare(strict_types=1);

namespace App\Services\Persediaan;

/**
 * Code 128B renderer (GD). Tidak memakai CDN.
 * Label target: ~50mm × 20mm (canvas 591×236 @ 300dpi).
 */
final class Code128Renderer
{
    public const WIDTH = 591;

    public const HEIGHT = 236;

    /** @var list<string> 11-bit patterns for values 0–106 */
    private const PATTERNS = [
        '11011001100', '11001101100', '11001100110', '10010011000', '10010001100',
        '10001001100', '10011001000', '10011000100', '10001100100', '11001001000',
        '11001000100', '11000100100', '10110011100', '10011011100', '10011001110',
        '10111001100', '10011101100', '10011100110', '11001110010', '11001011100',
        '11001001110', '11011100100', '11001110100', '11101101110', '11101001100',
        '11100101100', '11100100110', '11101100100', '11100110100', '11100110010',
        '11011011000', '11011000110', '11000110110', '10100011000', '10001011000',
        '10001000110', '10110001000', '10001101000', '10001100010', '11010001000',
        '11000101000', '11000100010', '10110111000', '10110001110', '10001101110',
        '10111011000', '10111000110', '10001110110', '11101110110', '11010001110',
        '11000101110', '11011101000', '11011100010', '11011101110', '11101011000',
        '11101000110', '11100010110', '11101101000', '11101100010', '11100011010',
        '11101111010', '11001000010', '11110001010', '10100110000', '10100001100',
        '10010110000', '10010000110', '10000101100', '10000100110', '10110010000',
        '10110000100', '10011010000', '10011000010', '10000110100', '10000110010',
        '11000010010', '11001010000', '11110111010', '11000010100', '10001111010',
        '10100111100', '10010111100', '10010011110', '10111100100', '10011110100',
        '10011110010', '11110100100', '11110010100', '11110010010', '11011011110',
        '11011110110', '11110110110', '10101111000', '10100011110', '10001011110',
        '10111101000', '10111100010', '11110101000', '11110100010', '10111011110',
        '10111101110', '11101011110', '11110101110', '11010000100', '11010010000',
        '11010011100', '1100011101011',
    ];

    public const START_B = 104;

    public const STOP = 106;

    public function render(string $payload, string $namaBarang, string $kodeBarang): string
    {
        $bits = $this->encodeBits($payload);
        $canvas = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        if ($canvas === false) {
            throw new \RuntimeException('Gagal membuat kanvas barcode Code 128.');
        }

        $white = imagecolorallocate($canvas, 255, 255, 255);
        $black = imagecolorallocate($canvas, 0, 0, 0);
        imagefilledrectangle($canvas, 0, 0, self::WIDTH, self::HEIGHT, $white);

        $name = $this->truncate($namaBarang, 42);
        $kode = trim($kodeBarang) !== '' ? trim($kodeBarang) : $payload;

        imagestring($canvas, 3, 10, 6, $name, $black);

        $barTop = 28;
        $barBottom = self::HEIGHT - 32;
        $barHeight = $barBottom - $barTop;
        $quiet = 18;
        $usable = self::WIDTH - (2 * $quiet);
        $module = max(1, (int) floor($usable / max(strlen($bits), 1)));
        $barWidth = $module * strlen($bits);
        $x = (int) (($usable - $barWidth) / 2) + $quiet;

        for ($i = 0, $len = strlen($bits); $i < $len; $i++) {
            if ($bits[$i] === '1') {
                imagefilledrectangle($canvas, $x, $barTop, $x + $module - 1, $barTop + $barHeight, $black);
            }
            $x += $module;
        }

        $kodeWidth = imagefontwidth(4) * strlen($kode);
        $kodeX = (int) max(4, (self::WIDTH - $kodeWidth) / 2);
        imagestring($canvas, 4, $kodeX, self::HEIGHT - 24, $kode, $black);

        ob_start();
        imagepng($canvas, null, 6);
        $png = (string) ob_get_clean();
        imagedestroy($canvas);

        if ($png === '' || strlen($png) < 100) {
            throw new \RuntimeException('Gagal merender PNG Code 128.');
        }

        return $png;
    }

    public function encodeBits(string $payload): string
    {
        $data = $this->toCodeB($payload);
        $checksum = self::START_B;
        $bits = self::PATTERNS[self::START_B];

        $len = strlen($data);
        for ($i = 0; $i < $len; $i++) {
            $value = ord($data[$i]) - 32;
            if ($value < 0 || $value > 95) {
                throw new \InvalidArgumentException('Karakter tidak didukung Code 128B: ' . $data[$i]);
            }
            $checksum += $value * ($i + 1);
            $bits .= self::PATTERNS[$value];
        }

        $bits .= self::PATTERNS[$checksum % 103];
        $bits .= self::PATTERNS[self::STOP];

        return $bits;
    }

    private function toCodeB(string $payload): string
    {
        $payload = trim($payload);
        if ($payload === '') {
            throw new \InvalidArgumentException('Payload barcode kosong.');
        }

        $out = '';
        $len = strlen($payload);
        for ($i = 0; $i < $len; $i++) {
            $ord = ord($payload[$i]);
            if ($ord < 32 || $ord > 127) {
                throw new \InvalidArgumentException('Payload barcode harus ASCII Code 128B.');
            }
            $out .= $payload[$i];
        }

        return $out;
    }

    private function truncate(string $text, int $max): string
    {
        $text = trim(preg_replace('/\s+/', ' ', $text) ?? $text);
        if (strlen($text) <= $max) {
            return $text;
        }

        return substr($text, 0, $max - 1) . '.';
    }
}
