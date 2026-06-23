<?php

declare(strict_types=1);

namespace App\Http\Controllers\BhpKebersihanAdmin;

use App\Exports\Persediaan\SimpleArrayExport;
use App\Http\Controllers\Controller;
use App\Models\BhpKebersihan\BhpKebersihanBarang;
use App\Models\BhpKebersihan\BhpKebersihanPermintaan;
use App\Models\BhpKebersihan\BhpKebersihanStockMovement;
use App\Models\BhpKebersihan\BhpKebersihanStockOpname;
use App\Services\BhpKebersihan\StockService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class LaporanController extends Controller
{
    public function __construct(private StockService $stock)
    {
    }

    public function index(Request $request): View
    {
        $tab = $request->input('tab', 'permintaan');

        return view('bhp_kebersihan_admin.laporan.index', [
            'tab'     => $tab,
            'filters' => $request->only(['status', 'from', 'to', 'barang_id', 'q']),
            'barangs' => BhpKebersihanBarang::orderBy('kode_barang')->get(),
            'rows'    => $this->queryRows($request, $tab),
        ]);
    }

    public function exportExcel(Request $request): BinaryFileResponse
    {
        $tab = $request->input('tab', 'permintaan');
        $rows = $this->queryRows($request, $tab, true);

        [$headings, $filename] = match ($tab) {
            'keluar' => [
                ['Tanggal', 'Kode Barang', 'Nama Barang', 'Jumlah', 'Sumber', 'Catatan'],
                'laporan-barang-keluar-bhp.xlsx',
            ],
            'stok' => [
                ['Kode Barang', 'Nama Barang', 'Satuan', 'Stok Awal', 'Masuk', 'Keluar', 'Stok Akhir'],
                'laporan-stok-akhir-bhp.xlsx',
            ],
            'opname' => [
                ['Tanggal Opname', 'Catatan', 'Jumlah Barang'],
                'laporan-opname-bhp.xlsx',
            ],
            default => [
                ['Nomor', 'Tanggal', 'Barang', 'Pengambil', 'Jumlah', 'Status'],
                'laporan-permintaan-bhp.xlsx',
            ],
        };

        return Excel::download(new SimpleArrayExport($rows, $headings), $filename);
    }

    /**
     * @return list<array<int|string|null>>
     */
    private function queryRows(Request $request, string $tab, bool $all = false): array
    {
        $from = $request->input('from');
        $to = $request->input('to');

        if ($tab === 'keluar') {
            $q = BhpKebersihanStockMovement::with('barang')
                ->where('tipe', BhpKebersihanStockMovement::TIPE_KELUAR)
                ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))
                ->when($to, fn ($q) => $q->whereDate('created_at', '<=', $to))
                ->when($request->filled('barang_id'), fn ($q) => $q->where('barang_id', $request->input('barang_id')))
                ->orderByDesc('created_at');

            $items = $all ? $q->get() : $q->limit(50)->get();

            return $items->map(fn ($m) => [
                $m->created_at?->format('Y-m-d H:i'),
                $m->barang?->kode_barang,
                $m->barang?->nama_barang,
                $m->jumlah,
                $m->sumber,
                $m->catatan,
            ])->toArray();
        }

        if ($tab === 'stok') {
            $barangs = BhpKebersihanBarang::where('status', 'aktif')
                ->when($request->filled('barang_id'), fn ($q) => $q->where('id', $request->input('barang_id')))
                ->orderBy('kode_barang')
                ->get();
            $ringkasan = $this->stock->ringkasanBanyak($barangs, $from, $to);

            return $barangs->map(function ($b) use ($ringkasan) {
                $r = $ringkasan[$b->id] ?? ['awal' => 0, 'masuk' => 0, 'keluar' => 0, 'akhir' => 0];

                return [$b->kode_barang, $b->nama_barang, $b->satuan, $r['awal'], $r['masuk'], $r['keluar'], $r['akhir']];
            })->toArray();
        }

        if ($tab === 'opname') {
            $q = BhpKebersihanStockOpname::withCount('details')
                ->when($from, fn ($q) => $q->whereDate('tanggal_opname', '>=', $from))
                ->when($to, fn ($q) => $q->whereDate('tanggal_opname', '<=', $to))
                ->orderByDesc('tanggal_opname');
            $items = $all ? $q->get() : $q->limit(50)->get();

            return $items->map(fn ($o) => [
                $o->tanggal_opname?->format('Y-m-d'),
                $o->catatan,
                $o->details_count,
            ])->toArray();
        }

        $q = BhpKebersihanPermintaan::with('barang')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($from, fn ($q) => $q->whereDate('tanggal_pengambilan', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('tanggal_pengambilan', '<=', $to))
            ->when($request->filled('barang_id'), fn ($q) => $q->where('barang_id', $request->input('barang_id')))
            ->orderByDesc('created_at');
        $items = $all ? $q->get() : $q->limit(50)->get();

        return $items->map(fn ($p) => [
            $p->nomor_permintaan,
            $p->tanggal_pengambilan?->format('Y-m-d'),
            $p->barang?->nama_barang,
            $p->nama_pengambil,
            $p->jumlah_diminta,
            $p->statusLabel(),
        ])->toArray();
    }
}
