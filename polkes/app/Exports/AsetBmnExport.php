<?php

declare(strict_types=1);

namespace App\Exports;

use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Export Aset BMN ke Excel (.xlsx).
 *
 * Memakai FromQuery + WithMapping agar dataset besar (±35 ribu baris) di-chunk
 * oleh maatwebsite/excel sehingga aman terhadap memory_limit. Pemanggil WAJIB
 * menyerahkan Eloquent Builder yang SUDAH difilter (mis. hasil scopeFilter di
 * AsetBmn) — kelas ini sengaja TIDAK mengetahui logika filter, agar single
 * source of truth filter tetap di Controller/Model.
 */
class AsetBmnExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize
{
    /**
     * Counter manual untuk kolom "No". Tetap konsisten lintas chunk karena
     * maatwebsite/excel memakai instance yang sama saat memproses FromQuery.
     */
    private int $rowNumber = 0;

    /**
     * @param  Builder<\App\Models\BMN\AsetBmn>  $query
     */
    public function __construct(private readonly Builder $query)
    {
    }

    /**
     * @return Builder<\App\Models\BMN\AsetBmn>
     */
    public function query(): Builder
    {
        return $this->query;
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return [
            'No',
            'Kode Barang',
            'NUP',
            'Nama Barang',
            'Merk',
            'Kategori',
            'Nilai Perolehan',
            'Tanggal Perolehan',
            'Kondisi',
            'Lokasi',
            'Satker',
            'Pengguna',
            'Status',
        ];
    }

    /**
     * @param  \App\Models\BMN\AsetBmn  $aset
     * @return list<int|string|float|null>
     */
    public function map($aset): array
    {
        $this->rowNumber++;

        $tanggalPerolehan = $aset->tanggal_perolehan;
        if ($tanggalPerolehan instanceof \DateTimeInterface) {
            $tanggalText = $tanggalPerolehan->format('d/m/Y');
        } elseif (is_string($tanggalPerolehan) && $tanggalPerolehan !== '') {
            $tanggalText = $tanggalPerolehan;
        } else {
            $tanggalText = '';
        }

        $kategoriNama = optional($aset->kategori)->nama_kategori;
        if ($kategoriNama === null || $kategoriNama === '') {
            $kategoriNama = $aset->kategori_id !== null ? (string) $aset->kategori_id : '';
        }

        $lokasi = method_exists($aset, 'labelLokasi')
            ? $aset->labelLokasi()
            : (string) ($aset->lokasi ?? '');

        $nilai = $aset->nilai_perolehan;
        if ($nilai !== null && $nilai !== '') {
            $nilai = (float) $nilai;
        } else {
            $nilai = null;
        }

        return [
            $this->rowNumber,
            (string) ($aset->kode_barang ?? ''),
            (string) ($aset->nup ?? ''),
            (string) ($aset->nama_barang ?? ''),
            (string) ($aset->merk ?? ''),
            (string) $kategoriNama,
            $nilai,
            $tanggalText,
            (string) ($aset->kondisi ?? ''),
            $lokasi,
            (string) ($aset->satker ?? ''),
            (string) ($aset->pengguna_ruangan ?? ''),
            (string) ($aset->status ?? ''),
        ];
    }
}
