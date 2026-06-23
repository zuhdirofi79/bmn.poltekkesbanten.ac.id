<?php

declare(strict_types=1);

namespace App\Http\Controllers\BhpKebersihanAdmin;

use App\Exports\Persediaan\SimpleArrayExport;
use App\Http\Controllers\Controller;
use App\Models\BhpKebersihan\BhpKebersihanBarang;
use App\Services\BhpKebersihan\BarcodeService;
use App\Services\BhpKebersihan\StockService;
use App\Support\BhpKebersihan\BhpAccess;
use App\Support\BhpKebersihan\BhpAdminSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MasterBarangController extends Controller
{
    public function __construct(
        private StockService $stock,
        private BarcodeService $barcode,
    ) {
    }

    public function index(Request $request): View
    {
        $query = BhpKebersihanBarang::query()
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%' . $request->input('q') . '%';
                $q->where(fn ($w) => $w->where('kode_barang', 'like', $term)->orWhere('nama_barang', 'like', $term));
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->orderBy('kode_barang');

        $barang = $query->paginate(20)->withQueryString();
        $stokMap = [];
        foreach ($barang as $b) {
            $stokMap[$b->id] = $this->stock->stokSaatIni($b);
        }

        return view('bhp_kebersihan_admin.master-barang.index', [
            'barang'  => $barang,
            'stokMap' => $stokMap,
            'filters' => $request->only(['q', 'status']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (! BhpAccess::canManageMaster(BhpAdminSession::role($request))) {
            abort(403);
        }

        $data = $this->validateData($request);
        $userId = BhpAdminSession::userId($request);

        DB::transaction(function () use ($data, $userId) {
            $barang = BhpKebersihanBarang::create([
                'kode_barang'   => $data['kode_barang'],
                'nama_barang'   => $data['nama_barang'],
                'satuan'        => $data['satuan'],
                'stok_awal'     => (int) $data['stok_awal'],
                'harga_satuan'  => $data['harga_satuan'] ?? null,
                'barcode_token' => $this->barcode->generateToken(),
                'status'        => 'aktif',
                'created_by'    => $userId,
            ]);

            if ((int) $data['stok_awal'] > 0) {
                $this->stock->recordMasuk(
                    $barang,
                    (int) $data['stok_awal'],
                    \App\Models\BhpKebersihan\BhpKebersihanStockMovement::SUMBER_MANUAL,
                    $barang->id,
                    'Stok awal master barang',
                    $userId,
                );
            }
        });

        return back()->with('success', 'Barang BHP berhasil ditambahkan.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        if (! BhpAccess::canManageMaster(BhpAdminSession::role($request))) {
            abort(403);
        }

        $barang = BhpKebersihanBarang::findOrFail($id);
        $data = $this->validateData($request, $id);

        $barang->update([
            'kode_barang'  => $data['kode_barang'],
            'nama_barang'  => $data['nama_barang'],
            'satuan'       => $data['satuan'],
            'harga_satuan' => $data['harga_satuan'] ?? null,
            'status'       => $request->input('status', 'aktif'),
            'updated_by'   => BhpAdminSession::userId($request),
        ]);

        return back()->with('success', 'Barang BHP berhasil diperbarui.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $barang = BhpKebersihanBarang::findOrFail($id);
        $barang->status = 'nonaktif';
        $barang->save();

        return back()->with('success', 'Barang dinonaktifkan.');
    }

    public function exportExcel(Request $request): BinaryFileResponse
    {
        $barangs = BhpKebersihanBarang::query()
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%' . $request->input('q') . '%';
                $q->where(fn ($w) => $w->where('kode_barang', 'like', $term)->orWhere('nama_barang', 'like', $term));
            })
            ->orderBy('kode_barang')
            ->get();

        $headings = ['Kode Barang', 'Nama Barang', 'Satuan', 'Stok Awal', 'Stok Saat Ini', 'Harga Satuan', 'Status', 'Scan URL', 'Barcode File'];
        $data = $barangs->map(fn ($b) => [
            $b->kode_barang,
            $b->nama_barang,
            $b->satuan,
            $b->stok_awal,
            $this->stock->stokSaatIni($b),
            $b->harga_satuan,
            $b->status,
            $b->barcodeUrl(),
            $b->barcode_path ? asset($b->barcode_path) : '-',
        ])->toArray();

        return Excel::download(new SimpleArrayExport($data, $headings), 'master-barang-bhp-kebersihan.xlsx');
    }

    public function barcodePrint(int $id): View|RedirectResponse
    {
        $barang = BhpKebersihanBarang::findOrFail($id);

        if (! $this->barcode->hasValidFile($barang)) {
            return redirect()->route('bhp.admin.master-barang.index')
                ->with('error', 'Barcode belum digenerate. Klik Generate Barcode terlebih dahulu.');
        }

        return view('bhp_kebersihan_admin.master-barang.barcode', [
            'barang'    => $barang,
            'imageUrl'  => $barang->barcodeFileUrl(),
            'publicUrl' => $barang->barcodeUrl(),
            'autoPrint' => true,
        ]);
    }

    public function barcodeDownload(int $id): BinaryFileResponse|RedirectResponse
    {
        $barang = BhpKebersihanBarang::findOrFail($id);

        if (! $this->barcode->hasValidFile($barang)) {
            return back()->with('error', 'File barcode belum tersedia.');
        }

        return response()->download(
            public_path($barang->barcode_path),
            basename((string) $barang->barcode_path),
            ['Content-Type' => 'image/png'],
        );
    }

    public function generateBarcode(Request $request, int $id): RedirectResponse
    {
        if (! BhpAccess::canManageMaster(BhpAdminSession::role($request))) {
            abort(403);
        }

        $barang = BhpKebersihanBarang::findOrFail($id);

        try {
            $this->barcode->generateAndSave($barang);
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Gagal generate barcode: ' . $e->getMessage());
        }

        return back()->with('success', 'Barcode berhasil digenerate untuk ' . $barang->kode_barang . '.');
    }

    public function generateAllBarcodes(Request $request): RedirectResponse
    {
        if (! BhpAccess::canManageMaster(BhpAdminSession::role($request))) {
            abort(403);
        }

        $query = BhpKebersihanBarang::query()->where('status', 'aktif');
        $ids = array_values(array_filter(array_map('intval', (array) $request->input('ids', []))));
        if ($ids !== []) {
            $query->whereIn('id', $ids);
        }

        $generated = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($query->orderBy('kode_barang')->get() as $barang) {
            if ($this->barcode->hasValidFile($barang)) {
                $skipped++;

                continue;
            }

            try {
                $this->barcode->generateAndSave($barang);
                $generated++;
            } catch (\Throwable $e) {
                $failed++;
                report($e);
            }
        }

        if ($generated === 0 && $failed === 0) {
            return back()->with('success', 'Semua barang sudah memiliki file barcode.');
        }

        $msg = "Generate selesai: {$generated} barcode dibuat, {$skipped} sudah ada.";
        if ($failed > 0) {
            $msg .= " {$failed} gagal.";
        }

        return back()->with($failed > 0 ? 'error' : 'success', $msg);
    }

    public function barcodeBatch(Request $request): View|RedirectResponse
    {
        $ids = array_values(array_filter(array_map('intval', (array) $request->input('ids', []))));
        $query = BhpKebersihanBarang::query()->where('status', 'aktif');

        if ($ids !== []) {
            $query->whereIn('id', $ids);
        }

        $barangs = $query->orderBy('kode_barang')->get()
            ->filter(fn (BhpKebersihanBarang $b) => $this->barcode->hasValidFile($b));

        if ($barangs->isEmpty()) {
            return redirect()->route('bhp.admin.master-barang.index')
                ->with('error', 'Tidak ada barcode yang siap dicetak. Generate barcode terlebih dahulu.');
        }

        $items = $barangs->map(fn (BhpKebersihanBarang $b) => [
            'barang'    => $b,
            'imageUrl'  => $b->barcodeFileUrl(),
            'publicUrl' => $b->barcodeUrl(),
        ]);

        return view('bhp_kebersihan_admin.master-barang.barcode-batch', [
            'items' => $items,
        ]);
    }

    public function regenerateBarcode(Request $request, int $id): RedirectResponse
    {
        if (! BhpAccess::canManageMaster(BhpAdminSession::role($request))) {
            abort(403);
        }

        $barang = BhpKebersihanBarang::findOrFail($id);

        if ($barang->barcode_path && is_file(public_path($barang->barcode_path))) {
            @unlink(public_path($barang->barcode_path));
        }

        $barang->barcode_token = $this->barcode->generateToken();
        $barang->barcode_path = null;
        $barang->save();

        try {
            $this->barcode->generateAndSave($barang);
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Token barcode diperbarui, tetapi gagal generate file: ' . $e->getMessage());
        }

        return back()->with('success', 'Barcode token & file berhasil digenerate ulang.');
    }

    /**
     * @return array<string,mixed>
     */
    private function validateData(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'kode_barang'  => ['required', 'string', 'max:100', Rule::unique('bhp_kebersihan_barang', 'kode_barang')->ignore($id)],
            'nama_barang'  => ['required', 'string', 'max:200'],
            'satuan'       => ['required', 'string', 'max:50'],
            'stok_awal'    => [$id === null ? 'required' : 'nullable', 'integer', 'min:0'],
            'harga_satuan' => ['nullable', 'numeric', 'min:0'],
        ]);
    }
}
