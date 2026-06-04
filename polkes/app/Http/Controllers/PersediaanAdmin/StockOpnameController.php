<?php

declare(strict_types=1);

namespace App\Http\Controllers\PersediaanAdmin;

use App\Exports\Persediaan\SimpleArrayExport;
use App\Http\Controllers\Controller;
use App\Models\Persediaan\PersediaanBarang;
use App\Models\Persediaan\PersediaanStockMovement;
use App\Models\Persediaan\PersediaanStockOpname;
use App\Models\Persediaan\PersediaanStockOpnameItem;
use App\Services\Persediaan\StockService;
use App\Support\Persediaan\UnitKerja;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class StockOpnameController extends Controller
{
    public function __construct(private StockService $stock)
    {
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
        $data = $request->validate([
            'unit_kerja'           => ['required', 'in:' . implode(',', UnitKerja::all())],
            'tanggal_opname'       => ['required', 'date'],
            'catatan'              => ['nullable', 'string', 'max:1000'],
            'items'                => ['required', 'array', 'min:1'],
            'items.*.barang_id'    => ['required', 'integer'],
            'items.*.stok_sistem'  => ['required', 'integer'],
            'items.*.stok_fisik'   => ['nullable', 'integer', 'min:0'],
            'items.*.kondisi'      => ['nullable', 'string'],
            'items.*.keterangan'   => ['nullable', 'string', 'max:255'],
        ]);

        $barangs = PersediaanBarang::where('unit_kerja', $data['unit_kerja'])->get()->keyBy('id');

        DB::transaction(function () use ($data, $barangs, $request) {
            $opname = PersediaanStockOpname::create([
                'unit_kerja'     => $data['unit_kerja'],
                'tanggal_opname' => $data['tanggal_opname'],
                'created_by'     => $request->user()->id,
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

                $kondisi = $row['kondisi'] ?? null;
                if (! in_array($kondisi, [
                    PersediaanStockOpnameItem::KONDISI_RUSAK,
                    PersediaanStockOpnameItem::KONDISI_KADALUARSA,
                ], true)) {
                    $kondisi = $selisih === 0
                        ? PersediaanStockOpnameItem::KONDISI_SESUAI
                        : ($selisih < 0
                            ? PersediaanStockOpnameItem::KONDISI_KURANG
                            : PersediaanStockOpnameItem::KONDISI_LEBIH);
                }

                PersediaanStockOpnameItem::create([
                    'opname_id'   => $opname->id,
                    'barang_id'   => $barang->id,
                    'kode_barang' => $barang->kode_barang,
                    'nama_barang' => $barang->nama_barang,
                    'satuan'      => $barang->satuan,
                    'stok_sistem' => $sistem,
                    'stok_fisik'  => $fisik,
                    'selisih'     => $selisih,
                    'kondisi'     => $kondisi,
                    'keterangan'  => $row['keterangan'] ?? null,
                ]);

                // Penyesuaian stok otomatis bila ada selisih
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
                        'created_by' => $request->user()->id,
                    ]);
                }
            }
        });

        return redirect()->route('persediaan.admin.opname.index')
            ->with('success', 'Stock opname tersimpan, stok disesuaikan otomatis bila ada selisih.');
    }

    public function show(int $id): View
    {
        $opname = PersediaanStockOpname::with('items')->findOrFail($id);

        return view('persediaan_admin.opname.show', [
            'opname' => $opname,
            'rekap'  => $this->rekap($opname),
        ]);
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

        $headings = ['Kode Barang', 'Nama Barang', 'Satuan', 'Stok Sistem', 'Stok Fisik', 'Selisih', 'Kondisi', 'Keterangan'];
        $data = $opname->items->map(fn ($it) => [
            $it->kode_barang, $it->nama_barang, $it->satuan,
            $it->stok_sistem, $it->stok_fisik, $it->selisih, $it->kondisi, $it->keterangan,
        ])->toArray();

        return Excel::download(new SimpleArrayExport($data, $headings), 'stock-opname-' . $opname->id . '.xlsx');
    }

    /**
     * @return array{kurang:int,lebih:int,rusak:int,kadaluarsa:int,sesuai:int}
     */
    private function rekap(PersediaanStockOpname $opname): array
    {
        $items = $opname->items;

        return [
            'kurang'     => $items->where('kondisi', PersediaanStockOpnameItem::KONDISI_KURANG)->count(),
            'lebih'      => $items->where('kondisi', PersediaanStockOpnameItem::KONDISI_LEBIH)->count(),
            'rusak'      => $items->where('kondisi', PersediaanStockOpnameItem::KONDISI_RUSAK)->count(),
            'kadaluarsa' => $items->where('kondisi', PersediaanStockOpnameItem::KONDISI_KADALUARSA)->count(),
            'sesuai'     => $items->where('kondisi', PersediaanStockOpnameItem::KONDISI_SESUAI)->count(),
        ];
    }
}
