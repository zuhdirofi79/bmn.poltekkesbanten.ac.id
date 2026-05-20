<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\BMN\AsetBmn;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Export aset BMN yang DIPILIH (subset by ID) ke Excel (.xlsx).
 *
 * Beda dengan AsetBmnExport (yang mengikuti filter halaman index),
 * kelas ini HANYA meng-export baris dengan id ∈ $ids — tidak memperhatikan
 * filter/search/pagination apa pun. ID divalidasi & disanitasi di Controller
 * sebelum sampai ke sini, jadi konstruktor cukup terima list<int>.
 *
 * Catatan kolom yang sengaja TIDAK diexport:
 *  - asset_tag_hash : nilai hash internal, bukan konsumsi user.
 *  - deleted_at, deleted_by : sensitif & tidak relevan untuk export aktif.
 *  - foto (JSON array path) : panjang & mengotori tampilan Excel; tetap ada
 *    kolom foto_url / foto_preview_url yang sudah ringkas.
 */
class AsetBmnSelectedExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize
{
    /**
     * @param  list<int>  $ids
     */
    public function __construct(private readonly array $ids)
    {
    }

    /**
     * @return Builder<AsetBmn>
     */
    public function query(): Builder
    {
        return AsetBmn::query()
            ->with(['kategori'])
            ->whereIn('id', $this->ids)
            ->orderBy('kode_barang')
            ->orderBy('nup');
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return [
            'ID',
            'Kode Barang',
            'Nama Barang',
            'Kategori ID',
            'Kategori',
            'Nilai Perolehan',
            'Harga Satuan',
            'Tanggal Perolehan',
            'Kondisi',
            'Status',
            'Created At',
            'Created By',
            'Updated At',
            'Updated By',
            'Nomor Register',
            'NUP',
            'Asset Tag',
            'Asset Tag Type',
            'Merk',
            'Foto URL',
            'Foto Preview URL',
            'Foto Provider',
            'Tahun Perolehan',
            'Pengguna Ruangan',
            'Satker ID',
            'Satker',
            'Lokasi ID',
            'Lokasi',
        ];
    }

    /**
     * @param  AsetBmn  $aset
     * @return list<int|string|float|null>
     */
    public function map($aset): array
    {
        $tanggalPerolehan = $aset->tanggal_perolehan;
        if ($tanggalPerolehan instanceof \DateTimeInterface) {
            $tanggalText = $tanggalPerolehan->format('Y-m-d');
        } elseif (is_string($tanggalPerolehan) && $tanggalPerolehan !== '') {
            $tanggalText = $tanggalPerolehan;
        } else {
            $tanggalText = '';
        }

        $createdAt = $aset->created_at;
        $createdAtText = $createdAt instanceof \DateTimeInterface
            ? $createdAt->format('Y-m-d H:i:s')
            : (string) ($createdAt ?? '');

        $updatedAt = $aset->updated_at;
        $updatedAtText = $updatedAt instanceof \DateTimeInterface
            ? $updatedAt->format('Y-m-d H:i:s')
            : (string) ($updatedAt ?? '');

        $nilai = $aset->nilai_perolehan;
        $nilai = ($nilai !== null && $nilai !== '') ? (float) $nilai : null;

        $hargaSatuan = $aset->harga_satuan;
        $hargaSatuan = ($hargaSatuan !== null && $hargaSatuan !== '') ? (float) $hargaSatuan : null;

        $tahun = $aset->tahun_perolehan;
        $tahun = ($tahun !== null && $tahun !== '') ? (int) $tahun : null;

        return [
            (int) $aset->id,
            (string) ($aset->kode_barang ?? ''),
            (string) ($aset->nama_barang ?? ''),
            $aset->kategori_id !== null ? (int) $aset->kategori_id : null,
            (string) (optional($aset->kategori)->nama_kategori ?? ''),
            $nilai,
            $hargaSatuan,
            $tanggalText,
            (string) ($aset->kondisi ?? ''),
            (string) ($aset->status ?? ''),
            $createdAtText,
            $aset->created_by !== null ? (int) $aset->created_by : null,
            $updatedAtText,
            $aset->updated_by !== null ? (int) $aset->updated_by : null,
            (string) ($aset->nomor_register ?? ''),
            (string) ($aset->nup ?? ''),
            (string) ($aset->asset_tag ?? ''),
            (string) ($aset->asset_tag_type ?? ''),
            (string) ($aset->merk ?? ''),
            (string) ($aset->foto_url ?? ''),
            (string) ($aset->foto_preview_url ?? ''),
            (string) ($aset->foto_provider ?? ''),
            $tahun,
            (string) ($aset->pengguna_ruangan ?? ''),
            $aset->satker_id !== null ? (int) $aset->satker_id : null,
            (string) ($aset->satker ?? ''),
            $aset->lokasi_id !== null ? (int) $aset->lokasi_id : null,
            (string) ($aset->lokasi ?? ''),
        ];
    }
}
