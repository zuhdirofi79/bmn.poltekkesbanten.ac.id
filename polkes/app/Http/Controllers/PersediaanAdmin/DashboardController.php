<?php

declare(strict_types=1);

namespace App\Http\Controllers\PersediaanAdmin;

use App\Http\Controllers\Controller;
use App\Models\Persediaan\PersediaanPermintaan;
use App\Services\Persediaan\PersediaanValuationService;
use App\Support\Persediaan\PersediaanAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private PersediaanValuationService $valuation)
    {
    }

    public function index(Request $request): View
    {
        $defaults = [
            'totalStokSaatIni'     => 0,
            'totalJenisBarang'     => 0,
            'nilaiBarangMasuk'     => '0.00',
            'jumlahBarangMasuk'    => 0,
            'nilaiBarangKeluar'    => '0.00',
            'jumlahBarangKeluar'   => 0,
            'nilaiPersediaan'      => '0.00',
            'stokMenipis'          => 0,
            'permintaanPending'    => 0,
            'chartLabels'          => [],
            'chartMasuk'           => [],
            'chartKeluar'          => [],
            'chartNilaiMasuk'      => [],
            'chartNilaiKeluar'     => [],
            'recent'               => collect(),
            'lockedUnit'           => PersediaanAccess::lockedUnitForApprover($request),
            'isSuperadmin'         => PersediaanAccess::isSuperadmin($request),
        ];

        if (! Schema::hasTable('persediaan_barang')) {
            $defaults['chartLabels'] = $this->emptyChartLabels();

            return view('persediaan_admin.dashboard.index', $defaults);
        }

        try {
            $saldo = $this->valuation->reconcile(['active_only' => true]);
            $bulan = $this->valuation->reconcile([
                'active_only' => true,
                'from'        => Carbon::now()->startOfMonth()->toDateString(),
                'to'          => Carbon::now()->endOfMonth()->toDateString(),
            ]);

            $stokMenipis = 0;
            $hargaById = [];
            $ids = [];
            foreach ($saldo['rows'] as $row) {
                $ids[] = (int) $row['id'];
                $hargaById[(int) $row['id']] = (string) $row['harga'];
                if ((int) $row['stok_saat_ini'] <= (int) $row['stok_minimum']) {
                    $stokMenipis++;
                }
            }

            $chart = $this->valuation->monthlyChart($ids, $hargaById, Carbon::now(), 6);

            $permintaanPending = 0;
            $recent = collect();
            if (Schema::hasTable('persediaan_permintaan')) {
                $permintaanQuery = PersediaanAccess::scopePermintaanQuery(
                    PersediaanPermintaan::query(),
                    $request,
                );
                $permintaanPending = (clone $permintaanQuery)
                    ->whereIn('status', PersediaanPermintaan::STATUS_PENDING)
                    ->count();
                $recent = (clone $permintaanQuery)
                    ->orderByDesc('created_at')
                    ->limit(8)
                    ->get();
            }

            return view('persediaan_admin.dashboard.index', [
                'totalStokSaatIni'   => $saldo['summary']['total_stok_saat_ini'],
                'totalJenisBarang'   => $saldo['summary']['total_jenis_barang'],
                'nilaiBarangMasuk'   => $bulan['summary']['nilai_barang_masuk'],
                'jumlahBarangMasuk'  => $bulan['summary']['jumlah_barang_masuk'],
                'nilaiBarangKeluar'  => $bulan['summary']['nilai_barang_keluar'],
                'jumlahBarangKeluar' => $bulan['summary']['jumlah_barang_keluar'],
                'nilaiPersediaan'    => $saldo['summary']['nilai_persediaan_saat_ini'],
                'stokMenipis'        => $stokMenipis,
                'permintaanPending'  => $permintaanPending,
                'chartLabels'        => $chart['labels'],
                'chartMasuk'         => $chart['masuk'],
                'chartKeluar'        => $chart['keluar'],
                'chartNilaiMasuk'    => $chart['nilai_masuk'],
                'chartNilaiKeluar'   => $chart['nilai_keluar'],
                'recent'             => $recent,
                'lockedUnit'         => PersediaanAccess::lockedUnitForApprover($request),
                'isSuperadmin'       => PersediaanAccess::isSuperadmin($request),
            ]);
        } catch (\Throwable $e) {
            report($e);
            $defaults['chartLabels'] = $this->emptyChartLabels();

            return view('persediaan_admin.dashboard.index', $defaults);
        }
    }

    /** @return list<string> */
    private function emptyChartLabels(): array
    {
        $labels = [];
        for ($i = 5; $i >= 0; $i--) {
            $labels[] = Carbon::now()->startOfMonth()->subMonths($i)->translatedFormat('M Y');
        }

        return $labels;
    }
}
