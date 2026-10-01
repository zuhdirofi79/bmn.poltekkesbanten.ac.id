<?php

declare(strict_types=1);

namespace App\Services\Persediaan;

use App\Models\Persediaan\PersediaanBarang;
use App\Models\Persediaan\PersediaanPembelian;
use App\Models\Persediaan\PersediaanPembelianItem;
use Illuminate\Support\Facades\DB;

/**
 * Satu-satunya pintu tulis pembelian beserta stok.
 * Stok naik hanya lewat StockService::recordPembelianMasuk, sekali per detail yang punya master.
 */
final class PurchaseService
{
    public function __construct(private StockService $stock)
    {
    }

    /**
     * @param  list<array<string,mixed>>  $items
     * @return array{rows: list<array<string,mixed>>, errors: list<string>}
     */
    public function normalizeFormItems(array $items, string $unit): array
    {
        $masters = PersediaanBarang::query()
            ->where('unit_kerja', $unit)
            ->get()
            ->keyBy('id');

        $rows = [];
        $errors = [];
        $number = 0;

        foreach ($items as $row) {
            if (! is_array($row) || $this->formRowBlank($row)) {
                continue;
            }
            $number++;

            $mode = (string) ($row['mode'] ?? '');
            if ($mode === '') {
                $mode = ! empty($row['barang_id']) ? 'master' : 'manual';
            }
            $mode = $mode === 'manual' ? 'manual' : 'master';

            $jumlah = $this->wholeNumber($row['jumlah'] ?? null);
            if ($jumlah === null || $jumlah <= 0) {
                $errors[] = 'Baris ' . $number . ': jumlah harus lebih besar dari 0.';
                continue;
            }

            $hargaRaw = $row['harga_satuan'] ?? 0;
            if ($hargaRaw === '' || $hargaRaw === null) {
                $hargaRaw = 0;
            }
            if (! is_numeric($hargaRaw) || (float) $hargaRaw < 0) {
                $errors[] = 'Baris ' . $number . ': harga satuan harus berupa angka dan tidak boleh negatif.';
                continue;
            }
            $harga = round((float) $hargaRaw, 2);

            if ($mode === 'manual') {
                $nama = trim((string) ($row['nama_barang'] ?? ''));
                $satuan = trim((string) ($row['satuan'] ?? ''));
                $kode = trim((string) ($row['kode_barang'] ?? ''));
                if ($nama === '') {
                    $errors[] = 'Baris ' . $number . ': nama barang wajib diisi.';
                    continue;
                }
                if ($satuan === '') {
                    $errors[] = 'Baris ' . $number . ': satuan wajib diisi.';
                    continue;
                }
                $rows[] = $this->line(null, $kode, $nama, $satuan, $harga, $jumlah);
                continue;
            }

            $barang = $masters->get((int) ($row['barang_id'] ?? 0));
            if ($barang === null) {
                $errors[] = 'Baris ' . $number . ': pilih barang dari master, atau ganti mode ke Input Manual.';
                continue;
            }

            $rows[] = $this->line(
                (int) $barang->id,
                (string) $barang->kode_barang,
                (string) $barang->nama_barang,
                (string) $barang->satuan,
                $harga,
                $jumlah,
            );
        }

        if ($rows === [] && $errors === []) {
            $errors[] = 'Minimal satu item dengan jumlah lebih besar dari 0.';
        }

        return ['rows' => $rows, 'errors' => $errors];
    }

    /**
     * @param  array<string,mixed>  $header
     * @param  list<array<string,mixed>>  $rows
     */
    public function store(array $header, array $rows, ?int $userId): PersediaanPembelian
    {
        return DB::transaction(function () use ($header, $rows, $userId) {
            $nomor = trim((string) ($header['nomor_faktur'] ?? ''));
            if ($nomor === '') {
                throw new PurchaseException('No. Faktur wajib diisi.');
            }

            $exists = PersediaanPembelian::query()
                ->whereRaw('LOWER(nomor_faktur) = ?', [mb_strtolower($nomor)])
                ->lockForUpdate()
                ->exists();

            if ($exists) {
                throw new DuplicateFakturException($nomor);
            }

            if ($rows === []) {
                throw new PurchaseException('Minimal satu item dengan jumlah lebih besar dari 0.');
            }

            $normalized = [];
            $total = 0.0;
            foreach ($rows as $row) {
                $jumlah = (int) ($row['jumlah'] ?? 0);
                $harga = round((float) ($row['harga_satuan'] ?? 0), 2);
                if ($jumlah <= 0) {
                    throw new PurchaseException('Jumlah item harus lebih besar dari 0.');
                }
                $barangId = ! empty($row['barang_id']) ? (int) $row['barang_id'] : null;
                if ($barangId !== null && ! PersediaanBarang::query()->whereKey($barangId)->exists()) {
                    throw new PurchaseException('Barang master tidak ditemukan.');
                }
                $subtotal = round($harga * $jumlah, 2);
                $total += $subtotal;
                $normalized[] = [
                    'barang_id'    => $barangId,
                    'kode_barang'  => (string) ($row['kode_barang'] ?? ''),
                    'nama_barang'  => (string) ($row['nama_barang'] ?? ''),
                    'satuan'       => (string) ($row['satuan'] ?? ''),
                    'harga_satuan' => $harga,
                    'jumlah'       => $jumlah,
                    'subtotal'     => $subtotal,
                ];
            }

            $pembelian = PersediaanPembelian::create([
                'nomor_faktur'     => $nomor,
                'penyedia'         => ($header['penyedia'] ?? null) !== '' ? ($header['penyedia'] ?? null) : null,
                'tanggal'          => $header['tanggal'],
                'unit_kerja'       => $header['unit_kerja'],
                'total'            => round($total, 2),
                'faktur_path'      => $header['faktur_path'] ?? null,
                'bast_path'        => $header['bast_path'] ?? null,
                'invoice_path'     => $header['invoice_path'] ?? null,
                'foto_barang_path' => $header['foto_barang_path'] ?? null,
                'created_by'       => $userId,
            ]);

            foreach ($normalized as $row) {
                $item = PersediaanPembelianItem::create([
                    'pembelian_id' => $pembelian->id,
                    'barang_id'    => $row['barang_id'],
                    'kode_barang'  => $row['kode_barang'],
                    'nama_barang'  => $row['nama_barang'],
                    'satuan'       => $row['satuan'],
                    'harga_satuan' => $row['harga_satuan'],
                    'jumlah'       => $row['jumlah'],
                    'subtotal'     => $row['subtotal'],
                ]);

                if ($row['barang_id'] !== null) {
                    $this->stock->recordPembelianMasuk(
                        (int) $row['barang_id'],
                        (string) $header['unit_kerja'],
                        (int) $item->id,
                        (int) $row['jumlah'],
                        'Pembelian faktur ' . $nomor,
                        $userId,
                    );
                }
            }

            return $pembelian->load('items');
        });
    }

    /**
     * Seluruh file dikunci dalam satu transaksi database.
     * Jika satu faktur gagal, header, detail, dan stok yang lain ikut dibatalkan.
     *
     * @param  list<array{header: array<string,mixed>, items: list<array<string,mixed>>}>  $groups
     * @return list<PersediaanPembelian>
     */
    public function storeMany(array $groups, ?int $userId): array
    {
        return DB::transaction(function () use ($groups, $userId) {
            $created = [];
            foreach ($groups as $group) {
                $created[] = $this->store($group['header'], $group['items'], $userId);
            }

            return $created;
        });
    }

    /**
     * @return array<string,mixed>
     */
    private function line(?int $barangId, string $kode, string $nama, string $satuan, float $harga, int $jumlah): array
    {
        return [
            'barang_id'    => $barangId,
            'kode_barang'  => $kode,
            'nama_barang'  => $nama,
            'satuan'       => $satuan,
            'harga_satuan' => $harga,
            'jumlah'       => $jumlah,
            'subtotal'     => round($harga * $jumlah, 2),
        ];
    }

    /**
     * @param  array<string,mixed>  $row
     */
    private function formRowBlank(array $row): bool
    {
        $barang = trim((string) ($row['barang_id'] ?? ''));
        $kode = trim((string) ($row['kode_barang'] ?? ''));
        $nama = trim((string) ($row['nama_barang'] ?? ''));
        $jumlah = trim((string) ($row['jumlah'] ?? ''));

        return $barang === '' && $kode === '' && $nama === '' && ($jumlah === '' || $jumlah === '0');
    }

    private function wholeNumber(mixed $value): ?int
    {
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
        if ($s === '' || ! preg_match('/^\d+$/', $s)) {
            return null;
        }

        return (int) $s;
    }
}
