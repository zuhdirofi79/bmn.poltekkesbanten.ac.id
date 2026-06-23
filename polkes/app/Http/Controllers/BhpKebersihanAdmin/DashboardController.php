<?php

declare(strict_types=1);

namespace App\Http\Controllers\BhpKebersihanAdmin;

use App\Http\Controllers\Controller;
use App\Models\BhpKebersihan\BhpKebersihanBarang;
use App\Models\BhpKebersihan\BhpKebersihanPermintaan;
use App\Models\BhpKebersihan\BhpKebersihanStockMovement;
use App\Services\BhpKebersihan\StockService;
use App\Support\BhpKebersihan\BhpAdminSession;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private StockService $stock)
    {
    }

    public function index(Request $request): View
    {
        $defaults = [
            'totalBarang'          => 0,
            'permintaanPending'    => 0,
            'permintaanDisetujui'  => 0,
            'barangKeluarBulanIni' => 0,
            'stokAkhirTotal'       => 0,
            'chartLabels'          => $this->emptyChartLabels(),
            'chartKeluar'          => [],
            'recent'               => collect(),
            'role'                 => BhpAdminSession::role($request),
        ];

        if (! Schema::hasTable('bhp_kebersihan_barang')) {
            return view('bhp_kebersihan_admin.dashboard.index', $defaults);
        }

        try {
            $barangs = BhpKebersihanBarang::where('status', 'aktif')->get();
            $stokAkhirTotal = 0;
            foreach ($barangs as $b) {
                $stokAkhirTotal += $this->stock->stokSaatIni($b);
            }

            $startMonth = Carbon::now()->startOfMonth();
            $barangKeluarBulanIni = Schema::hasTable('bhp_kebersihan_stock_movements')
                ? (int) BhpKebersihanStockMovement::query()
                    ->where('tipe', BhpKebersihanStockMovement::TIPE_KELUAR)
                    ->where('created_at', '>=', $startMonth)
                    ->sum('jumlah')
                : 0;

            $permintaanPending = 0;
            $permintaanDisetujui = 0;
            $recent = collect();
            if (Schema::hasTable('bhp_kebersihan_permintaan')) {
                $permintaanPending = BhpKebersihanPermintaan::where('status', BhpKebersihanPermintaan::STATUS_PENDING)->count();
                $permintaanDisetujui = BhpKebersihanPermintaan::whereIn('status', [
                    BhpKebersihanPermintaan::STATUS_DISETUJUI,
                    BhpKebersihanPermintaan::STATUS_SIAP_DIAMBIL,
                ])->count();
                $recent = BhpKebersihanPermintaan::with('barang')
                    ->orderByDesc('created_at')
                    ->limit(8)
                    ->get();
            }

            $labels = [];
            $keluarSeries = [];
            for ($i = 5; $i >= 0; $i--) {
                $m = Carbon::now()->startOfMonth()->subMonths($i);
                $next = (clone $m)->addMonth();
                $labels[] = $m->translatedFormat('M Y');
                $keluarSeries[] = Schema::hasTable('bhp_kebersihan_stock_movements')
                    ? (int) BhpKebersihanStockMovement::query()
                        ->where('tipe', BhpKebersihanStockMovement::TIPE_KELUAR)
                        ->whereBetween('created_at', [$m, $next])
                        ->sum('jumlah')
                    : 0;
            }

            return view('bhp_kebersihan_admin.dashboard.index', [
                'totalBarang'          => $barangs->count(),
                'permintaanPending'    => $permintaanPending,
                'permintaanDisetujui'   => $permintaanDisetujui,
                'barangKeluarBulanIni' => $barangKeluarBulanIni,
                'stokAkhirTotal'       => $stokAkhirTotal,
                'chartLabels'          => $labels,
                'chartKeluar'          => $keluarSeries,
                'recent'               => $recent,
                'role'                 => BhpAdminSession::role($request),
            ]);
        } catch (\Throwable $e) {
            return view('bhp_kebersihan_admin.dashboard.index', $defaults);
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
