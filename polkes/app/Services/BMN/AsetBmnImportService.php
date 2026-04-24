<?php

declare(strict_types=1);

namespace App\Services\BMN;

use App\Models\BmnSatker;
use App\Models\BMN\AsetBmn;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class AsetBmnImportService
{
    /**
     * @return array{ok: true, count: int}|array{ok: false, errors: list<string>, count: int}
     */
    public function importFromUpload(UploadedFile $file): array
    {
        $path = $file->getRealPath();
        if ($path === false) {
            return ['ok' => false, 'errors' => ['Berkas tidak dapat dibaca.'], 'count' => 0];
        }

        $spreadsheet = IOFactory::load($path);
        $rows = $spreadsheet->getActiveSheet()->toArray();
        if ($rows === [] || $rows[0] === null) {
            return ['ok' => false, 'errors' => ['Berkas kosong.'], 'count' => 0];
        }

        $rawHeaders = $rows[0];
        $map = $this->normalizeHeaderMap($rawHeaders);
        $required = ['kode_barang', 'nama_barang', 'kategori_id', 'kondisi', 'satker_id'];
        foreach ($required as $key) {
            if (! in_array($key, $map, true)) {
                return [
                    'ok' => false,
                    'errors' => ['Kolom wajib tidak ditemukan: '.implode(', ', $required).'. Pakai unduhan template resmi.'],
                    'count' => 0,
                ];
            }
        }

        $dataRows = array_slice($rows, 1);
        $lineErrors = [];
        $normalizedRows = [];
        $kodeDalamBerkas = [];

        foreach ($dataRows as $idx => $row) {
            if (! is_array($row) || $this->isRowKosong($row)) {
                continue;
            }

            $rowNumber = $idx + 2;
            $assoc = $this->rowToAssoc($rawHeaders, $row);
            $kode = isset($assoc['kode_barang']) ? trim((string) $assoc['kode_barang']) : '';
            if ($kode === '') {
                $lineErrors[] = "Baris {$rowNumber}: kode_barang wajib diisi.";

                continue;
            }

            if (isset($kodeDalamBerkas[$kode])) {
                $lineErrors[] = "Baris {$rowNumber}: kode_barang '{$kode}' duplikat (sama dengan baris {$kodeDalamBerkas[$kode]}).";

                continue;
            }
            $kodeDalamBerkas[$kode] = $rowNumber;

            $normalized = $this->normalizeAssoc($assoc);
            if ($normalized === null) {
                $lineErrors[] = "Baris {$rowNumber}: format data tidak valid (cek kategori_id, satker_id, nilai, tanggal).";

                continue;
            }

            $v = Validator::make($normalized, $this->rulesForRow());
            if ($v->fails()) {
                foreach ($v->errors()->all() as $msg) {
                    $lineErrors[] = "Baris {$rowNumber}: {$msg}";
                }

                continue;
            }

            $normalizedRows[] = $normalized;
        }

        if ($normalizedRows === [] && $lineErrors === []) {
            return ['ok' => false, 'errors' => ['Tidak ada baris data yang diimpor.'], 'count' => 0];
        }

        if ($lineErrors !== []) {
            return ['ok' => false, 'errors' => $lineErrors, 'count' => 0];
        }

        $inserted = 0;
        $userId = auth()->id();

        DB::transaction(function () use ($normalizedRows, $userId, &$inserted): void {
            foreach ($normalizedRows as $row) {
                $satker = BmnSatker::query()->findOrFail((int) $row['satker_id']);
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
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]);
                $inserted++;
            }
        });

        return ['ok' => true, 'count' => $inserted];
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
        $nup = isset($assoc['nup']) && $assoc['nup'] !== null && (string) $assoc['nup'] !== '' ? trim((string) $assoc['nup']) : null;
        $peng = isset($assoc['pengguna_ruangan']) && $assoc['pengguna_ruangan'] !== null
            ? trim((string) $assoc['pengguna_ruangan']) : null;
        if ($peng === '') {
            $peng = null;
        }
        $lok = isset($assoc['lokasi']) && $assoc['lokasi'] !== null ? trim((string) $assoc['lokasi']) : null;
        if ($lok === '') {
            $lok = null;
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
        ];
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
            'kode_barang' => ['required', 'string', 'max:255', 'unique:aset_bmn,kode_barang'],
            'nama_barang' => ['required', 'string', 'max:255'],
            'kategori_id' => ['required', 'integer', 'exists:kategori_aset,id'],
            'nilai_perolehan' => ['nullable', 'numeric'],
            'tanggal_perolehan' => ['nullable', 'date'],
            'kondisi' => ['required', 'in:baik,rusak_ringan,rusak_berat'],
            'satker_id' => ['required', 'integer', 'exists:bmn_satker,id'],
            'pengguna_ruangan' => ['nullable', 'string', 'max:200'],
            'nup' => ['nullable', 'string', 'max:50'],
            'lokasi' => ['nullable', 'string', 'max:255'],
        ];
    }
}
