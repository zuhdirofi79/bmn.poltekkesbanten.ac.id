<?php

declare(strict_types=1);

namespace App\Http\Controllers\PersediaanAdmin;

use App\Http\Controllers\Controller;
use App\Models\Persediaan\PersediaanBarang;
use App\Models\Persediaan\PersediaanPermintaan;
use App\Models\Persediaan\PersediaanStockMovement;
use App\Services\Persediaan\StockService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private StockService $stock)
    {
    }

    public function index(): View
    {
        $defaults = [
            'totalPersediaan'      => 0,
            'barangMasukBulanIni'  => 0,
            'barangKeluarBulanIni' => 0,
            'nilaiPersediaan'      => 0.0,
            'stokMenipis'          => 0,
            'permintaanPending'    => 0,
            'chartLabels'          => [],
            'chartMasuk'           => [],
            'chartKeluar'          => [],
            'recent'               => collect(),
        ];

        if (! Schema::hasTable('persediaan_barang')) {
            $defaults['chartLabels'] = $this->emptyChartLabels();

            return view('persediaan_admin.dashboard.index', $defaults);
        }

        try {
            $barangs = PersediaanBarang::where('is_active', true)->get();
            $ringkasan = $this->stock->ringkasanBanyak($barangs);

            $totalPersediaan = $barangs->count();
            $nilaiPersediaan = 0.0;
            $stokMenipis = 0;
            foreach ($barangs as $b) {
                $akhir = $ringkasan[$b->id]['akhir'] ?? $b->stok_awal;
                $nilaiPersediaan += $akhir * (float) $b->harga_satuan;
                if ($akhir <= $b->stok_minimum) {
                    $stokMenipis++;
                }
            }

            $startMonth = Carbon::now()->startOfMonth();
            $barangMasukBulanIni = 0;
            $barangKeluarBulanIni = 0;
            if (Schema::hasTable('persediaan_stock_movements')) {
                $barangMasukBulanIni = (int) PersediaanStockMovement::query()
                    ->where('tipe', '!=', PersediaanStockMovement::TIPE_STOK_AWAL)
                    ->where('created_at', '>=', $startMonth)
                    ->sum('qty_masuk');
                $barangKeluarBulanIni = (int) PersediaanStockMovement::query()
                    ->where('created_at', '>=', $startMonth)
                    ->sum('qty_keluar');
            }

            $permintaanPending = 0;
            $recent = collect();
            if (Schema::hasTable('persediaan_permintaan')) {
                $permintaanPending = PersediaanPermintaan::whereIn('status', PersediaanPermintaan::STATUS_PENDING)->count();
                $recent = PersediaanPermintaan::orderByDesc('created_at')->limit(8)->get();
            }

            $labels = [];
            $masukSeries = [];
            $keluarSeries = [];
            for ($i = 5; $i >= 0; $i--) {
                $m = Carbon::now()->startOfMonth()->subMonths($i);
                $next = (clone $m)->addMonth();
                $labels[] = $m->translatedFormat('M Y');
                if (Schema::hasTable('persediaan_stock_movements')) {
                    $masukSeries[] = (int) PersediaanStockMovement::query()
                        ->where('tipe', '!=', PersediaanStockMovement::TIPE_STOK_AWAL)
                        ->whereBetween('created_at', [$m, $next])
                        ->sum('qty_masuk');
                    $keluarSeries[] = (int) PersediaanStockMovement::query()
                        ->whereBetween('created_at', [$m, $next])
                        ->sum('qty_keluar');
                } else {
                    $masukSeries[] = 0;
                    $keluarSeries[] = 0;
                }
            }

            return view('persediaan_admin.dashboard.index', [
                'totalPersediaan'      => $totalPersediaan,
                'barangMasukBulanIni'  => $barangMasukBulanIni,
                'barangKeluarBulanIni' => $barangKeluarBulanIni,
                'nilaiPersediaan'      => $nilaiPersediaan,
                'stokMenipis'          => $stokMenipis,
                'permintaanPending'    => $permintaanPending,
                'chartLabels'          => $labels,
                'chartMasuk'           => $masukSeries,
                'chartKeluar'          => $keluarSeries,
                'recent'               => $recent,
            ]);
        } catch (\Throwable $e) {
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
