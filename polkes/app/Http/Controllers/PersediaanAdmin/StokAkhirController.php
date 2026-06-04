<?php

declare(strict_types=1);

namespace App\Http\Controllers\PersediaanAdmin;

use App\Exports\Persediaan\SimpleArrayExport;
use App\Http\Controllers\Controller;
use App\Models\Persediaan\PersediaanBarang;
use App\Services\Persediaan\StockService;
use App\Support\Persediaan\UnitKerja;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StokAkhirController extends Controller
{
    public function __construct(private StockService $stock)
    {
    }

    public function index(Request $request): View
    {
        [$rows] = $this->buildRows($request);

        return view('persediaan_admin.stok-akhir.index', [
            'rows'    => $rows,
            'units'   => UnitKerja::all(),
            'filters' => $request->only(['unit', 'q', 'from', 'to', 'menipis']),
        ]);
    }

    public function exportExcel(Request $request): BinaryFileResponse
    {
        [$rows] = $this->buildRows($request);

        $headings = ['Kode Barang', 'Nama Barang', 'Unit Kerja', 'Stok Awal', 'Barang Masuk', 'Barang Keluar', 'Stok Akhir'];
        $data = array_map(fn ($r) => [
            $r['kode'], $r['nama'], $r['unit'], $r['awal'], $r['masuk'], $r['keluar'], $r['akhir'],
        ], $rows);

        return Excel::download(new SimpleArrayExport($data, $headings), 'stok-akhir-persediaan.xlsx');
    }

    /**
     * @return array{0: list<array<string,mixed>>}
     */
    private function buildRows(Request $request): array
    {
        $query = PersediaanBarang::query()->where('is_active', true)
            ->when($request->filled('unit'), fn ($q) => $q->where('unit_kerja', $request->input('unit')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%' . $request->input('q') . '%';
                $q->where(fn ($w) => $w->where('kode_barang', 'like', $term)->orWhere('nama_barang', 'like', $term));
            })
            ->orderBy('unit_kerja')->orderBy('kode_barang');

        $barangs = $query->get();
        $ringkasan = $this->stock->ringkasanBanyak($barangs, $request->input('from'), $request->input('to'));

        $menipis = $request->boolean('menipis');
        $rows = [];
        foreach ($barangs as $b) {
            $r = $ringkasan[$b->id] ?? ['masuk' => 0, 'keluar' => 0, 'akhir' => $b->stok_awal];
            if ($menipis && $r['akhir'] > $b->stok_minimum) {
                continue;
            }
            $rows[] = [
                'kode'   => $b->kode_barang,
                'nama'   => $b->nama_barang,
                'unit'   => $b->unit_kerja,
                'awal'   => (int) $b->stok_awal,
                'masuk'  => $r['masuk'],
                'keluar' => $r['keluar'],
                'akhir'  => $r['akhir'],
                'minim'  => $r['akhir'] <= $b->stok_minimum,
            ];
        }

        return [$rows];
    }
}
