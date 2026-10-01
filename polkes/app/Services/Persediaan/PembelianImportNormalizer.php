<?php

declare(strict_types=1);

namespace App\Services\Persediaan;

use App\Support\Persediaan\PersediaanDateParser;
use App\Support\Persediaan\UnitKerja;

/**
 * Mengelompokkan baris Excel menjadi transaksi berdasarkan no_faktur.
 * Tidak menulis database dan tidak membuat master barang.
 */
final class PembelianImportNormalizer
{
    /**
     * @param  list<array<string,mixed>>  $rows
     * @param  array<string,array{id:int,kode:string,nama:string,satuan:string,harga:float}>  $masters
     * @param  list<string>  $existingFakturs
     * @return array{
     *   can_import: bool,
     *   messages: list<string>,
     *   rows: list<array<string,mixed>>,
     *   groups: list<array{header: array<string,mixed>, items: list<array<string,mixed>>}>
     * }
     */
    public function build(array $rows, array $masters, array $existingFakturs, ?string $lockedUnit): array
    {
        $existing = [];
        foreach ($existingFakturs as $faktur) {
            $existing[mb_strtolower(trim((string) $faktur))] = true;
        }

        $parsed = [];
        foreach ($rows as $row) {
            $parsed[] = $this->parseRow($row, $masters, $lockedUnit);
        }

        /** @var array<string,list<int>> $groups */
        $groups = [];
        $order = [];
        foreach ($parsed as $index => $row) {
            if ($row['no_faktur_key'] === '') {
                continue;
            }
            $key = $row['no_faktur_key'];
            if (! isset($groups[$key])) {
                $groups[$key] = [];
                $order[] = $key;
            }
            $groups[$key][] = $index;
        }

        foreach ($order as $key) {
            $this->applyGroupRules($parsed, $groups[$key], isset($existing[$key]));
        }

        $messages = [];
        $persist = [];
        $blocked = $parsed === [];

        foreach ($parsed as $row) {
            if ($row['status'] === 'ERROR' || $row['status'] === 'DUPLIKAT') {
                $blocked = true;
                if ($row['error'] !== '') {
                    $messages[] = 'Baris ' . $row['line'] . ': ' . $row['error'];
                }
            }
        }

        if (! $blocked) {
            foreach ($order as $key) {
                $persist[] = $this->persistGroup($parsed, $groups[$key]);
            }
        }

        if ($persist === []) {
            $blocked = true;
        }

        $dupLabels = [];
        foreach ($parsed as $row) {
            if ($row['status'] === 'DUPLIKAT' || str_contains((string) $row['error'], 'sudah terdaftar')) {
                $dupLabels[(string) $row['no_faktur']] = true;
            }
        }
        foreach (array_keys($dupLabels) as $label) {
            array_unshift($messages, 'Transaksi dengan No. Faktur ' . $label . ' sudah terdaftar.');
        }

        return [
            'can_import' => ! $blocked,
            'messages'   => $messages,
            'rows'       => $parsed,
            'groups'     => $blocked ? [] : $persist,
        ];
    }

    /**
     * @param  array<string,mixed>  $row
     * @param  array<string,array{id:int,kode:string,nama:string,satuan:string,harga:float}>  $masters
     * @return array<string,mixed>
     */
    private function parseRow(array $row, array $masters, ?string $lockedUnit): array
    {
        $line = (int) ($row['line'] ?? 0);
        $faktur = trim((string) ($row['no_faktur'] ?? ''));
        $penyedia = trim((string) ($row['penyedia'] ?? ''));
        $kode = trim((string) ($row['kode_barang'] ?? ''));
        $nama = trim((string) ($row['nama_barang'] ?? ''));
        $satuan = trim((string) ($row['satuan'] ?? ''));
        $unitRaw = trim((string) ($row['unit_kerja'] ?? ''));
        $unit = $this->resolveUnit($unitRaw);
        $tanggalRaw = $row['tanggal'] ?? '';
        $tanggalIso = PersediaanDateParser::toDateString($tanggalRaw);
        $harga = $this->parseHarga($row['harga_satuan'] ?? null);
        $jumlah = $this->parseJumlah($row['jumlah'] ?? null);

        $errors = [];
        if ($faktur === '') {
            $errors[] = 'No. Faktur wajib diisi.';
        }
        if ($this->blank($tanggalRaw)) {
            $errors[] = 'tanggal wajib diisi. Format yang diterima: DD/MM/YYYY';
        } elseif ($tanggalIso === null) {
            $errors[] = 'tanggal "' . $this->displayRaw($tanggalRaw) . '" tidak valid. Format yang diterima: DD/MM/YYYY';
        }
        if ($penyedia === '') {
            $errors[] = 'penyedia wajib diisi.';
        }
        if ($unitRaw === '') {
            $errors[] = 'unit kerja wajib diisi.';
        } elseif ($unit === null) {
            $errors[] = 'unit kerja tidak valid.';
        } elseif ($lockedUnit !== null && $unit !== $lockedUnit) {
            $errors[] = 'unit kerja tidak sesuai hak akses.';
        }
        if ($nama === '') {
            $errors[] = 'nama barang wajib diisi.';
        }
        if ($satuan === '') {
            $errors[] = 'satuan wajib diisi.';
        }
        if ($harga === null) {
            $errors[] = 'harga satuan harus berupa angka.';
        } elseif ($harga < 0) {
            $errors[] = 'harga satuan tidak boleh negatif.';
        }
        if ($jumlah === null) {
            $errors[] = 'jumlah harus lebih besar dari 0.';
        } elseif ($jumlah <= 0) {
            $errors[] = 'jumlah harus lebih besar dari 0.';
        }

        $barangId = null;
        $status = 'MANUAL';
        if ($unit !== null && $kode !== '') {
            $master = $masters[$unit . '|' . mb_strtoupper($kode)] ?? null;
            if ($master !== null) {
                $barangId = (int) $master['id'];
                $status = 'MASTER';
                if ($nama === '') {
                    $nama = (string) $master['nama'];
                }
                if ($satuan === '') {
                    $satuan = (string) $master['satuan'];
                }
            }
        }

        if ($errors !== []) {
            $status = 'ERROR';
            $barangId = null;
        }

        $subtotal = ($harga !== null && $jumlah !== null && $jumlah > 0 && $harga >= 0)
            ? round($harga * $jumlah, 2)
            : null;

        return [
            'line'           => $line,
            'no_faktur'      => $faktur,
            'no_faktur_key'  => mb_strtolower($faktur),
            'tanggal_iso'    => $tanggalIso,
            'tanggal'        => $tanggalIso !== null ? PersediaanDateParser::formatIndonesia($tanggalIso) : $this->displayRaw($tanggalRaw),
            'penyedia'       => $penyedia,
            'unit_kerja'     => $unit,
            'unit_label'     => $unit !== null ? $unit : $unitRaw,
            'kode_barang'    => $kode,
            'nama_barang'    => $nama,
            'satuan'         => $satuan,
            'harga_satuan'   => $harga,
            'jumlah'         => $jumlah,
            'subtotal'       => $subtotal,
            'barang_id'      => $barangId,
            'status'         => $status,
            'error'          => implode(' ', $errors),
        ];
    }

    /**
     * @param  list<array<string,mixed>>  $parsed
     * @param  list<int>  $indexes
     */
    private function applyGroupRules(array &$parsed, array $indexes, bool $isDuplicate): void
    {
        $tanggal = null;
        $penyedia = null;
        $unit = null;
        $label = (string) $parsed[$indexes[0]]['no_faktur'];

        foreach ($indexes as $index) {
            $row = $parsed[$index];
            if ($row['tanggal_iso'] !== null) {
                if ($tanggal === null) {
                    $tanggal = $row['tanggal_iso'];
                } elseif ($tanggal !== $row['tanggal_iso']) {
                    $this->addError($parsed[$index], 'tanggal tidak sama dengan baris lain pada No. Faktur ' . $label . '.');
                }
            }
            if ($row['penyedia'] !== '') {
                $key = mb_strtolower((string) $row['penyedia']);
                if ($penyedia === null) {
                    $penyedia = $key;
                } elseif ($penyedia !== $key) {
                    $this->addError($parsed[$index], 'penyedia tidak sama dengan baris lain pada No. Faktur ' . $label . '.');
                }
            }
            if ($row['unit_kerja'] !== null) {
                if ($unit === null) {
                    $unit = $row['unit_kerja'];
                } elseif ($unit !== $row['unit_kerja']) {
                    $this->addError($parsed[$index], 'unit kerja tidak sama dengan baris lain pada No. Faktur ' . $label . '.');
                }
            }
        }

        if ($isDuplicate) {
            foreach ($indexes as $index) {
                $this->addError(
                    $parsed[$index],
                    'No. Faktur ' . $label . ' sudah terdaftar.',
                    'DUPLIKAT',
                );
            }
        }
    }

    /**
     * @param  array<string,mixed>  $row
     */
    private function addError(array &$row, string $message, string $status = 'ERROR'): void
    {
        $row['error'] = trim(($row['error'] !== '' ? $row['error'] . ' ' : '') . $message);
        if ($status === 'ERROR' || $row['status'] !== 'ERROR') {
            if ($row['status'] !== 'ERROR') {
                $row['status'] = $status;
            }
        }
        if ($status === 'ERROR') {
            $row['status'] = 'ERROR';
            $row['barang_id'] = null;
        }
    }

    /**
     * @param  list<array<string,mixed>>  $parsed
     * @param  list<int>  $indexes
     * @return array{header: array<string,mixed>, items: list<array<string,mixed>>}
     */
    private function persistGroup(array $parsed, array $indexes): array
    {
        $first = $parsed[$indexes[0]];
        $items = [];
        $total = 0.0;

        foreach ($indexes as $index) {
            $row = $parsed[$index];
            $subtotal = round((float) $row['harga_satuan'] * (int) $row['jumlah'], 2);
            $total += $subtotal;
            $items[] = [
                'barang_id'    => $row['barang_id'],
                'kode_barang'  => (string) $row['kode_barang'],
                'nama_barang'  => (string) $row['nama_barang'],
                'satuan'       => (string) $row['satuan'],
                'harga_satuan' => (float) $row['harga_satuan'],
                'jumlah'       => (int) $row['jumlah'],
                'subtotal'     => $subtotal,
            ];
        }

        return [
            'header' => [
                'nomor_faktur' => (string) $first['no_faktur'],
                'tanggal'      => (string) $first['tanggal_iso'],
                'penyedia'     => (string) $first['penyedia'],
                'unit_kerja'   => (string) $first['unit_kerja'],
                'total'        => round($total, 2),
            ],
            'items' => $items,
        ];
    }

    private function resolveUnit(string $raw): ?string
    {
        if ($raw === '') {
            return null;
        }

        foreach (UnitKerja::all() as $unit) {
            if (strcasecmp($raw, $unit) === 0 || strcasecmp($raw, UnitKerja::label($unit)) === 0) {
                return $unit;
            }
        }

        return null;
    }

    private function parseHarga(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $s = trim((string) $value);
        $s = str_replace(['Rp', 'rp', ' '], '', $s);
        if ($s === '') {
            return null;
        }
        if (is_numeric($s)) {
            return (float) $s;
        }
        if (preg_match('/^\d{1,3}(\.\d{3})+(,\d+)?$/', $s)) {
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);

            return (float) $s;
        }
        if (preg_match('/^\d+,\d+$/', $s)) {
            return (float) str_replace(',', '.', $s);
        }

        return null;
    }

    private function parseJumlah(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_int($value)) {
            return $value;
        }
        if (is_float($value)) {
            if (abs($value - round($value)) > 0.00001) {
                return null;
            }

            return (int) round($value);
        }

        $s = trim((string) $value);
        $s = str_replace(' ', '', $s);
        if (preg_match('/^\d+$/', $s)) {
            return (int) $s;
        }
        if (preg_match('/^\d+\.0+$/', $s)) {
            return (int) $s;
        }
        if (preg_match('/^\d{1,3}(\.\d{3})+$/', $s)) {
            return (int) str_replace('.', '', $s);
        }

        return null;
    }

    private function blank(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }
        if ($value instanceof \DateTimeInterface) {
            return false;
        }

        return trim((string) $value) === '';
    }

    private function displayRaw(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        if (is_float($value) || is_int($value)) {
            return rtrim(rtrim(sprintf('%.4F', (float) $value), '0'), '.');
        }

        return trim((string) $value);
    }
}
