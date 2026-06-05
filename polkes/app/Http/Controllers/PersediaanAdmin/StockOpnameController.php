<?php

declare(strict_types=1);

namespace App\Http\Controllers\PersediaanAdmin;

use App\Exports\Persediaan\SimpleArrayExport;
use App\Http\Controllers\Controller;
use App\Models\Persediaan\PersediaanBarang;
use App\Models\Persediaan\PersediaanStockMovement;
use App\Models\Persediaan\PersediaanStockOpname;
use App\Models\Persediaan\PersediaanStockOpnameItem;
use App\Services\Persediaan\OpnameLaporanService;
use App\Services\Persediaan\StockService;
use App\Support\Persediaan\UnitKerja;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class StockOpnameController extends Controller
{
    public function __construct(
        private StockService $stock,
        private OpnameLaporanService $laporan,
    ) {
    }

    public function index(): View
    {
        $opname = PersediaanStockOpname::withCount('items')
            ->orderByDesc('tanggal_opname')->orderByDesc('id')->paginate(15);

        return view('persediaan_admin.opname.index', [
            'opname' => $opname,
        ]);
    }

    public function create(Request $request): View
    {
        $unit = $request->input('unit', UnitKerja::KEPERAWATAN);
        if (! UnitKerja::isValid($unit)) {
            $unit = UnitKerja::KEPERAWATAN;
        }

        $barangs = PersediaanBarang::where('is_active', true)->where('unit_kerja', $unit)
            ->orderBy('kode_barang')->get();
        $ringkasan = $this->stock->ringkasanBanyak($barangs);

        $rows = $barangs->map(fn ($b) => [
            'id'     => $b->id,
            'kode'   => $b->kode_barang,
            'nama'   => $b->nama_barang,
            'satuan' => $b->satuan,
            'sistem' => $ringkasan[$b->id]['akhir'] ?? (int) $b->stok_awal,
        ]);

        return view('persediaan_admin.opname.create', [
            'unit'  => $unit,
            'units' => UnitKerja::all(),
            'rows'  => $rows,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $admin = auth('persediaan_admin')->user() ?? auth()->user();

        if ($admin === null) {
            return redirect()->route('persediaan.admin.login.show')
                ->with('error', 'Silakan login admin persediaan terlebih dahulu.');
        }

        $data = $request->validate([
            'unit_kerja'           => ['required', 'in:' . implode(',', UnitKerja::all())],
            'tanggal_opname'       => ['required', 'date'],
            'catatan'              => ['nullable', 'string', 'max:1000'],
            'items'                => ['required', 'array', 'min:1'],
            'items.*.barang_id'    => ['required', 'integer'],
            'items.*.stok_sistem'  => ['required', 'integer'],
            'items.*.stok_fisik'   => ['nullable', 'integer', 'min:0'],
            'items.*.keterangan'   => ['nullable', 'string', 'max:255'],
        ]);

        $barangs = PersediaanBarang::where('unit_kerja', $data['unit_kerja'])->get()->keyBy('id');
        $createdBy = (int) $admin->id;

        $opnameId = DB::transaction(function () use ($data, $barangs, $createdBy) {
            $opname = PersediaanStockOpname::create([
                'unit_kerja'     => $data['unit_kerja'],
                'tanggal_opname' => $data['tanggal_opname'],
                'created_by'     => $createdBy,
                'catatan'        => $data['catatan'] ?? null,
            ]);

            foreach ($data['items'] as $row) {
                $barang = $barangs->get((int) $row['barang_id']);
                if ($barang === null) {
                    continue;
                }

                $sistem = (int) $row['stok_sistem'];
                $fisik = (int) ($row['stok_fisik'] ?? $sistem);
                $selisih = $fisik - $sistem;

                PersediaanStockOpnameItem::create([
                    'opname_id'   => $opname->id,
                    'barang_id'   => $barang->id,
                    'kode_barang' => $barang->kode_barang,
                    'nama_barang' => $barang->nama_barang,
                    'satuan'      => $barang->satuan,
                    'stok_sistem' => $sistem,
                    'stok_fisik'  => $fisik,
                    'selisih'     => $selisih,
                    'keterangan'  => $row['keterangan'] ?? null,
                ]);

                if ($selisih !== 0) {
                    $this->stock->recordMovement([
                        'barang_id'  => $barang->id,
                        'unit_kerja' => $data['unit_kerja'],
                        'tipe'       => PersediaanStockMovement::TIPE_OPNAME,
                        'ref_table'  => 'persediaan_stock_opname',
                        'ref_id'     => $opname->id,
                        'qty_masuk'  => $selisih > 0 ? $selisih : 0,
                        'qty_keluar' => $selisih < 0 ? abs($selisih) : 0,
                        'keterangan' => 'Penyesuaian stock opname ' . $opname->tanggal_opname,
                        'created_by' => $createdBy,
                    ]);
                }
            }

            return $opname->id;
        });

        return redirect()->route('persediaan.admin.opname.show', $opnameId)
            ->with('success', 'Stock opname tersimpan. Laporan transaksi persediaan siap diunduh.');
    }

    public function show(int $id): View
    {
        $opname = PersediaanStockOpname::with('items')->findOrFail($id);

        return view('persediaan_admin.opname.show', [
            'opname' => $opname,
            'rekap'  => $this->rekap($opname),
        ]);
    }

    public function laporanPdf(int $id): Response
    {
        $opname = PersediaanStockOpname::with('items')->findOrFail($id);
        $laporan = $this->laporan->build($opname);

        $pdf = Pdf::loadView('persediaan_admin.opname.laporan-transaksi', [
            'laporan'         => $laporan,
            'kodeUapkpb'      => OpnameLaporanService::KODE_UAPKPB,
            'jenisTransaksi'  => OpnameLaporanService::JENIS_TRANSAKSI,
            'formatter'       => $this->laporan,
        ])->setPaper('a4', 'portrait');

        $filename = 'Daftar-Transaksi-Persediaan-Opname-' . $opname->id . '.pdf';

        return $pdf->download($filename);
    }

    public function laporanExcel(int $id): BinaryFileResponse
    {
        $opname = PersediaanStockOpname::with('items')->findOrFail($id);
        $laporan = $this->laporan->build($opname);

        $rows = [];
        $rows[] = ['DAFTAR TRANSAKSI PERSEDIAAN'];
        $rows[] = ['UNTUK PERIODE YANG BERAKHIR TANGGAL ' . optional($laporan['tanggal_opname'])->format('d-m-Y')];
        $rows[] = ['TAHUN ANGGARAN : ' . $laporan['tahun_anggaran']];
        $rows[] = [];
        $rows[] = ['NAMA', $laporan['nama_admin']];
        $rows[] = ['KODE UAPKPB', OpnameLaporanService::KODE_UAPKPB];
        $rows[] = [];
        $rows[] = ['JENIS TRANSAKSI:', OpnameLaporanService::JENIS_TRANSAKSI];
        $rows[] = [];
        $rows[] = ['KODE', 'URAIAN', 'KUANTITAS', 'RUPIAH'];

        foreach ($laporan['rows'] as $row) {
            $rows[] = [
                $row['kode'],
                $row['uraian'],
                $row['kuantitas'],
                $this->laporan->formatRupiah($row['rupiah']),
            ];
        }

        $rows[] = [];
        $rows[] = ['Jumlah', '', $laporan['total_kuantitas'], ''];
        $rows[] = ['Total Jumlah', '', '', $this->laporan->formatRupiah($laporan['total_rupiah'])];

        return Excel::download(
            new SimpleArrayExport($rows, []),
            'Daftar-Transaksi-Persediaan-Opname-' . $opname->id . '.xlsx',
        );
    }

    public function beritaAcara(int $id): Response
    {
        $opname = PersediaanStockOpname::with('items')->findOrFail($id);

        $pdf = Pdf::loadView('persediaan_admin.opname.berita-acara', [
            'opname' => $opname,
            'rekap'  => $this->rekap($opname),
        ])->setPaper('a4', 'portrait');

        return $pdf->stream('Berita-Acara-Opname-' . $opname->id . '.pdf');
    }

    public function exportExcel(int $id): BinaryFileResponse
    {
        $opname = PersediaanStockOpname::with('items')->findOrFail($id);

        $headings = ['Kode Barang', 'Nama Barang', 'Satuan', 'Stok Sistem', 'Stok Fisik', 'Selisih', 'Keterangan'];
        $data = $opname->items->map(fn ($it) => [
            $it->kode_barang, $it->nama_barang, $it->satuan,
            $it->stok_sistem, $it->stok_fisik, $it->selisih, $it->keterangan,
        ])->toArray();

        return Excel::download(new SimpleArrayExport($data, $headings), 'stock-opname-' . $opname->id . '.xlsx');
    }

    /**
     * @return array{kurang:int,lebih:int}
     */
    private function rekap(PersediaanStockOpname $opname): array
    {
        $items = $opname->items;

        return [
            'kurang' => $items->filter(fn ($it) => $it->selisih < 0)->count(),
            'lebih'  => $items->filter(fn ($it) => $it->selisih > 0)->count(),
        ];
    }
}
