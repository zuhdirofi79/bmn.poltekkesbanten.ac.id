<?php

declare(strict_types=1);

namespace App\Services\BhpKebersihan\FotoImport;

use App\Models\BhpKebersihan\BhpKebersihanBarang;

final class PreviewBuilder
{
    public function __construct(private KodeBarangAllocator $allocator)
    {
    }

    /**
     * @param  list<array<string,mixed>>  $rawRows
     * @return array{rows: list<array<string,mixed>>, summary: array<string,int>}
     */
    public function build(array $rawRows): array
    {
        $existingRows = BhpKebersihanBarang::query()
            ->get(['id', 'kode_barang', 'nama_barang', 'merk', 'satuan']);

        $dbByKey = [];
        $dbByKode = [];
        foreach ($existingRows as $barang) {
            $key = VolumeParser::normalizeKey(
                (string) $barang->nama_barang,
                (string) ($barang->merk ?? ''),
                (string) $barang->satuan
            );
            $dbByKey[$key] = $barang;
            $dbByKode[strtoupper((string) $barang->kode_barang)] = $barang;
        }

        $normalized = [];
        foreach ($rawRows as $index => $raw) {
            $normalized[] = $this->normalizeRaw($raw, $index);
        }

        $seenBatch = [];
        $needCodes = 0;

        foreach ($normalized as $i => $row) {
            $kode = strtoupper((string) ($row['kode_barang'] ?? ''));
            if ($kode !== '' && isset($dbByKode[$kode])) {
                $normalized[$i]['status'] = 'sudah_ada';
                $normalized[$i]['status_label'] = self::labelFor('sudah_ada');
                $normalized[$i]['existing_id'] = (int) $dbByKode[$kode]->id;
                $normalized[$i]['kode_barang'] = $dbByKode[$kode]->kode_barang;
                $normalized[$i]['notes'][] = 'Kode barang sudah ada di master.';
                continue;
            }

            $key = VolumeParser::normalizeKey($row['nama_barang'], $row['merk'], $row['satuan']);
            if ($key !== '||' && isset($dbByKey[$key])) {
                $normalized[$i]['status'] = 'sudah_ada';
                $normalized[$i]['status_label'] = self::labelFor('sudah_ada');
                $normalized[$i]['existing_id'] = (int) $dbByKey[$key]->id;
                $normalized[$i]['kode_barang'] = $dbByKey[$key]->kode_barang;
                $normalized[$i]['notes'][] = 'Barang dengan nama, merk, dan satuan yang sama sudah ada.';
                continue;
            }

            if ($key !== '||' && isset($seenBatch[$key])) {
                $normalized[$i]['status'] = 'duplikat_batch';
                $normalized[$i]['status_label'] = self::labelFor('duplikat_batch');
                $normalized[$i]['notes'][] = 'Duplikat dalam batch import ini (halaman lain / foto berulang).';
                continue;
            }

            if ($key !== '||') {
                $seenBatch[$key] = $i;
            }

            if ($row['kode_barang'] === '') {
                $needCodes++;
            }
        }

        $existingCodes = $existingRows->pluck('kode_barang')->all();
        $reserved = [];
        foreach ($normalized as $row) {
            if (($row['kode_barang'] ?? '') !== '' && ($row['status'] ?? '') === 'ok') {
                $reserved[] = $row['kode_barang'];
            }
        }
        $generated = $this->allocator->nextCodes($needCodes, $existingCodes, $reserved);
        $gi = 0;

        foreach ($normalized as $i => $row) {
            if (in_array($row['status'], ['sudah_ada', 'duplikat_batch'], true)) {
                continue;
            }
            if ($row['kode_barang'] === '' && isset($generated[$gi])) {
                $normalized[$i]['kode_barang'] = $generated[$gi];
                $gi++;
            }
        }

        $summary = [
            'total' => count($normalized),
            'siap' => 0,
            'perlu_diperiksa' => 0,
            'sudah_ada' => 0,
            'duplikat_batch' => 0,
        ];
        foreach ($normalized as $row) {
            $key = $row['status'] === 'ok' ? 'siap' : $row['status'];
            $summary[$key] = ($summary[$key] ?? 0) + 1;
        }

        return ['rows' => $normalized, 'summary' => $summary];
    }

    /**
     * @param  array<string,mixed>  $raw
     * @return array<string,mixed>
     */
    private function normalizeRaw(array $raw, int $index): array
    {
        $nama = VolumeParser::clean((string) ($raw['nama_barang'] ?? ''));
        $merk = VolumeParser::clean((string) ($raw['merk'] ?? ''));
        if ($merk === '-' || $merk === '—' || $merk === '–') {
            $merk = '';
        }

        $vol = VolumeParser::split($raw['jumlah'] ?? null, $raw['satuan'] ?? null);
        $kode = VolumeParser::clean((string) ($raw['kode_barang'] ?? ''));
        $confidence = strtolower((string) ($raw['confidence'] ?? 'medium'));
        if (! in_array($confidence, ['high', 'medium', 'low'], true)) {
            $confidence = 'medium';
        }

        $notes = [];
        $needs = (bool) ($raw['needs_review'] ?? false) || $vol['needs_review'];

        if ($nama === '') {
            $needs = true;
            $notes[] = 'Nama barang kosong atau tidak terbaca.';
        }
        if ($vol['satuan'] === '') {
            $needs = true;
            $notes[] = 'Satuan tidak terbaca.';
        }
        if ($vol['jumlah'] === null) {
            $needs = true;
            $notes[] = 'Jumlah tidak terbaca.';
        }
        if ($confidence === 'low') {
            $needs = true;
            $notes[] = 'Akurasi OCR rendah — periksa nama, jumlah, dan satuan.';
        }

        $status = $needs ? 'perlu_diperiksa' : 'ok';

        return [
            'uid' => 'r' . ($index + 1),
            'no' => isset($raw['no']) && is_numeric($raw['no']) ? (int) $raw['no'] : ($index + 1),
            'nama_barang' => $nama,
            'merk' => $merk,
            'jumlah' => $vol['jumlah'] ?? 0,
            'satuan' => $vol['satuan'],
            'kode_barang' => $kode,
            'status' => $status,
            'status_label' => self::labelFor($status),
            'notes' => $notes,
            'existing_id' => null,
            'source_page' => (int) ($raw['source_page'] ?? 1),
            'confidence' => $confidence,
        ];
    }

    public static function labelFor(string $status): string
    {
        return match ($status) {
            'ok' => 'Siap',
            'perlu_diperiksa' => 'Perlu diperiksa',
            'sudah_ada' => 'Sudah ada',
            'duplikat_batch' => 'Duplikat batch',
            default => $status,
        };
    }
}
