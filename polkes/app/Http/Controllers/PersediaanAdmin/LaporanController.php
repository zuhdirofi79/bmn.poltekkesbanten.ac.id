<?php

declare(strict_types=1);

namespace App\Http\Controllers\PersediaanAdmin;

use App\Exports\Persediaan\SimpleArrayExport;
use App\Http\Controllers\Controller;
use App\Models\Persediaan\PersediaanPermintaan;
use App\Support\Persediaan\UnitKerja;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Laporan permintaan persediaan. Export Excel WAJIB mengikuti filter aktif
 * (unit, periode, status). Tidak mengexport semua data tanpa filter kecuali
 * admin sengaja reset filter.
 */
class LaporanController extends Controller
{
    public function index(Request $request): View
    {
        $rows = $this->query($request)->with('pemohon')->orderByDesc('tanggal_permintaan')->paginate(20)->withQueryString();

        return view('persediaan_admin.laporan.index', [
            'rows'    => $rows,
            'units'   => UnitKerja::all(),
            'filters' => $request->only(['unit', 'status', 'from', 'to']),
        ]);
    }

    public function exportExcel(Request $request): BinaryFileResponse
    {
        $rows = $this->query($request)->with('pemohon')->orderByDesc('tanggal_permintaan')->get();

        $headings = ['Nomor Permintaan', 'Tanggal', 'Unit Kerja', 'Pemohon', 'Status', 'Jumlah Item'];
        $data = $rows->map(fn ($p) => [
            $p->nomor_permintaan,
            optional($p->tanggal_permintaan)->format('Y-m-d'),
            $p->unit_kerja,
            optional($p->pemohon)->nama,
            $p->statusLabel(),
            $p->items()->count(),
        ])->toArray();

        return Excel::download(new SimpleArrayExport($data, $headings), 'laporan-permintaan-persediaan.xlsx');
    }

    private function query(Request $request)
    {
        return PersediaanPermintaan::query()
            ->when($request->filled('unit'), fn ($q) => $q->where('unit_kerja', $request->input('unit')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('tanggal_permintaan', '>=', $request->input('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('tanggal_permintaan', '<=', $request->input('to')));
    }
}
