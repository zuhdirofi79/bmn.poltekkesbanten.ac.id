<?php

declare(strict_types=1);

namespace App\Http\Controllers\BhpKebersihanAdmin;

use App\Exports\Persediaan\SimpleArrayExport;
use App\Http\Controllers\Controller;
use App\Models\BhpKebersihan\BhpKebersihanBarang;
use App\Services\BhpKebersihan\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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
        [$rows, $from, $to] = $this->buildRows($request);

        return view('bhp_kebersihan_admin.stok-akhir.index', [
            'rows'    => $rows,
            'filters' => array_merge($request->only(['q', 'menipis']), ['from' => $from, 'to' => $to]),
        ]);
    }

    public function exportExcel(Request $request): BinaryFileResponse
    {
        [$rows] = $this->buildRows($request);

        $headings = ['Kode Barang', 'Nama Barang', 'Satuan', 'Stok Awal', 'Barang Masuk', 'Barang Keluar', 'Stok Akhir'];
        $data = array_map(fn ($r) => [
            $r['kode'], $r['nama'], $r['satuan'], $r['awal'], $r['masuk'], $r['keluar'], $r['akhir'],
        ], $rows);

        return Excel::download(new SimpleArrayExport($data, $headings), 'stok-akhir-bhp-kebersihan.xlsx');
    }

    /**
     * @return array{0: list<array<string,mixed>>, 1: string, 2: string}
     */
    private function buildRows(Request $request): array
    {
        $from = $request->input('from') ?: Carbon::now()->startOfMonth()->toDateString();
        $to = $request->input('to') ?: Carbon::now()->endOfMonth()->toDateString();

        $barangs = BhpKebersihanBarang::query()
            ->where('status', 'aktif')
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%' . $request->input('q') . '%';
                $q->where(fn ($w) => $w->where('kode_barang', 'like', $term)->orWhere('nama_barang', 'like', $term));
            })
            ->orderBy('kode_barang')
            ->get();

        $ringkasan = $this->stock->ringkasanBanyak($barangs, $from, $to);
        $menipis = $request->boolean('menipis');

        $rows = [];
        foreach ($barangs as $b) {
            $r = $ringkasan[$b->id] ?? ['awal' => (int) $b->stok_awal, 'masuk' => 0, 'keluar' => 0, 'akhir' => (int) $b->stok_awal];
            if ($menipis && $r['akhir'] > 5) {
                continue;
            }
            $rows[] = [
                'kode'   => $b->kode_barang,
                'nama'   => $b->nama_barang,
                'satuan' => $b->satuan,
                'awal'   => $r['awal'],
                'masuk'  => $r['masuk'],
                'keluar' => $r['keluar'],
                'akhir'  => $r['akhir'],
            ];
        }

        return [$rows, $from, $to];
    }
}
