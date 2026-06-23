<?php

declare(strict_types=1);

namespace App\Http\Controllers\BhpKebersihanAdmin;

use App\Exports\Persediaan\SimpleArrayExport;
use App\Http\Controllers\Controller;
use App\Models\BhpKebersihan\BhpKebersihanBarang;
use App\Models\BhpKebersihan\BhpKebersihanStockOpname;
use App\Models\BhpKebersihan\BhpKebersihanStockOpnameDetail;
use App\Services\BhpKebersihan\StockService;
use App\Support\BhpKebersihan\BhpAdminSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StockOpnameController extends Controller
{
    public function __construct(private StockService $stock)
    {
    }

    public function index(): View
    {
        $opname = BhpKebersihanStockOpname::withCount('details')
            ->orderByDesc('tanggal_opname')
            ->orderByDesc('id')
            ->paginate(15);

        return view('bhp_kebersihan_admin.opname.index', ['opname' => $opname]);
    }

    public function create(): View
    {
        $barangs = BhpKebersihanBarang::where('status', 'aktif')->orderBy('kode_barang')->get();
        $rows = $barangs->map(fn ($b) => [
            'id'     => $b->id,
            'kode'   => $b->kode_barang,
            'nama'   => $b->nama_barang,
            'satuan' => $b->satuan,
            'sistem' => $this->stock->stokSaatIni($b),
        ]);

        return view('bhp_kebersihan_admin.opname.create', ['rows' => $rows]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'tanggal_opname'      => ['required', 'date'],
            'catatan'             => ['nullable', 'string', 'max:1000'],
            'items'               => ['required', 'array', 'min:1'],
            'items.*.barang_id'   => ['required', 'integer'],
            'items.*.stok_sistem' => ['required', 'integer'],
            'items.*.stok_fisik'  => ['nullable', 'integer', 'min:0'],
            'items.*.keterangan'  => ['nullable', 'string', 'max:255'],
        ]);

        $barangs = BhpKebersihanBarang::all()->keyBy('id');
        $createdBy = BhpAdminSession::userId($request);

        $opnameId = DB::transaction(function () use ($data, $barangs, $createdBy) {
            $opname = BhpKebersihanStockOpname::create([
                'tanggal_opname' => $data['tanggal_opname'],
                'catatan'        => $data['catatan'] ?? null,
                'created_by'     => $createdBy,
            ]);

            foreach ($data['items'] as $row) {
                $barang = $barangs->get((int) $row['barang_id']);
                if ($barang === null) {
                    continue;
                }

                $sistem = (int) $row['stok_sistem'];
                $fisik = (int) ($row['stok_fisik'] ?? $sistem);
                $selisih = $fisik - $sistem;

                BhpKebersihanStockOpnameDetail::create([
                    'opname_id'   => $opname->id,
                    'barang_id'   => $barang->id,
                    'stok_sistem' => $sistem,
                    'stok_fisik'  => $fisik,
                    'selisih'     => $selisih,
                    'keterangan'  => $row['keterangan'] ?? null,
                ]);

                if ($selisih !== 0) {
                    $this->stock->recordKoreksiOpname(
                        $barang,
                        $fisik,
                        $opname->id,
                        $row['keterangan'] ?? null,
                        $createdBy,
                    );
                }
            }

            return $opname->id;
        });

        return redirect()->route('bhp.admin.opname.show', $opnameId)
            ->with('success', 'Stock opname berhasil disimpan.');
    }

    public function show(int $id): View
    {
        $opname = BhpKebersihanStockOpname::with(['details.barang'])->findOrFail($id);

        return view('bhp_kebersihan_admin.opname.show', ['opname' => $opname]);
    }

    public function exportExcel(int $id): BinaryFileResponse
    {
        $opname = BhpKebersihanStockOpname::with(['details.barang'])->findOrFail($id);

        $headings = ['Kode', 'Nama Barang', 'Stok Sistem', 'Stok Fisik', 'Selisih', 'Keterangan'];
        $data = $opname->details->map(fn ($d) => [
            $d->barang?->kode_barang ?? '-',
            $d->barang?->nama_barang ?? '-',
            $d->stok_sistem,
            $d->stok_fisik,
            $d->selisih,
            $d->keterangan,
        ])->toArray();

        return Excel::download(
            new SimpleArrayExport($data, $headings),
            'opname-bhp-' . $opname->tanggal_opname->format('Y-m-d') . '.xlsx',
        );
    }
}
