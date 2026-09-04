<?php

declare(strict_types=1);

namespace App\Services\BhpKebersihan\FotoImport;

final class TextTableParser
{
    /** @var list<string> */
    private const SKIP_LINE = [
        'nama barang', 'spesifikasi/merk', 'spesifikasi', 'keterangan',
        'surat jalan', 'cv. faiz', 'direktur', 'jakarta', 'usulan belanja',
        'politeknik kesehatan', 'volume', 'satuan', 'no nama',
    ];

    /**
     * @return list<array<string,mixed>>
     */
    public function parse(string $text, int $sourcePage = 1): array
    {
        $rows = [];
        $lines = preg_split('/\R/u', $text) ?: [];

        foreach ($lines as $line) {
            $line = VolumeParser::clean($line);
            if ($line === '' || $this->shouldSkip($line)) {
                continue;
            }

            $parsed = $this->parseLine($line);
            if ($parsed === null) {
                continue;
            }

            $parsed['source_page'] = $sourcePage;
            $parsed['confidence'] = 'medium';
            $rows[] = $parsed;
        }

        return $rows;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function parseLine(string $line): ?array
    {
        $cells = array_values(array_filter(
            array_map([VolumeParser::class, 'clean'], preg_split('/\s*\|\s*/u', $line) ?: []),
            fn (string $c) => $c !== ''
        ));

        if (count($cells) >= 3 && preg_match('/^\d{1,3}$/', $cells[0]) === 1) {
            $no = (int) $cells[0];
            $nama = $cells[1] ?? '';
            $merk = $cells[2] ?? '';
            $volumeRaw = trim(($cells[3] ?? '') . ' ' . ($cells[4] ?? ''));
            $vol = VolumeParser::split($volumeRaw);

            if ($nama === '' || $this->shouldSkip($nama)) {
                return null;
            }

            return [
                'no' => $no,
                'nama_barang' => $nama,
                'merk' => $this->isDash($merk) ? '' : $merk,
                'jumlah' => $vol['jumlah'],
                'satuan' => $vol['satuan'],
                'kode_barang' => null,
                'needs_review' => $vol['needs_review'] || $nama === '',
            ];
        }

        if (preg_match('/^(\d{1,3})[.)]?\s+(.+)$/u', $line, $m) !== 1) {
            return null;
        }

        $no = (int) $m[1];
        $rest = VolumeParser::clean($m[2]);
        $vol = VolumeParser::split($rest);

        $namaMerk = VolumeParser::clean(preg_replace(
            '/\d{1,7}(?:[.,]\d{1,2})?\s+\S+.*$/u',
            '',
            $rest
        ) ?? $rest);

        if ($namaMerk === '' || $this->shouldSkip($namaMerk)) {
            return null;
        }

        $parts = preg_split('/\s{2,}/u', $namaMerk) ?: [$namaMerk];
        $nama = $parts[0];
        $merk = $parts[1] ?? '';

        return [
            'no' => $no,
            'nama_barang' => $nama,
            'merk' => $this->isDash($merk) ? '' : $merk,
            'jumlah' => $vol['jumlah'],
            'satuan' => $vol['satuan'],
            'kode_barang' => null,
            'needs_review' => true,
        ];
    }

    private function shouldSkip(string $line): bool
    {
        $fold = VolumeParser::fold($line);
        if (preg_match('/^(done|kurang|baru|checklist|✓|✔)/u', $fold) === 1) {
            return true;
        }
        foreach (self::SKIP_LINE as $needle) {
            if (str_contains($fold, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function isDash(string $value): bool
    {
        return $value === '' || $value === '-' || $value === '—' || $value === '–';
    }
}
