<?php

declare(strict_types=1);

namespace App\Observers\BMN;

use App\Models\BMN\AsetBmn;
use App\Models\BMN\HistoriAset;
use App\Models\BMN\LokasiAset;
use App\Support\BMN\AsetBmnAuditHelper;

class AsetBmnObserver
{
    /**
     * @var array<int, array<string, mixed>>
     */
    protected array $pendingUpdates = [];

    /**
     * Setelah create: catat seluruh snapshot atribut bisnis.
     */
    public function created(AsetBmn $aset): void
    {
        $dataBaru = AsetBmnAuditHelper::snapshotCurrent($aset);

        self::persistHistori([
            'aset_id' => $aset->id,
            'aksi' => 'create',
            'data_lama' => null,
            'data_baru' => $dataBaru,
            'deskripsi' => sprintf(
                'Pembuatan aset: kode_barang=%s, nama_barang=%s',
                $aset->kode_barang,
                $aset->nama_barang
            ),
        ]);
    }

    /**
     * Menangkap field dirty sebelum persist (getOriginal masih valid).
     */
    public function updating(AsetBmn $aset): void
    {
        $changed = AsetBmnAuditHelper::getChangedData($aset);

        if ($changed['data_baru'] === []) {
            return;
        }

        $oldStatus = $changed['data_lama']['status'] ?? $aset->getOriginal('status');
        $newStatus = $changed['data_baru']['status'] ?? null;
        $deleteTransition = $newStatus === 'dihapus' && $oldStatus !== 'dihapus';

        $this->pendingUpdates[spl_object_id($aset)] = [
            'changed' => $changed,
            'delete_transition' => $deleteTransition,
            'full_original_snapshot' => $deleteTransition ? AsetBmnAuditHelper::snapshotOriginal($aset) : null,
        ];
    }

    /**
     * Setelah update berhasil: tulis histori dari pending (jika ada).
     */
    public function updated(AsetBmn $aset): void
    {
        $oid = spl_object_id($aset);
        if (! isset($this->pendingUpdates[$oid])) {
            return;
        }

        $pending = $this->pendingUpdates[$oid];
        unset($this->pendingUpdates[$oid]);

        /** @var array{data_lama: array<string, mixed>, data_baru: array<string, mixed>} $changed */
        $changed = $pending['changed'];

        if ($pending['delete_transition'] === true) {
            self::persistHistori([
                'aset_id' => $aset->id,
                'aksi' => 'delete',
                'data_lama' => $pending['full_original_snapshot'] ?? AsetBmnAuditHelper::snapshotOriginal($aset),
                'data_baru' => null,
                'deskripsi' => sprintf(
                    "Penghapusan logis (status → dihapus): kode_barang='%s', nama_barang='%s'",
                    $aset->kode_barang,
                    $aset->nama_barang
                ),
            ]);

            return;
        }

        self::persistHistori([
            'aset_id' => $aset->id,
            'aksi' => 'update',
            'data_lama' => $changed['data_lama'],
            'data_baru' => $changed['data_baru'],
            'deskripsi' => AsetBmnAuditHelper::describeUpdate($changed['data_lama'], $changed['data_baru']),
        ]);
    }

    /**
     * Penghapusan permanen: riwayat dipertahankan dengan aset_id null (butuh FK ON DELETE SET NULL pada MySQL).
     */
    public function deleted(AsetBmn $aset): void
    {
        $attrs = $aset->getAttributes();
        $clean = [];
        foreach (AsetBmnAuditHelper::auditableKeys() as $key) {
            if (! array_key_exists($key, $attrs)) {
                continue;
            }
            $clean[$key] = AsetBmnAuditHelper::normalizeAttributeForAudit($key, $attrs[$key]);
        }

        self::persistHistori([
            'aset_id' => null,
            'aksi' => 'delete',
            'data_lama' => $clean,
            'data_baru' => null,
            'deskripsi' => sprintf(
                "Penghapusan permanen aset: kode_barang='%s', nama_barang='%s' (id=%s)",
                $attrs['kode_barang'] ?? '',
                $attrs['nama_barang'] ?? '',
                $attrs['id'] ?? ''
            ),
        ]);
    }

    /**
     * Catat mutasi lokasi (dipanggil dari luar observer, mis. setelah save tanpa event).
     *
     * @param  array<string, mixed>  $meta  contoh: tanggal_mutasi
     */
    public static function logMutasi(
        AsetBmn $aset,
        int $dariLokasiId,
        int $keLokasiId,
        ?string $dariNama = null,
        ?string $keNama = null,
        ?string $keterangan = null,
        array $meta = []
    ): void {
        $dariNama = $dariNama ?? (optional(LokasiAset::query()->find($dariLokasiId))->nama_lokasi ?? ('ID '.$dariLokasiId));
        $keNama = $keNama ?? (optional(LokasiAset::query()->find($keLokasiId))->nama_lokasi ?? ('ID '.$keLokasiId));

        $dataLama = [
            'lokasi_id' => $dariLokasiId,
            'dari_lokasi' => $dariNama,
        ];
        $dataBaru = [
            'lokasi_id' => $keLokasiId,
            'ke_lokasi' => $keNama,
        ];

        if (! empty($meta['tanggal_mutasi'])) {
            $dataBaru['tanggal_mutasi'] = (string) $meta['tanggal_mutasi'];
        }
        if ($keterangan !== null && $keterangan !== '') {
            $dataBaru['keterangan'] = $keterangan;
        }

        $deskripsi = sprintf(
            "Mutasi lokasi: dari '%s' (lokasi_id=%s) ke '%s' (lokasi_id=%s)",
            $dariNama,
            $dariLokasiId,
            $keNama,
            $keLokasiId
        );
        if ($keterangan !== null && $keterangan !== '') {
            $deskripsi .= ' — '.$keterangan;
        }

        self::persistHistori([
            'aset_id' => $aset->id,
            'aksi' => 'mutasi',
            'data_lama' => $dataLama,
            'data_baru' => $dataBaru,
            'deskripsi' => $deskripsi,
        ]);
    }

    /**
     * @param  array{aset_id?: int|null, aksi: string, data_lama?: ?array<string, mixed>, data_baru?: ?array<string, mixed>, deskripsi: string}  $row
     */
    protected static function persistHistori(array $row): void
    {
        $histori = new HistoriAset();
        $histori->aset_id = $row['aset_id'] ?? null;
        $histori->aksi = $row['aksi'];
        $deskripsi = $row['deskripsi'];
        if (AsetBmnAuditHelper::auditUserId() === null) {
            $deskripsi .= ' [aktor: sistem]';
        }
        $histori->deskripsi = $deskripsi;
        $histori->data_lama = $row['data_lama'] ?? null;
        $histori->data_baru = $row['data_baru'] ?? null;
        $histori->user_id = AsetBmnAuditHelper::auditUserId();
        $histori->created_at = now();
        $histori->save();
    }
}
