<?php

declare(strict_types=1);

namespace App\Exports;

use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Export laporan bulanan Sewa BMN ke Excel (.xlsx).
 * Pemanggil menyerahkan builder yang sudah difilter (mis. per bulan/tahun).
 */
class BmnSewaLaporanExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize
{
    private int $row = 0;

    /**
     * @param  Builder<\App\Models\BMN\BmnSewaPengajuan>  $query
     */
    public function __construct(private readonly Builder $query)
    {
    }

    /**
     * @return Builder<\App\Models\BMN\BmnSewaPengajuan>
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
            'Kode Sewa',
            'Tanggal Pengajuan',
            'Nama Penyewa',
            'No WA',
            'Email',
            'Jenis Penyewa',
            'Layanan',
            'Tarif (Rp)',
            'Tanggal Mulai',
            'Rencana Kembali',
            'Jumlah Hari',
            'Total Biaya (Rp)',
            'Status Pembayaran',
            'Diverifikasi Oleh',
            'Tanggal Verifikasi',
            'Bukti Bayar',
            'Surat Resmi',
            'Catatan',
        ];
    }

    /**
     * @param  \App\Models\BMN\BmnSewaPengajuan  $row
     * @return list<int|string|float|null>
     */
    public function map($row): array
    {
        $this->row++;

        $tarif      = $row->tarif;
        $tarifNilai = $row->tarifDipakai();
        $jumlahHari = $row->jumlahHariDihitung();
        $totalBiaya = $row->totalBiayaDihitung();

        $buktiUrl = $row->buktiBayarUrl();
        $suratUrl = $row->suratResmiUrl();

        return [
            $this->row,
            (string) $row->kode_sewa,
            optional($row->created_at)->format('Y-m-d H:i'),
            (string) $row->nama_lengkap,
            (string) $row->nomor_wa,
            (string) $row->email,
            $row->jenisPenyewaLabel(),
            (string) (optional($tarif)->nama_layanan ?? '-'),
            $tarifNilai,
            optional($row->tanggal_mulai_sewa)->format('Y-m-d'),
            optional($row->tanggal_rencana_kembali)->format('Y-m-d'),
            $jumlahHari > 0 ? $jumlahHari : null,
            $totalBiaya,
            $row->paymentStatusLabel(),
            (string) (optional($row->adminPaymentBy)->name ?? '-'),
            optional($row->admin_payment_at)->format('Y-m-d H:i'),
            $buktiUrl ?? '-',
            $suratUrl ?? '-',
            (string) ($row->admin_payment_note ?? $row->catatan ?? ''),
        ];
    }
}
