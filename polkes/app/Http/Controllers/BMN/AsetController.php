<?php

declare(strict_types=1);

namespace App\Http\Controllers\BMN;

use App\Exports\AsetBmnTemplateExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\BMN\StoreAsetRequest;
use App\Http\Requests\BMN\UpdateAsetRequest;
use App\Models\BmnSatker;
use App\Models\BMN\AsetBmn;
use App\Models\BMN\KategoriAset;
use App\Services\BMN\AsetBmnImportService;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class AsetController extends Controller
{
    public function __construct(
        private readonly AsetBmnImportService $asetBmnImportService
    ) {
    }

    public function index(Request $request): View
    {
        $filters = $request->only(['lokasi', 'kondisi', 'status']);

        $query = AsetBmn::with(['kategori', 'lokasiMaster'])
            ->filter($filters)
            ->latest();

        $aset = $query->paginate(20)->appends($filters);

        return view('bmn.aset.index', compact('aset', 'filters'));
    }

    public function create(): View
    {
        $kategori = KategoriAset::orderBy('nama_kategori')->get();
        $satkerList = BmnSatker::orderBy('sort_order')->get();

        return view('bmn.aset.create', compact('kategori', 'satkerList'));
    }

    public function store(StoreAsetRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $satker = BmnSatker::findOrFail((int) $validated['satker_id']);
        $data = [
            'kode_barang' => $validated['kode_barang'],
            'nup' => $validated['nup'] ?? null,
            'nama_barang' => $validated['nama_barang'],
            'kategori_id' => (int) $validated['kategori_id'],
            'nilai_perolehan' => $validated['nilai_perolehan'],
            'tanggal_perolehan' => $validated['tanggal_perolehan'] ?? null,
            'kondisi' => $validated['kondisi'],
            'pengguna_ruangan' => $validated['pengguna_ruangan'] ?? null,
            'satker_id' => $satker->id,
            'satker' => $satker->nama,
            'lokasi' => $request->lokasi,
            'status' => 'aktif',
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ];

        $aset = DB::transaction(function () use ($data): AsetBmn {
            return AsetBmn::create($data);
        });

        return redirect()
            ->route('bmn.aset.index')
            ->with('success', 'Aset '.$aset->kode_barang.' berhasil ditambahkan.');
    }

    public function edit(AsetBmn $aset): View
    {
        $aset->load(['histori' => function (Relation $q): void {
            $q->with('user')->limit(50);
        }]);

        $kategori = KategoriAset::orderBy('nama_kategori')->get();
        $satkerList = BmnSatker::orderBy('sort_order')->get();

        return view('bmn.aset.edit', compact('aset', 'kategori', 'satkerList'));
    }

    public function update(UpdateAsetRequest $request, AsetBmn $aset): RedirectResponse
    {
        $data = $request->validated();
        $satker = BmnSatker::findOrFail((int) $data['satker_id']);
        $data['satker_id'] = $satker->id;
        $data['satker'] = $satker->nama;
        $data['lokasi'] = $request->lokasi;
        $data['updated_by'] = auth()->id();

        if (($data['status'] ?? $aset->status) === 'dihapus') {
            $data['deleted_by'] = auth()->id();
            $data['deleted_at'] = $data['deleted_at'] ?? now();
        } elseif (($data['status'] ?? $aset->status) !== 'dihapus') {
            $data['deleted_by'] = null;
            $data['deleted_at'] = null;
        }

        DB::transaction(function () use ($aset, $data): void {
            $aset->update($data);
        });

        return redirect()
            ->route('bmn.aset.index')
            ->with('success', 'Aset '.$aset->kode_barang.' berhasil diperbarui.');
    }

    public function destroy(AsetBmn $aset): RedirectResponse
    {
        if ($aset->status === 'dihapus') {
            return redirect()
                ->route('bmn.aset.index')
                ->with('error', 'Aset sudah berstatus dihapus.');
        }

        DB::transaction(function () use ($aset): void {
            $aset->status = 'dihapus';
            $aset->deleted_by = auth()->id();
            $aset->deleted_at = now();
            $aset->save();
        });

        return redirect()
            ->route('bmn.aset.index')
            ->with('success', 'Aset '.$aset->kode_barang.' ditandai sebagai dihapus.');
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,csv', 'max:10240'],
        ]);

        $result = $this->asetBmnImportService->importFromUpload($request->file('file'));

        if ($result['ok'] !== true) {
            return back()
                ->with('error', 'Import ditolak: ada kesalahan pada berkas. Periksa daftar di bawah.')
                ->with('import_errors', $result['errors']);
        }

        return back()->with('success', 'Import berhasil: '.$result['count'].' aset tersimpan.');
    }

    public function downloadTemplate()
    {
        return Excel::download(new AsetBmnTemplateExport, 'template_aset_bmn.xlsx');
    }
}
