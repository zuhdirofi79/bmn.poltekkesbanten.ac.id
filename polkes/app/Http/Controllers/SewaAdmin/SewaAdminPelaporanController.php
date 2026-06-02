<?php

declare(strict_types=1);

namespace App\Http\Controllers\SewaAdmin;

use App\Exports\BmnSewaLaporanExport;
use App\Http\Controllers\Controller;
use App\Models\BMN\BmnSewaPengajuan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SewaAdminPelaporanController extends Controller
{
    public function index(Request $request): View
    {
        [$year, $month] = $this->resolveYearMonth($request);

        $builder = $this->buildQuery($year, $month);

        $list = (clone $builder)
            ->with(['tarif:id,nama_layanan,kategori,satuan,tarif_umum,tarif_pegawai', 'adminPaymentBy:id,name'])
            ->orderBy('id')
            ->get();

        $totalPengajuan = $list->count();
        $totalTerbayar = $list->where('payment_status', BmnSewaPengajuan::PAYMENT_CONFIRMED)->count();
        $totalDitolak = $list->where('payment_status', BmnSewaPengajuan::PAYMENT_REJECTED)->count();
        $totalPending = $list->where('payment_status', BmnSewaPengajuan::PAYMENT_PENDING)->count();

        $totalNominalUmum = 0.0;
        $totalNominalPegawai = 0.0;
        $totalBiayaTerbayar = 0.0;
        foreach ($list as $row) {
            if ($row->payment_status !== BmnSewaPengajuan::PAYMENT_CONFIRMED) {
                continue;
            }
            $rowTotal = $row->totalBiayaDihitung();
            if ($rowTotal !== null) {
                $totalBiayaTerbayar += (float) $rowTotal;
            }
            if ($row->tarif === null) {
                continue;
            }
            if ($row->jenis_penyewa === BmnSewaPengajuan::JENIS_PEGAWAI && $row->tarif->tarif_pegawai !== null) {
                $totalNominalPegawai += (float) ($rowTotal ?? $row->tarif->tarif_pegawai);
            } else {
                $totalNominalUmum += (float) ($rowTotal ?? $row->tarif->tarif_umum);
            }
        }

        return view('sewa_admin.pelaporan.index', [
            'list' => $list,
            'year' => $year,
            'month' => $month,
            'yearOptions' => $this->yearOptions(),
            'monthOptions' => $this->monthOptions(),
            'totalPengajuan' => $totalPengajuan,
            'totalTerbayar' => $totalTerbayar,
            'totalDitolak' => $totalDitolak,
            'totalPending' => $totalPending,
            'totalNominalUmum' => $totalNominalUmum,
            'totalNominalPegawai' => $totalNominalPegawai,
            'totalBiayaTerbayar' => $totalBiayaTerbayar,
        ]);
    }

    public function exportExcel(Request $request): StreamedResponse|Response
    {
        [$year, $month] = $this->resolveYearMonth($request);
        $filename = sprintf('laporan-sewa-bmn-%04d-%02d.xlsx', $year, $month);

        try {
            return Excel::download(
                new BmnSewaLaporanExport($this->buildQuery($year, $month)->with(['tarif', 'adminPaymentBy'])),
                $filename
            );
        } catch (\Throwable $e) {
            // Fallback ke CSV jika package Excel bermasalah.
            return $this->exportCsv($request);
        }
    }

    public function exportCsv(Request $request): Response
    {
        [$year, $month] = $this->resolveYearMonth($request);
        $filename = sprintf('laporan-sewa-bmn-%04d-%02d.csv', $year, $month);

        $rows = $this->buildQuery($year, $month)
            ->with(['tarif', 'adminPaymentBy:id,name'])
            ->orderBy('id')
            ->get();

        $callback = function () use ($rows): void {
            $out = fopen('php://output', 'wb');
            // BOM agar Excel di Windows mengenali UTF-8
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                'No', 'Kode Sewa', 'Tanggal Pengajuan', 'Nama Penyewa', 'No WA', 'Email',
                'Jenis Penyewa', 'Layanan', 'Tarif (Rp)', 'Tanggal Mulai', 'Rencana Kembali',
                'Jumlah Hari', 'Total Biaya (Rp)',
                'Status Pembayaran', 'Diverifikasi Oleh', 'Tanggal Verifikasi',
                'Bukti Bayar', 'Surat Resmi', 'Catatan',
            ]);

            $no = 0;
            foreach ($rows as $row) {
                $no++;
                $tarif      = $row->tarif;
                $tarifNilai = $row->tarifDipakai();
                $jumlahHari = $row->jumlahHariDihitung();
                $totalBiaya = $row->totalBiayaDihitung();

                fputcsv($out, [
                    $no,
                    (string) $row->kode_sewa,
                    optional($row->created_at)->format('Y-m-d H:i'),
                    (string) $row->nama_lengkap,
                    (string) $row->nomor_wa,
                    (string) $row->email,
                    $row->jenisPenyewaLabel(),
                    (string) (optional($tarif)->nama_layanan ?? '-'),
                    $tarifNilai !== null ? (string) $tarifNilai : '',
                    optional($row->tanggal_mulai_sewa)->format('Y-m-d'),
                    optional($row->tanggal_rencana_kembali)->format('Y-m-d'),
                    $jumlahHari > 0 ? (string) $jumlahHari : '',
                    $totalBiaya !== null ? (string) $totalBiaya : '',
                    $row->paymentStatusLabel(),
                    (string) (optional($row->adminPaymentBy)->name ?? '-'),
                    optional($row->admin_payment_at)->format('Y-m-d H:i'),
                    (string) ($row->buktiBayarUrl() ?? '-'),
                    (string) ($row->suratResmiUrl() ?? '-'),
                    (string) ($row->admin_payment_note ?? $row->catatan ?? ''),
                ]);
            }
            fclose($out);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * @return array{0:int,1:int}
     */
    private function resolveYearMonth(Request $request): array
    {
        $now = Carbon::now();
        $year = (int) $request->query('tahun', $now->year);
        $month = (int) $request->query('bulan', $now->month);
        if ($year < 2000 || $year > 2100) {
            $year = $now->year;
        }
        if ($month < 1 || $month > 12) {
            $month = $now->month;
        }

        return [$year, $month];
    }

    /**
     * @return Builder<BmnSewaPengajuan>
     */
    private function buildQuery(int $year, int $month): Builder
    {
        return BmnSewaPengajuan::query()
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month);
    }

    /**
     * @return array<int, int>
     */
    private function yearOptions(): array
    {
        $now = Carbon::now()->year;
        $years = [];
        for ($y = $now - 2; $y <= $now + 1; $y++) {
            $years[] = $y;
        }

        return $years;
    }

    /**
     * @return array<int, string>
     */
    private function monthOptions(): array
    {
        return [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];
    }
}
