<?php

declare(strict_types=1);

namespace App\Services\Persediaan;

use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Membaca satu sheet Data Pembelian.
 * Seluruh baris dibaca sekaligus supaya no_faktur yang sama tidak terpecah antar batch.
 */
final class PembelianExcelReader
{
    public const MAX_ROWS = 5000;

    /** @var list<string> */
    private const REQUIRED = [
        'no_faktur',
        'tanggal',
        'penyedia',
        'unit_kerja',
        'kode_barang',
        'nama_barang',
        'satuan',
        'harga_satuan',
        'jumlah',
    ];

    /** @var array<string,string> */
    private const ALIASES = [
        'no_faktur'     => 'no_faktur',
        'nomor_faktur'  => 'no_faktur',
        'nofaktur'      => 'no_faktur',
        'tanggal'       => 'tanggal',
        'tgl'           => 'tanggal',
        'penyedia'      => 'penyedia',
        'unit_kerja'    => 'unit_kerja',
        'unit'          => 'unit_kerja',
        'kode_barang'   => 'kode_barang',
        'kode'          => 'kode_barang',
        'nama_barang'   => 'nama_barang',
        'nama'          => 'nama_barang',
        'satuan'        => 'satuan',
        'harga_satuan'  => 'harga_satuan',
        'harga'         => 'harga_satuan',
        'jumlah'        => 'jumlah',
        'qty'           => 'jumlah',
    ];

    /**
     * @return list<array<string,mixed>>
     */
    public function read(UploadedFile $file): array
    {
        try {
            $sheets = Excel::toCollection(null, $file);
        } catch (\Throwable $e) {
            throw new PembelianImportException(
                'File Excel tidak dapat dibaca. Periksa template lalu coba lagi.',
                0,
                $e,
            );
        }

        foreach ($sheets as $sheet) {
            $parsed = $this->parseSheet($this->sheetToList($sheet));
            if ($parsed !== null) {
                return $parsed;
            }
        }

        throw new PembelianImportException(
            'Template tidak sesuai. Kolom wajib: ' . implode(', ', self::REQUIRED) . '. Unduh template terbaru.',
        );
    }

    /**
     * @param  list<list<mixed>>  $rows
     * @return list<array<string,mixed>>|null
     */
    public function parseSheet(array $rows): ?array
    {
        $headerIndex = null;
        $map = null;

        foreach ($rows as $index => $row) {
            if ($this->rowEmpty($row)) {
                continue;
            }
            $map = $this->mapHeaders($row);
            if ($map === null) {
                return null;
            }
            $headerIndex = $index;
            break;
        }

        if ($headerIndex === null || $map === null) {
            return null;
        }

        $parsed = [];
        for ($i = $headerIndex + 1, $count = count($rows); $i < $count; $i++) {
            $row = $rows[$i];
            if ($this->rowEmpty($row)) {
                continue;
            }
            $item = ['line' => $i + 1];
            foreach ($map as $field => $col) {
                $item[$field] = $row[$col] ?? null;
            }
            $parsed[] = $item;
        }

        if ($parsed === []) {
            throw new PembelianImportException('Tidak ada baris data pada file Excel.');
        }

        if (count($parsed) > self::MAX_ROWS) {
            throw new PembelianImportException(
                'File melebihi ' . self::MAX_ROWS . ' baris data. Pecah file Excel lalu import per bagian.',
            );
        }

        return $parsed;
    }

    /**
     * @param  iterable<mixed>  $sheet
     * @return list<list<mixed>>
     */
    private function sheetToList(iterable $sheet): array
    {
        $list = [];
        foreach ($sheet as $row) {
            if ($row instanceof \Illuminate\Support\Collection) {
                $list[] = $row->values()->all();
                continue;
            }
            $list[] = array_values(is_array($row) ? $row : []);
        }

        return $list;
    }

    /**
     * @param  list<mixed>  $row
     * @return array<string,int>|null
     */
    private function mapHeaders(array $row): ?array
    {
        $map = [];
        foreach ($row as $index => $cell) {
            $label = $this->headerLabel($cell);
            if ($label === '' || ! isset(self::ALIASES[$label])) {
                continue;
            }
            $field = self::ALIASES[$label];
            if (! isset($map[$field])) {
                $map[$field] = (int) $index;
            }
        }

        foreach (self::REQUIRED as $field) {
            if (! isset($map[$field])) {
                return null;
            }
        }

        return $map;
    }

    private function headerLabel(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface || is_array($value)) {
            return '';
        }
        $s = strtolower(trim((string) $value));
        $s = preg_replace('/[^a-z0-9]+/', '_', $s) ?? $s;

        return trim($s, '_');
    }

    /**
     * @param  list<mixed>  $row
     */
    private function rowEmpty(array $row): bool
    {
        foreach ($row as $cell) {
            if ($cell instanceof \DateTimeInterface) {
                return false;
            }
            if ($cell === null) {
                continue;
            }
            if (is_string($cell) && trim($cell) === '') {
                continue;
            }
            if ($cell !== '') {
                return false;
            }
        }

        return true;
    }
}
