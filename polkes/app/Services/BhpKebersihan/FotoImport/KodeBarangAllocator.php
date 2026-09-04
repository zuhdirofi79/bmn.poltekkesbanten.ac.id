<?php

declare(strict_types=1);

namespace App\Services\BhpKebersihan\FotoImport;

final class KodeBarangAllocator
{
    public const DEFAULT_PREFIX = 'KBR-01';

    /**
     * @param  list<string>  $existing
     * @param  list<string>  $reserved
     * @return list<string>
     */
    public function nextCodes(int $count, array $existing, array $reserved = []): array
    {
        if ($count < 1) {
            return [];
        }

        $used = [];
        foreach (array_merge($existing, $reserved) as $kode) {
            $key = strtoupper(trim((string) $kode));
            if ($key !== '') {
                $used[$key] = true;
            }
        }

        [$prefix, $seq] = $this->cursor($existing);
        $out = [];

        for ($i = 0; $i < $count; $i++) {
            do {
                $seq++;
                if ($seq > 999) {
                    $prefix = $this->bumpPrefix($prefix);
                    $seq = 1;
                }
                $code = sprintf('%s-%03d', $prefix, $seq);
            } while (isset($used[strtoupper($code)]));

            $used[strtoupper($code)] = true;
            $out[] = $code;
        }

        return $out;
    }

    /**
     * @param  list<string>  $existing
     * @return array{0: string, 1: int}
     */
    public function cursor(array $existing): array
    {
        $bestPrefix = self::DEFAULT_PREFIX;
        $bestGroup = 1;
        $bestSeq = 0;
        $found = false;

        foreach ($existing as $kode) {
            if (preg_match('/^KBR-(\d+)-(\d+)$/i', trim((string) $kode), $m) !== 1) {
                continue;
            }
            $found = true;
            $group = (int) $m[1];
            $seq = (int) $m[2];
            $width = strlen($m[1]);
            if ($group > $bestGroup || ($group === $bestGroup && $seq >= $bestSeq)) {
                $bestGroup = $group;
                $bestSeq = $seq;
                $bestPrefix = 'KBR-' . str_pad((string) $group, max(2, $width), '0', STR_PAD_LEFT);
            }
        }

        if (! $found) {
            return [self::DEFAULT_PREFIX, 0];
        }

        return [$bestPrefix, $bestSeq];
    }

    private function bumpPrefix(string $prefix): string
    {
        if (preg_match('/^KBR-(\d+)$/i', $prefix, $m) !== 1) {
            return 'KBR-02';
        }

        $group = (int) $m[1] + 1;
        $width = max(2, strlen($m[1]));

        return 'KBR-' . str_pad((string) $group, $width, '0', STR_PAD_LEFT);
    }
}
