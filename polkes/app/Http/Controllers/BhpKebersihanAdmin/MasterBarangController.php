<?php

declare(strict_types=1);

namespace App\Http\Controllers\BhpKebersihanAdmin;

use App\Exports\Persediaan\SimpleArrayExport;
use App\Http\Controllers\Controller;
use App\Models\BhpKebersihan\BhpKebersihanBarang;
use App\Services\BhpKebersihan\BarcodeService;
use App\Services\BhpKebersihan\FotoService;
use App\Services\BhpKebersihan\StockService;
use App\Support\BhpKebersihan\BhpAccess;
use App\Support\BhpKebersihan\BhpAdminSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MasterBarangController extends Controller
{
    public function __construct(
        private StockService $stock,
        private BarcodeService $barcode,
        private FotoService $foto,
    ) {
    }

    public function index(Request $request): View
    {
        $query = BhpKebersihanBarang::query()
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%' . $request->input('q') . '%';
                $q->where(fn ($w) => $w->where('kode_barang', 'like', $term)
                    ->orWhere('nama_barang', 'like', $term)
                    ->orWhere('merk', 'like', $term));
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->orderBy('kode_barang');

        $barang = $query->paginate(20)->withQueryString();
        $stokMap = [];
        $barcodeUrlMap = [];
        $fotoUrlMap = [];
        foreach ($barang as $b) {
            $stokMap[$b->id] = $this->stock->stokSaatIni($b);
            $barcodeUrlMap[$b->id] = $this->barcode->fileUrl($b);
            $fotoUrlMap[$b->id] = $b->fotoUrl();
        }

        return view('bhp_kebersihan_admin.master-barang.index', [
            'barang'           => $barang,
            'stokMap'          => $stokMap,
            'barcodeUrlMap'    => $barcodeUrlMap,
            'fotoUrlMap'       => $fotoUrlMap,
            'filters'          => $request->only(['q', 'status']),
            'canManageMaster'  => BhpAccess::canManageMaster(BhpAdminSession::role($request)),
        ]);
    }

    public function show(int $id): View
    {
        $barang = BhpKebersihanBarang::findOrFail($id);
        $riwayat = $barang->movements()
            ->with('creator')
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        return view('bhp_kebersihan_admin.master-barang.show', [
            'barang'     => $barang,
            'stokSaatIni'=> $this->stock->stokSaatIni($barang),
            'barcodeUrl' => $this->barcode->fileUrl($barang),
            'fotoUrl'    => $barang->fotoUrl(),
            'riwayat'    => $riwayat,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (! BhpAccess::canManageMaster(BhpAdminSession::role($request))) {
            abort(403);
        }

        $data = $this->validateData($request);
        $userId = BhpAdminSession::userId($request);

        DB::transaction(function () use ($data, $userId, $request) {
            $barang = BhpKebersihanBarang::create([
                'kode_barang'   => $data['kode_barang'],
                'nama_barang'   => $data['nama_barang'],
                'merk'          => $data['merk'] ?? null,
                'satuan'        => $data['satuan'],
                'stok_awal'     => (int) $data['stok_awal'],
                'harga_satuan'  => $data['harga_satuan'] ?? null,
                'barcode_token' => $this->barcode->generateToken(),
                'status'        => 'aktif',
                'created_by'    => $userId,
            ]);

            if ($request->hasFile('foto')) {
                $this->foto->store($barang, $request->file('foto'));
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

        try {
            $data = $this->validateData($request, $id);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()
                ->withErrors($e->errors())
                ->withInput()
                ->with('open_edit_id', $id);
        }

        $barang->update([
            'nama_barang'  => $data['nama_barang'],
            'merk'         => $data['merk'] ?? null,
            'satuan'       => $data['satuan'],
            'harga_satuan' => $data['harga_satuan'] ?? null,
            'status'       => $data['status'],
            'updated_by'   => BhpAdminSession::userId($request),
        ]);

        if ($request->hasFile('foto')) {
            $this->foto->store($barang->fresh(), $request->file('foto'));
        }

        return back()->with('success', 'Data barang berhasil diperbarui.');
    }

    public function koreksiStok(Request $request, int $id): RedirectResponse
    {
        if (! BhpAccess::canManageMaster(BhpAdminSession::role($request))) {
            abort(403);
        }

        try {
            $data = $request->validate([
                'arah'             => ['required', 'in:tambah,kurangi'],
                'jumlah'           => ['required', 'integer', 'min:1'],
                'catatan'          => ['required', 'string', 'min:3', 'max:1000'],
                'idempotency_key'  => ['nullable', 'string', 'max:80'],
            ]);
        } catch (ValidationException $e) {
            return back()
                ->withErrors($e->errors())
                ->withInput()
                ->with('open_koreksi_id', $id);
        }

        $barang = BhpKebersihanBarang::findOrFail($id);

        try {
            $movement = $this->stock->recordKoreksiManual(
                $barang,
                $data['arah'],
                (int) $data['jumlah'],
                $data['catatan'],
                BhpAdminSession::userId($request),
                $data['idempotency_key'] ?? null,
            );
        } catch (\InvalidArgumentException $e) {
            return back()
                ->withErrors(['jumlah' => $e->getMessage()])
                ->withInput()
                ->with('open_koreksi_id', $id);
        }

        $delta = $movement->signedDelta();
        $deltaLabel = $delta > 0 ? '+' . $delta : (string) $delta;

        return back()
            ->with('success', 'Stok berhasil diperbarui.')
            ->with('stok_koreksi', [
                'sebelum' => (int) $movement->stok_sebelum,
                'delta'   => $deltaLabel,
                'sesudah' => (int) $movement->stok_sesudah,
            ]);
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        if (! BhpAccess::canManageMaster(BhpAdminSession::role($request))) {
            abort(403);
        }

        $barang = BhpKebersihanBarang::findOrFail($id);
        $result = $this->hapusAtauNonaktifkan($barang, BhpAdminSession::userId($request));

        if ($result === 'deleted') {
            return back()->with('success', 'Barang berhasil dihapus dari Master Barang.');
        }

        return back()->with(
            'success',
            'Barang sudah memiliki histori transaksi sehingga tidak dihapus permanen. Barang dinonaktifkan.'
        );
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        if (! BhpAccess::canManageMaster(BhpAdminSession::role($request))) {
            abort(403);
        }

        $ids = array_values(array_unique(array_filter(
            array_map('intval', (array) $request->input('ids', [])),
            static fn (int $id): bool => $id > 0
        )));

        if ($ids === []) {
            return back()->with('error', 'Pilih minimal satu barang.');
        }

        if (count($ids) > 100) {
            return back()->with('error', 'Maksimal 100 barang per proses.');
        }

        $userId = BhpAdminSession::userId($request);
        $items = BhpKebersihanBarang::query()->whereIn('id', $ids)->get()->keyBy('id');

        $deleted = 0;
        $nonaktif = 0;
        $failed = 0;

        foreach ($ids as $id) {
            $barang = $items->get($id);
            if ($barang === null) {
                $failed++;

                continue;
            }

            try {
                $result = $this->hapusAtauNonaktifkan($barang, $userId);
                if ($result === 'deleted') {
                    $deleted++;
                } else {
                    $nonaktif++;
                }
            } catch (\Throwable $e) {
                report($e);
                $failed++;
            }
        }

        return back()
            ->with('success', 'Proses selesai.')
            ->with('bulk_delete_summary', [
                'deleted'  => $deleted,
                'nonaktif' => $nonaktif,
                'failed'   => $failed,
            ]);
    }

    /**
     * @return 'deleted'|'nonaktif'
     */
    private function hapusAtauNonaktifkan(BhpKebersihanBarang $barang, ?int $userId): string
    {
        return DB::transaction(function () use ($barang, $userId) {
            $locked = BhpKebersihanBarang::query()->whereKey($barang->id)->lockForUpdate()->first();
            if ($locked === null) {
                throw new \RuntimeException('Barang tidak ditemukan.');
            }

            if ($locked->hasTransaksiHistori()) {
                $this->tandaiNonaktif($locked, $userId);

                return 'nonaktif';
            }

            $this->hapusFileFisikBarangTidakTerpakai($locked);

            try {
                $locked->delete();
            } catch (\Throwable $e) {
                $this->tandaiNonaktif($locked, $userId);

                return 'nonaktif';
            }

            return 'deleted';
        });
    }

    private function tandaiNonaktif(BhpKebersihanBarang $barang, ?int $userId): void
    {
        $barang->status = 'nonaktif';
        if ($userId !== null) {
            $barang->updated_by = $userId;
        }
        $barang->save();
    }

    private function hapusFileFisikBarangTidakTerpakai(BhpKebersihanBarang $barang): void
    {
        $this->foto->deleteStored($barang->foto_path);

        $absolute = $this->barcode->absoluteFilePath($barang);
        if ($absolute !== null && is_file($absolute)) {
            @unlink($absolute);
        }
    }

    public function exportExcel(Request $request): BinaryFileResponse
    {
        $barangs = BhpKebersihanBarang::query()
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%' . $request->input('q') . '%';
                $q->where(fn ($w) => $w->where('kode_barang', 'like', $term)
                    ->orWhere('nama_barang', 'like', $term)
                    ->orWhere('merk', 'like', $term));
            })
            ->orderBy('kode_barang')
            ->get();

        $headings = ['Kode Barang', 'Nama Barang', 'Merk', 'Satuan', 'Stok Awal', 'Stok Saat Ini', 'Harga Satuan', 'Status', 'Scan URL', 'Barcode File'];
        $data = $barangs->map(fn ($b) => [
            $b->kode_barang,
            $b->nama_barang,
            $b->merk,
            $b->satuan,
            $b->stok_awal,
            $this->stock->stokSaatIni($b),
            $b->harga_satuan,
            $b->status,
            $b->barcodeUrl(),
            $this->barcode->fileUrl($b) ?? '-',
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
            'imageUrl'  => $this->barcode->fileUrl($barang),
            'publicUrl' => $barang->barcodeUrl(),
            'autoPrint' => true,
        ]);
    }

    public function barcodeDownload(int $id): BinaryFileResponse|RedirectResponse
    {
        $barang = BhpKebersihanBarang::findOrFail($id);
        $absolute = $this->barcode->absoluteFilePath($barang);

        if ($absolute === null) {
            return back()->with('error', 'File barcode belum tersedia.');
        }

        $relative = $this->barcode->resolveRelativePath($barang);

        return response()->download(
            $absolute,
            $relative !== null ? basename($relative) : basename($absolute),
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
            'imageUrl'  => $this->barcode->fileUrl($b),
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
        $oldAbsolute = $this->barcode->absoluteFilePath($barang);

        if ($oldAbsolute !== null && is_file($oldAbsolute)) {
            @unlink($oldAbsolute);
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
        $rules = [
            'nama_barang'  => ['required', 'string', 'max:200'],
            'merk'         => ['nullable', 'string', 'max:255'],
            'satuan'       => ['required', 'string', 'max:50'],
            'stok_awal'    => [$id === null ? 'required' : 'nullable', 'integer', 'min:0'],
            'harga_satuan' => ['nullable', 'numeric', 'min:0'],
            'foto'         => ['nullable', 'file', 'max:5120', 'mimes:jpg,jpeg,png,webp'],
            'status'       => [$id === null ? 'nullable' : 'required', 'in:aktif,nonaktif'],
        ];

        if ($id === null) {
            $rules['kode_barang'] = ['required', 'string', 'max:100', Rule::unique('bhp_kebersihan_barang', 'kode_barang')];
        }

        return $request->validate($rules);
    }
}
