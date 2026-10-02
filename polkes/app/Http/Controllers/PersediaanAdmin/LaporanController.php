<?php

declare(strict_types=1);

namespace App\Http\Controllers\PersediaanAdmin;

use App\Exports\Persediaan\LaporanPersediaanExport;
use App\Http\Controllers\Controller;
use App\Models\Persediaan\PersediaanPermintaan;
use App\Services\Persediaan\PersediaanValuationService;
use App\Support\Persediaan\PersediaanAccess;
use App\Support\Persediaan\UnitKerja;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class LaporanController extends Controller
{
    public function __construct(private PersediaanValuationService $valuation)
    {
    }

    public function index(Request $request): View
    {
        $filters = $this->filters($request);
        $report = $this->valuation->reconcile($filters);
        $chart = $this->chart($report, $filters);
        $perPage = 20;
        $page = max(1, (int) $request->input('page', 1));
        $rows = new LengthAwarePaginator(
            array_slice($report['rows'], ($page - 1) * $perPage, $perPage),
            count($report['rows']),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return view('persediaan_admin.laporan.index', [
            'rows'              => $rows,
            'summary'           => $report['summary'],
            'units'             => UnitKerja::all(),
            'filters'           => $filters,
            'chartLabels'       => $chart['labels'],
            'chartMasuk'        => $chart['masuk'],
            'chartKeluar'       => $chart['keluar'],
            'chartNilaiMasuk'   => $chart['nilai_masuk'],
            'chartNilaiKeluar'  => $chart['nilai_keluar'],
            'permintaanPending' => $this->pendingCount($request, $filters['unit']),
        ]);
    }

    public function exportExcel(Request $request): BinaryFileResponse
    {
        $filters = $this->filters($request);
        $report = $this->valuation->reconcile($filters);
        $filename = 'Laporan_Persediaan_' . now()->format('Ymd_His') . '.xlsx';

        return Excel::download(
            new LaporanPersediaanExport($report['rows'], $report['summary']),
            $filename,
        );
    }

    public function exportPdf(Request $request): Response
    {
        $filters = $this->filters($request);
        $report = $this->valuation->reconcile($filters);
        $pdf = Pdf::loadView('persediaan_admin.laporan.pdf', [
            'rows'    => $report['rows'],
            'summary' => $report['summary'],
            'filters' => $filters,
        ])->setPaper('a4', 'landscape');

        return $pdf->download('Laporan_Persediaan_' . now()->format('Ymd_His') . '.pdf');
    }

    /**
     * @param  array{summary: array<string, int|string>, rows: list<array<string, int|string>>}  $report
     * @param  array{unit: ?string, from: string, to: string, q: ?string, active_only: bool}  $filters
     * @return array{labels: list<string>, masuk: list<int>, keluar: list<int>, nilai_masuk: list<float>, nilai_keluar: list<float>}
     */
    private function chart(array $report, array $filters): array
    {
        $ids = [];
        $harga = [];
        foreach ($report['rows'] as $row) {
            $ids[] = (int) $row['id'];
            $harga[(int) $row['id']] = (string) $row['harga'];
        }

        return $this->valuation->monthlyChart($ids, $harga, Carbon::parse($filters['to']), 6);
    }

    /**
     * @return array{unit: ?string, from: string, to: string, q: ?string, active_only: bool}
     */
    private function filters(Request $request): array
    {
        $from = (string) ($request->input('from') ?: Carbon::now()->startOfMonth()->toDateString());
        $to = (string) ($request->input('to') ?: Carbon::now()->endOfMonth()->toDateString());
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $unit = $request->input('unit');
        $locked = PersediaanAccess::lockedUnitForApprover($request);
        if ($locked !== null) {
            $unit = $locked;
        }

        $q = trim((string) $request->input('q', ''));

        return [
            'unit'        => is_string($unit) && $unit !== '' ? $unit : null,
            'from'        => $from,
            'to'          => $to,
            'q'           => $q !== '' ? $q : null,
            'active_only' => true,
        ];
    }

    private function pendingCount(Request $request, ?string $unit): int
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('persediaan_permintaan')) {
            return 0;
        }

        $query = PersediaanAccess::scopePermintaanQuery(PersediaanPermintaan::query(), $request)
            ->whereIn('status', PersediaanPermintaan::STATUS_PENDING);
        if ($unit !== null) {
            $query->where('unit_kerja', $unit);
        }

        return (int) $query->count();
    }
}
