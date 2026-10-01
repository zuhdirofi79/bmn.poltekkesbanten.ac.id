<?php

declare(strict_types=1);

namespace App\Services\Persediaan;

use App\Models\Persediaan\PersediaanBarang;
use App\Models\Persediaan\PersediaanPemohon;
use App\Models\Persediaan\PersediaanPermintaan;
use App\Models\Persediaan\PersediaanPermintaanItem;
use App\Models\Persediaan\PersediaanRak;
use App\Support\Persediaan\PersediaanAccess;
use App\Support\Persediaan\RackAccess;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PersediaanRequestService
{
    public function __construct(
        private StockService $stock,
        private NomorPermintaanService $nomor,
    ) {
    }

    /**
     * Buat SATU header permintaan dengan banyak item. Stok belum berkurang.
     *
     * @param  array<int,int>  $qtyByBarangId
     */
    public function createFromRack(
        PersediaanPemohon $pemohon,
        PersediaanRak $rak,
        array $qtyByBarangId,
        string $idempotencyKey,
    ): PersediaanPermintaan {
        if (! RackAccess::pemohonCanUseRak($pemohon, $rak)) {
            throw new InvalidArgumentException('Anda tidak berhak mengambil barang dari rak ini.');
        }

        $existing = PersediaanPermintaan::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();
        if ($existing !== null) {
            return $existing;
        }

        $qtyByBarangId = array_filter(
            $qtyByBarangId,
            static fn ($qty): bool => (int) $qty > 0,
        );

        if ($qtyByBarangId === []) {
            throw new InvalidArgumentException('Minimal satu barang dengan jumlah > 0.');
        }

        $barangIds = array_map('intval', array_keys($qtyByBarangId));
        $assignedIds = $rak->assignments()->pluck('barang_id')->map(fn ($id) => (int) $id)->all();

        $barangs = PersediaanBarang::query()
            ->whereIn('id', $barangIds)
            ->where('unit_kerja', $rak->unit_kerja)
            ->where('is_active', true)
            ->get()
            ->keyBy('id');

        $chosen = [];
        foreach ($qtyByBarangId as $barangId => $qty) {
            $barangId = (int) $barangId;
            $qty = (int) $qty;
            if ($qty <= 0) {
                continue;
            }
            if (! in_array($barangId, $assignedIds, true)) {
                throw new InvalidArgumentException('Barang tidak berada di rak ini.');
            }
            $barang = $barangs->get($barangId);
            if ($barang === null) {
                throw new InvalidArgumentException('Barang tidak valid untuk rak ini.');
            }
            $stok = $this->stock->stokAkhir($barang);
            if ($qty > $stok) {
                throw new InvalidArgumentException(sprintf(
                    'Jumlah "%s" (%d) melebihi stok tersedia (%d).',
                    $barang->nama_barang,
                    $qty,
                    $stok,
                ));
            }
            $chosen[] = ['barang' => $barang, 'jumlah' => $qty, 'stok' => $stok];
        }

        if ($chosen === []) {
            throw new InvalidArgumentException('Minimal satu barang dengan jumlah > 0.');
        }

        return DB::transaction(function () use ($pemohon, $rak, $chosen, $idempotencyKey) {
            $again = PersediaanPermintaan::query()
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();
            if ($again !== null) {
                return $again;
            }

            $approverUserId = PersediaanAccess::approverUserIdForUnit($pemohon->unit_kerja);
            $permintaan = PersediaanPermintaan::create([
                'nomor_permintaan'   => $this->nomor->generate($pemohon->unit_kerja),
                'pemohon_id'         => $pemohon->id,
                'unit_kerja'         => $pemohon->unit_kerja,
                'tanggal_permintaan' => Carbon::today(),
                'keterangan'         => 'Pengambilan via QR Rak ' . $rak->kode_rak . ' – ' . $rak->nama_rak,
                'status'             => PersediaanPermintaan::STATUS_MENUNGGU,
                'approver_user_id'   => $approverUserId,
                'source'             => 'rack_qr',
                'rak_id'             => $rak->id,
                'idempotency_key'    => $idempotencyKey,
            ]);

            foreach ($chosen as $row) {
                /** @var PersediaanBarang $barang */
                $barang = $row['barang'];
                PersediaanPermintaanItem::create([
                    'permintaan_id'  => $permintaan->id,
                    'barang_id'      => $barang->id,
                    'kode_barang'    => $barang->kode_barang,
                    'nama_barang'    => $barang->nama_barang,
                    'satuan'         => $barang->satuan,
                    'stok_tersedia'  => $row['stok'],
                    'jumlah_diminta' => $row['jumlah'],
                ]);
            }

            return $permintaan->load('items');
        });
    }
}
