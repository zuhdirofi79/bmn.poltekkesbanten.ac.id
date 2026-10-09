<?php

declare(strict_types=1);

namespace App\Imports;

use App\Models\Persediaan\PersediaanBarang;
use App\Models\Persediaan\PersediaanStockMovement;
use App\Support\Persediaan\UnitKerja;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

class PersediaanMasterBarangImport
{
    /** @var list<string> */
    private const REQUIRED_HEADERS = [
        'kode_barang',
        'nama_barang',
        'unit_kerja',
        'satuan',
        'stok_awal',
        'harga_satuan',
        'status',
    ];

    public function __construct(
        private readonly ?string $adminSatker,
    ) {
    }

    /**
     * @return array{
     *     created: int,
     *     updated: int,
     *     skipped: int,
     *     errors: list<array{baris: int, kolom: string, pesan: string}>
     * }
     */
    public function import(UploadedFile $file): array
    {
        $rows = Excel::toArray(null, $file)[0] ?? [];
        if ($rows === []) {
            return $this->result(0, 0, 0, [[
                'baris'  => 1,
                'kolom'  => 'file',
                'pesan'  => 'Berkas Excel kosong.',
            ]]);
        }

        $headerRow = $rows[0] ?? [];
        $map = $this->headerMap($headerRow);
        foreach (self::REQUIRED_HEADERS as $required) {
            if (! array_key_exists($required, $map)) {
                return $this->result(0, 0, 0, [[
                    'baris'  => 1,
                    'kolom'  => $required,
                    'pesan'  => 'Kolom wajib tidak ditemukan. Gunakan template resmi.',
                ]]);
            }
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        foreach (array_slice($rows, 1) as $idx => $row) {
            $excelRow = $idx + 2;
            if (! is_array($row) || $this->isEmptyRow($row, $map)) {
                continue;
            }

            $assoc = $this->rowToAssoc($row, $map);
            $normalized = $this->normalizeRow($assoc);
            $validator = Validator::make($normalized, $this->rules());
            if ($validator->fails()) {
                $skipped++;
                foreach ($validator->errors()->keys() as $field) {
                    $errors[] = [
                        'baris'  => $excelRow,
                        'kolom'  => (string) $field,
                        'pesan'  => (string) $validator->errors()->first($field),
                    ];
                }

                continue;
            }

            $accessError = $this->unitAccessError($normalized['unit_kerja']);
            if ($accessError !== null) {
                $skipped++;
                $errors[] = ['baris' => $excelRow, 'kolom' => 'unit_kerja', 'pesan' => $accessError];

                continue;
            }

            $payload = [
                'kode_barang'  => $normalized['kode_barang'],
                'nama_barang'  => $normalized['nama_barang'],
                'unit_kerja'   => $normalized['unit_kerja'],
                'satuan'       => $normalized['satuan'],
                'stok_awal'    => (int) $normalized['stok_awal'],
                'harga_satuan' => (float) $normalized['harga_satuan'],
                'is_active'    => $normalized['is_active'],
            ];

            try {
                DB::transaction(function () use ($payload): void {
                    $barang = PersediaanBarang::create($payload);

                    if ((int) $payload['stok_awal'] > 0) {
                        PersediaanStockMovement::create([
                            'barang_id'  => $barang->id,
                            'unit_kerja' => $barang->unit_kerja,
                            'tipe'       => PersediaanStockMovement::TIPE_STOK_AWAL,
                            'ref_table'  => 'persediaan_barang',
                            'ref_id'     => $barang->id,
                            'qty_masuk'  => (int) $payload['stok_awal'],
                            'keterangan' => 'Stok awal import master barang',
                        ]);
                    }
                });
                $created++;
            } catch (\Throwable $e) {
                report($e);
                $skipped++;
                $errors[] = [
                    'baris'  => $excelRow,
                    'kolom'  => 'kode_barang',
                    'pesan'  => 'Baris tidak dapat disimpan: ' . $e->getMessage(),
                ];
            }
        }

        return $this->result($created, $updated, $skipped, $errors);
    }

    /**
     * @param  array<int, mixed>  $headerRow
     * @return array<string, int>
     */
    private function headerMap(array $headerRow): array
    {
        $map = [];
        foreach ($headerRow as $i => $cell) {
            $key = strtolower(trim((string) $cell));
            if ($key !== '') {
                $map[$key] = (int) $i;
            }
        }

        return $map;
    }

    /**
     * @param  array<int, mixed>  $row
     * @param  array<string, int>  $map
     */
    private function isEmptyRow(array $row, array $map): bool
    {
        foreach (['kode_barang', 'nama_barang', 'unit_kerja'] as $key) {
            $idx = $map[$key] ?? null;
            if ($idx !== null && trim((string) ($row[$idx] ?? '')) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int, mixed>  $row
     * @param  array<string, int>  $map
     * @return array<string, mixed>
     */
    private function rowToAssoc(array $row, array $map): array
    {
        $assoc = [];
        foreach (self::REQUIRED_HEADERS as $key) {
            $idx = $map[$key] ?? null;
            $assoc[$key] = $idx !== null ? ($row[$idx] ?? null) : null;
        }

        return $assoc;
    }

    /**
     * @param  array<string, mixed>  $assoc
     * @return array<string, mixed>
     */
    private function normalizeRow(array $assoc): array
    {
        $statusRaw = strtolower(trim((string) ($assoc['status'] ?? '')));

        return [
            'kode_barang'  => trim((string) ($assoc['kode_barang'] ?? '')),
            'nama_barang'  => trim((string) ($assoc['nama_barang'] ?? '')),
            'unit_kerja'   => trim((string) ($assoc['unit_kerja'] ?? '')),
            'satuan'       => trim((string) ($assoc['satuan'] ?? '')),
            'stok_awal'    => $this->numericValue($assoc['stok_awal'] ?? 0),
            'harga_satuan' => $this->numericValue($assoc['harga_satuan'] ?? '', 0),
            'status'       => $statusRaw,
            'is_active'    => $statusRaw !== 'nonaktif',
        ];
    }

    private function numericValue(mixed $value, mixed $default = 0): float|int|string
    {
        if ($value === null || $value === '') {
            return $default;
        }

        return is_numeric($value) ? $value + 0 : $default;
    }

    private function unitAccessError(string $unitKerja): ?string
    {
        if ($this->adminSatker === null || $this->adminSatker === '') {
            return null;
        }

        if ($unitKerja === $this->adminSatker) {
            return null;
        }

        return 'Unit kerja tidak sesuai hak akses admin.';
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function rules(): array
    {
        return [
            'kode_barang'  => ['required', 'string', 'max:100'],
            'nama_barang'  => ['required', 'string', 'max:255'],
            'unit_kerja'   => ['required', Rule::in(UnitKerja::all())],
            'satuan'       => ['required', 'string', 'max:50'],
            'stok_awal'    => ['required', 'numeric', 'min:0'],
            'harga_satuan' => ['nullable', 'numeric', 'min:0'],
            'status'       => ['nullable', 'string', Rule::in(['aktif', 'nonaktif', ''])],
            'is_active'    => ['boolean'],
        ];
    }

    /**
     * @param  list<array{baris: int, kolom: string, pesan: string}>  $errors
     * @return array{created: int, updated: int, skipped: int, errors: list<array{baris: int, kolom: string, pesan: string}>}
     */
    private function result(int $created, int $updated, int $skipped, array $errors): array
    {
        return [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'errors'  => $errors,
        ];
    }
}
