<?php

declare(strict_types=1);

namespace App\Services\BMN;

use App\Models\BmnSatker;
use App\Models\BMN\AsetBmn;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class AsetBmnImportService
{
    /**
     * @return array{
     *     count: int,
     *     errors: list<string>,
     *     skipped_count: int,
     *     skipped_rows: list<int>,
     *     duplicate_nup_errors: list<string>
     * }
     */
    public function importFromUpload(UploadedFile $file): array
    {
        $path = $file->getRealPath();
        if ($path === false) {
            return $this->emptyResult(['Berkas tidak dapat dibaca.']);
        }

        try {
            $spreadsheet = IOFactory::load($path);
        } catch (\Throwable) {
            return $this->emptyResult(['Berkas tidak dapat dibaca atau format tidak didukung.']);
        }
        $rows = $spreadsheet->getActiveSheet()->toArray();
        if ($rows === [] || $rows[0] === null) {
            return $this->emptyResult(['Berkas kosong.']);
        }

        $rawHeaders = $rows[0];
        $map = $this->normalizeHeaderMap($rawHeaders);
        $required = ['kode_barang', 'nama_barang', 'kategori_id', 'kondisi', 'satker_id'];
        foreach ($required as $key) {
            if (! in_array($key, $map, true)) {
                return $this->emptyResult([
                    'Kolom wajib tidak ditemukan: '.implode(', ', $required).'. Pakai unduhan template resmi.',
                ]);
            }
        }

        $dataRows = array_slice($rows, 1);
        $lineErrors = [];
        $skippedRows = [];
        $pendingInserts = [];

        foreach ($dataRows as $idx => $row) {
            if (! is_array($row) || $this->isRowKosong($row)) {
                continue;
            }

            $rowNumber = $idx + 2;
            $assoc = $this->rowToAssoc($rawHeaders, $row);
            $kode = isset($assoc['kode_barang']) ? trim((string) $assoc['kode_barang']) : '';
            if ($kode === '') {
                $lineErrors[] = "Baris {$rowNumber}: kode_barang wajib diisi.";
                $skippedRows[] = $rowNumber;

                continue;
            }

            $normalized = $this->normalizeAssoc($assoc);
            if ($normalized === null) {
                $lineErrors[] = "Baris {$rowNumber}: format data tidak valid (cek kategori_id, satker_id, nilai, tanggal).";
                $skippedRows[] = $rowNumber;

                continue;
            }

            $v = Validator::make($normalized, $this->rulesForRow());
            if ($v->fails()) {
                foreach ($v->errors()->all() as $msg) {
                    $lineErrors[] = "Baris {$rowNumber}: {$msg}";
                }
                $skippedRows[] = $rowNumber;

                continue;
            }

            $pendingInserts[] = ['line' => $rowNumber, 'data' => $normalized];
        }

        if ($pendingInserts === [] && $lineErrors === []) {
            return $this->emptyResult(['Tidak ada baris data yang diimpor.']);
        }

        if ($pendingInserts === []) {
            return [
                'count' => 0,
                'errors' => $lineErrors,
                'skipped_count' => $this->uniqueSortedCount($skippedRows),
                'skipped_rows' => $this->uniqueSortedRows($skippedRows),
                'duplicate_nup_errors' => [],
            ];
        }

        $inserted = 0;
        $userId = auth()->id();

        foreach ($pendingInserts as $item) {
            $rowNumber = $item['line'];
            $row = $item['data'];

            try {
                DB::transaction(function () use ($row, $userId): void {
                    $satker = BmnSatker::query()->findOrFail((int) $row['satker_id']);
                    $fotoJson = (isset($row['foto_url']) && is_string($row['foto_url']) && $row['foto_url'] !== '')
                        ? [['full' => $row['foto_url'], 'thumb' => $row['foto_url']]]
                        : null;
                    AsetBmn::create([
                        'kode_barang' => $row['kode_barang'],
                        'nup' => $row['nup'] ?? null,
                        'nama_barang' => $row['nama_barang'],
                        'kategori_id' => (int) $row['kategori_id'],
                        'nilai_perolehan' => $row['nilai_perolehan'] ?? null,
                        'tanggal_perolehan' => $row['tanggal_perolehan'] ?? null,
                        'kondisi' => $row['kondisi'],
                        'pengguna_ruangan' => $row['pengguna_ruangan'] ?? null,
                        'satker_id' => $satker->id,
                        'satker' => $satker->nama,
                        'lokasi' => $row['lokasi'] ?? null,
                        'lokasi_id' => null,
                        'status' => 'aktif',
                        'asset_tag_type' => $row['asset_tag_type'] ?? 'MANUAL',
                        'foto_provider' => $row['foto_provider'] ?? '',
                        'foto' => $fotoJson,
                        'created_by' => $userId,
                        'updated_by' => $userId,
                    ]);
                });
                $inserted++;
            } catch (QueryException $e) {
                $lineErrors[] = "Baris {$rowNumber} dilewati: terjadi kesalahan basis data ({$e->errorInfo[1]}).";
                $skippedRows[] = $rowNumber;
            }
        }

        return [
            'count' => $inserted,
            'errors' => $lineErrors,
            'skipped_count' => $this->uniqueSortedCount($skippedRows),
            'skipped_rows' => $this->uniqueSortedRows($skippedRows),
            'duplicate_nup_errors' => [],
        ];
    }

    /**
     * @param  list<string>  $errors
     * @return array{count: int, errors: list<string>, skipped_count: int, skipped_rows: list<int>, duplicate_nup_errors: list<string>}
     */
    private function emptyResult(array $errors): array
    {
        return [
            'count' => 0,
            'errors' => array_values($errors),
            'skipped_count' => 0,
            'skipped_rows' => [],
            'duplicate_nup_errors' => [],
        ];
    }

    /**
     * @param  list<int>  $rows
     */
    private function uniqueSortedCount(array $rows): int
    {
        return count(array_unique($rows));
    }

    /**
     * @param  list<int>  $rows
     * @return list<int>
     */
    private function uniqueSortedRows(array $rows): array
    {
        $u = array_unique($rows);
        sort($u);

        return array_values($u);
    }

    /**
     * @param  array<int, mixed>  $row
     */
    protected function isRowKosong(array $row): bool
    {
        foreach ($row as $c) {
            if ($c !== null && $c !== '' && trim((string) $c) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, mixed>  $row
     * @return array<string, mixed>
     */
    protected function rowToAssoc(array $headers, array $row): array
    {
        $out = [];
        foreach ($headers as $i => $h) {
            $key = $this->headerKey($h);
            if ($key === null) {
                continue;
            }
            $out[$key] = $row[$i] ?? null;
        }

        return $out;
    }

    /**
     * @param  array<int, mixed>  $rawHeaders
     * @return list<string>
     */
    protected function normalizeHeaderMap(array $rawHeaders): array
    {
        $m = [];
        foreach ($rawHeaders as $h) {
            $k = $this->headerKey($h);
            if ($k !== null) {
                $m[] = $k;
            }
        }

        return $m;
    }

    private function headerKey(mixed $header): ?string
    {
        if ($header === null) {
            return null;
        }
        $h = mb_strtolower(trim((string) $header));
        $h = str_replace([' ', '-'], ['_', '_'], $h);
        if ($h === 'pengguna' || $h === 'pengguna_ruangan') {
            return 'pengguna_ruangan';
        }
        if (in_array($h, [
            'kode_barang', 'nup', 'nama_barang', 'kategori_id', 'nilai_perolehan',
            'tanggal_perolehan', 'kondisi', 'pengguna_ruangan', 'satker_id', 'lokasi',
            'foto_url',
        ], true)) {
            return $h;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $assoc
     * @return array<string, mixed>|null
     */
    protected function normalizeAssoc(array $assoc): ?array
    {
        $kategoriId = $assoc['kategori_id'] ?? null;
        if (is_string($kategoriId) && is_numeric($kategoriId)) {
            $kategoriId = (int) round((float) $kategoriId);
        } elseif (is_float($kategoriId) || is_int($kategoriId)) {
            $kategoriId = (int) $kategoriId;
        }
        if (! is_int($kategoriId) || $kategoriId < 1) {
            return null;
        }

        $rawSid = $assoc['satker_id'] ?? null;
        if ($rawSid === null || $rawSid === '') {
            $sid = null;
        } elseif (is_string($rawSid) && is_numeric($rawSid)) {
            $sid = (int) round((float) $rawSid);
        } elseif (is_int($rawSid) || is_float($rawSid)) {
            $sid = (int) $rawSid;
        } else {
            $sid = null;
        }
        if ($sid === null || $sid < 1) {
            return null;
        }

        $nilai = $assoc['nilai_perolehan'] ?? null;
        if ($nilai === '' || $nilai === null) {
            $nilai = null;
        } elseif (is_string($nilai) && is_numeric($nilai)) {
            $nilai = (float) $nilai;
        } elseif (is_int($nilai) || is_float($nilai)) {
            $nilai = (float) $nilai;
        } else {
            return null;
        }

        $tgl = $assoc['tanggal_perolehan'] ?? null;
        if ($tgl === '' || $tgl === null) {
            $tgl = null;
        } elseif (is_numeric($tgl) && (float) $tgl > 200) {
            try {
                $tgl = ExcelDate::excelToDateTimeObject((float) $tgl);
                $tgl = $tgl instanceof \DateTimeInterface
                    ? Carbon::instance($tgl)->format('Y-m-d')
                    : null;
            } catch (\Throwable $e) {
                return null;
            }
        } elseif (is_string($tgl) && is_numeric($tgl) && (float) $tgl > 200) {
            try {
                $tgl = ExcelDate::excelToDateTimeObject((float) $tgl);
                $tgl = $tgl instanceof \DateTimeInterface
                    ? Carbon::instance($tgl)->format('Y-m-d')
                    : null;
            } catch (\Throwable $e) {
                return null;
            }
        } elseif (is_string($tgl)) {
            $parsed = $this->parseTanggalString($tgl);
            if ($parsed === null) {
                return null;
            }
            $tgl = $parsed;
        } elseif ($tgl instanceof \DateTimeInterface) {
            $tgl = Carbon::instance($tgl)->format('Y-m-d');
        } else {
            return null;
        }

        $kondisi = isset($assoc['kondisi']) ? trim(mb_strtolower((string) $assoc['kondisi'])) : '';
        $kondisi = str_replace(' ', '_', $kondisi);
        if (in_array($kondisi, ['rusakringan', 'rusak_rignan', 'r.r'], true)) {
            $kondisi = 'rusak_ringan';
        } elseif (in_array($kondisi, ['rusakberat', 'r.b'], true)) {
            $kondisi = 'rusak_berat';
        }

        $kode = isset($assoc['kode_barang']) ? trim((string) $assoc['kode_barang']) : '';
        $nama = isset($assoc['nama_barang']) ? trim((string) $assoc['nama_barang']) : '';
        $nupRaw = $assoc['nup'] ?? null;
        if ($nupRaw === null || $nupRaw === '') {
            $nup = null;
        } elseif (is_float($nupRaw) || is_int($nupRaw)) {
            $nup = $this->normalizeNupStringFromScalar($nupRaw);
        } else {
            $nup = trim((string) $nupRaw);
            if ($nup === '') {
                $nup = null;
            }
        }
        $peng = isset($assoc['pengguna_ruangan']) && $assoc['pengguna_ruangan'] !== null
            ? trim((string) $assoc['pengguna_ruangan']) : null;
        if ($peng === '') {
            $peng = null;
        }
        $lok = isset($assoc['lokasi']) && $assoc['lokasi'] !== null ? trim((string) $assoc['lokasi']) : null;
        if ($lok === '') {
            $lok = null;
        }

        $fotoUrl = isset($assoc['foto_url']) && $assoc['foto_url'] !== null ? trim((string) $assoc['foto_url']) : null;
        if ($fotoUrl === '') {
            $fotoUrl = null;
        }

        return [
            'kode_barang' => $kode,
            'nama_barang' => $nama,
            'kategori_id' => $kategoriId,
            'nilai_perolehan' => $nilai,
            'tanggal_perolehan' => $tgl,
            'kondisi' => $kondisi,
            'pengguna_ruangan' => $peng,
            'satker_id' => $sid,
            'nup' => $nup,
            'lokasi' => $lok,
            'foto_url' => $fotoUrl,
        ];
    }

    private function normalizeNupStringFromScalar(int|float $nupRaw): ?string
    {
        if (is_int($nupRaw) || (is_float($nupRaw) && floor($nupRaw) === $nupRaw)) {
            return (string) (int) $nupRaw;
        }

        $s = trim((string) $nupRaw);

        return $s === '' ? null : $s;
    }

    private function parseTanggalString(string $s): ?string
    {
        $s = trim($s);
        if ($s === '') {
            return null;
        }
        try {
            $c = Carbon::parse($s);

            return $c->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * @return array<string, list<string|object>>
     */
    protected function rulesForRow(): array
    {
        return [
            'kode_barang' => ['required', 'string', 'max:255'],
            'nama_barang' => ['required', 'string', 'max:255'],
            'kategori_id' => ['required', 'integer', 'exists:kategori_aset,id'],
            'nilai_perolehan' => ['nullable', 'numeric'],
            'tanggal_perolehan' => ['nullable', 'date'],
            'kondisi' => ['required', 'in:baik,rusak_ringan,rusak_berat'],
            'satker_id' => ['required', 'integer', 'exists:bmn_satker,id'],
            'pengguna_ruangan' => ['nullable', 'string', 'max:200'],
            'nup' => ['nullable', 'string', 'max:50'],
            'lokasi' => ['nullable', 'string', 'max:255'],
            'foto_url' => ['nullable', 'string', 'url', 'max:2048'],
        ];
    }
}
