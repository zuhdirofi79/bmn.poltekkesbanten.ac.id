<?php

declare(strict_types=1);

namespace App\Http\Controllers\BMN;

use App\Exports\AsetBmnExport;
use App\Exports\AsetBmnSelectedExport;
use App\Exports\AsetBmnTemplateExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\BMN\StoreAsetRequest;
use App\Http\Requests\BMN\UpdateAsetRequest;
use App\Models\BmnSatker;
use App\Models\BMN\AsetBmn;
use App\Models\BMN\KategoriAset;
use App\Services\BMN\AsetBmnFotoService;
use App\Services\BMN\AsetBmnImportService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AsetController extends Controller
{
    public function __construct(
        private readonly AsetBmnImportService $asetBmnImportService,
        private readonly AsetBmnFotoService $asetBmnFotoService
    ) {
    }

    public function index(Request $request): View
    {
        $this->validateAsetFilters($request);

        $filters = $request->only($this->asetFilterKeys());

        $satkers = BmnSatker::orderBy('sort_order')->orderBy('id')->get();
        $kategoriList = KategoriAset::orderBy('id')->get();

        $query = $this->asetQuery($request);

        $perPage = $this->resolvePerPage($request);

        $aset = $query->paginate($perPage)->appends($request->query());

        return view('bmn.aset.index', compact('aset', 'filters', 'satkers', 'kategoriList', 'perPage'));
    }

    /**
     * Daftar kunci filter yang dipakai bersama oleh index() dan exportExcel().
     *
     * @return list<string>
     */
    private function asetFilterKeys(): array
    {
        return [
            'q',
            'kode_barang',
            'nup',
            'nama_barang',
            'merk',
            'kategori_id',
            'kondisi',
            'status',
            'lokasi',
            'satker_id',
            'satker',
            'pengguna_ruangan',
            'tahun_perolehan',
            'tanggal_from',
            'tanggal_to',
            'nilai_min',
            'nilai_max',
            'created_from',
            'created_to',
        ];
    }

    /**
     * Validasi tipe filter yang dipakai index() & exportExcel(). Dipisah agar
     * kedua endpoint memakai aturan yang identik (tidak ada divergensi).
     */
    private function validateAsetFilters(Request $request): void
    {
        $request->validate([
            'satker_id' => ['nullable', 'integer', 'exists:bmn_satker,id'],
            'kategori_id' => ['nullable', 'integer', 'exists:kategori_aset,id'],
            'tahun_perolehan' => ['nullable', 'integer', 'digits:4'],
            'tanggal_from' => ['nullable', 'date'],
            'tanggal_to' => ['nullable', 'date', 'after_or_equal:tanggal_from'],
            'nilai_min' => ['nullable', 'numeric', 'min:0'],
            'nilai_max' => ['nullable', 'numeric', 'min:0', 'gte:nilai_min'],
            'created_from' => ['nullable', 'date'],
            'created_to' => ['nullable', 'date', 'after_or_equal:created_from'],
        ]);
    }

    /**
     * Single source of truth query daftar aset. Dipakai oleh:
     *  - index()        -> dipaginate untuk tampilan tabel
     *  - exportExcel()  -> dipakai utuh tanpa pagination (chunk via FromQuery)
     *
     * Logika filter sepenuhnya didelegasikan ke scopeFilter() di model AsetBmn
     * agar tidak ada duplikasi aturan filter.
     *
     * @return Builder<AsetBmn>
     */
    private function asetQuery(Request $request): Builder
    {
        $filters = $request->only($this->asetFilterKeys());

        return AsetBmn::with(['kategori', 'lokasiMaster'])
            ->filter($filters)
            ->orderByDesc('id');
    }

    /**
     * Whitelist ukuran halaman agar tidak bisa dipaksa load 200rb row sekaligus.
     *
     * @return int<10, 1000>
     */
    private function resolvePerPage(Request $request): int
    {
        $allowed = [10, 25, 50, 100, 250, 500, 1000];
        $perPage = (int) $request->query('per_page', 10);

        if (! in_array($perPage, $allowed, true)) {
            return 10;
        }

        return $perPage;
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
        unset($validated['foto']);
        $satker = BmnSatker::findOrFail((int) $validated['satker_id']);
        $data = [
            'kode_barang' => $validated['kode_barang'],
            'nup' => $validated['nup'] ?? null,
            'nama_barang' => $validated['nama_barang'],
            'merk' => isset($validated['merk']) && $validated['merk'] !== '' ? $validated['merk'] : null,
            'kategori_id' => (int) $validated['kategori_id'],
            'nilai_perolehan' => $validated['nilai_perolehan'],
            'tanggal_perolehan' => $validated['tanggal_perolehan'] ?? null,
            'kondisi' => $validated['kondisi'],
            'pengguna_ruangan' => $validated['pengguna_ruangan'] ?? null,
            'satker_id' => $satker->id,
            'satker' => $satker->nama,
            'lokasi' => $request->lokasi,
            'status' => 'aktif',
            'asset_tag_type' => 'MANUAL',
            'foto_provider' => '',
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ];

        $foto = null;
        if ($request->hasFile('foto')) {
            $foto = $this->asetBmnFotoService->processUploads(
                $request->file('foto'),
                (string) $validated['nama_barang'],
                (string) $validated['kode_barang'],
                $validated['nup'] ?? null,
                AsetBmnFotoService::MAX_FOTO_PER_ASET
            );
            if ($foto === []) {
                return back()
                    ->with('error', 'Tidak ada foto valid yang diunggah (periksa format, maks. 2MB per file, maks. 2 foto).')
                    ->withInput();
            }
        }
        $data['foto'] = $foto;

        try {
            $aset = DB::transaction(function () use ($data): AsetBmn {
                return AsetBmn::create($data);
            });
        } catch (\Throwable $e) {
            if (is_array($foto) && $foto !== []) {
                $this->asetBmnFotoService->deleteLocalFiles($foto);
            }

            return back()->with('error', $e->getMessage())->withInput();
        }

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
        unset($data['foto']);
        $satker = BmnSatker::findOrFail((int) $data['satker_id']);
        $data['satker_id'] = $satker->id;
        $data['satker'] = $satker->nama;
        $data['lokasi'] = $request->lokasi;
        $data['updated_by'] = auth()->id();

        $data['asset_tag_type'] = $aset->asset_tag_type !== null && $aset->asset_tag_type !== ''
            ? $aset->asset_tag_type
            : 'MANUAL';
        $data['foto_provider'] = $aset->foto_provider ?? '';

        /** @var list<array{full?: string, thumb?: string}> $existingFoto */
        $existingFoto = is_array($aset->foto) ? $aset->foto : [];
        $replacementFoto = null;
        if ($request->hasFile('foto')) {
            $replacementFoto = $this->asetBmnFotoService->processUploads(
                $request->file('foto'),
                (string) $data['nama_barang'],
                (string) $data['kode_barang'],
                $data['nup'] ?? null,
                AsetBmnFotoService::MAX_FOTO_PER_ASET
            );
            if ($replacementFoto === []) {
                return back()
                    ->with('error', 'Tidak ada foto valid yang diunggah (periksa format, ukuran maks. 2MB per file, maks. 2 foto).')
                    ->withInput();
            }
            $data['foto'] = $replacementFoto;
        } else {
            $data['foto'] = $existingFoto;
        }

        try {
            DB::beginTransaction();
            $aset->update($data);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            if ($replacementFoto !== null && $replacementFoto !== []) {
                $this->asetBmnFotoService->deleteLocalFiles($replacementFoto);
            }

            return back()->with('error', $e->getMessage())->withInput();
        }

        if ($request->hasFile('foto') && $existingFoto !== []) {
            $this->asetBmnFotoService->deleteLocalFiles($existingFoto);
        }

        $msg = $request->hasFile('foto')
            ? 'Data dan foto aset berhasil diperbarui; foto lama (lokal) diganti penuh.'
            : 'Aset '.$aset->kode_barang.' berhasil diperbarui.';

        return redirect()
            ->route('bmn.aset.index')
            ->with('success', $msg);
    }

    public function destroy(AsetBmn $aset): RedirectResponse
    {
        if (is_array($aset->foto) && $aset->foto !== []) {
            $this->asetBmnFotoService->deleteLocalFiles($aset->foto);
        }

        DB::transaction(function () use ($aset): void {
            $aset->forceFill(['deleted_by' => auth()->id()])->saveQuietly();
            $aset->delete();
        });

        return redirect()
            ->route('bmn.aset.index')
            ->with('success', 'Data berhasil dihapus');
    }

    /**
     * Soft-delete massal untuk aset terpilih dari halaman index.
     *
     * Sesuai konvensi BMN: status -> 'dihapus', deleted_at + deleted_by diisi,
     * tanpa menyentuh berkas foto lokal (foto tetap aman untuk audit/restore).
     */
    public function bulkDelete(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:5000'],
            'ids.*' => ['integer', 'distinct'],
        ]);

        /** @var list<int> $ids */
        $ids = array_values(array_unique(array_map('intval', $validated['ids'])));

        // Safety hard-limit: hindari payload terlalu besar (max_input_vars,
        // memory_limit, timeout) untuk dataset 200rb+ aset.
        if (count($ids) > 5000) {
            return back()->with('error', 'Maksimal 5.000 aset sekali hapus.');
        }

        $userId = auth()->id();
        $now = now();

        $affected = DB::transaction(function () use ($ids, $userId, $now): int {
            return AsetBmn::query()
                ->whereIn('id', $ids)
                ->update([
                    'status' => 'dihapus',
                    'deleted_at' => $now,
                    'deleted_by' => $userId,
                    'updated_by' => $userId,
                ]);
        });

        if ($affected === 0) {
            return back()->with('error', 'Tidak ada aset yang berhasil dihapus.');
        }

        return back()->with('success', $affected.' aset berhasil dihapus.');
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,csv', 'max:10240'],
        ]);

        try {
            $result = $this->asetBmnImportService->importFromUpload($request->file('file'));
        } catch (\Throwable $e) {
            report($e);

            return back()
                ->with('error', 'Import tidak dapat diproses. Periksa format berkas atau hubungi administrator.')
                ->with('import_errors', [])
                ->withoutInput();
        }

        $summary = [
            'imported' => (int) ($result['count'] ?? 0),
            'skipped_count' => (int) ($result['skipped_count'] ?? 0),
            'skipped_rows' => $result['skipped_rows'] ?? [],
            'duplicate_nup_errors' => $result['duplicate_nup_errors'] ?? [],
        ];

        if ($summary['imported'] === 0) {
            return back()
                ->with('error', 'Tidak ada baris yang diimpor. Periksa daftar di bawah.')
                ->with('import_errors', $result['errors'] ?? [])
                ->with('import_summary', $summary);
        }

        $msg = 'Import selesai: '.$summary['imported'].' aset tersimpan.';
        if ($summary['skipped_count'] > 0) {
            $msg .= ' '.$summary['skipped_count'].' baris dilewati.';
        }
        if (($result['errors'] ?? []) !== []) {
            $msg .= ' Lihat peringatan untuk detail baris yang dilewati.';
        }

        return back()
            ->with('success', $msg)
            ->with('import_errors', $result['errors'] ?? [])
            ->with('import_summary', $summary);
    }

    public function downloadTemplate()
    {
        return Excel::download(new AsetBmnTemplateExport, 'template_aset_bmn.xlsx');
    }

    /**
     * Export daftar aset (sesuai filter aktif) ke file .xlsx.
     *
     * - Memakai asetQuery() agar filter persis sama dengan halaman index.
     * - Tidak dipagination: query diserahkan ke AsetBmnExport (FromQuery) yang
     *   akan men-chunk baris sehingga aman untuk ±35.000 data.
     * - Otorisasi: route ini berada di group middleware ['auth'] yang sama
     *   dengan index, sehingga hanya user yang boleh membuka halaman Aset BMN
     *   yang dapat memanggil endpoint ini.
     */
    public function exportExcel(Request $request): BinaryFileResponse
    {
        $this->validateAsetFilters($request);

        $fileName = 'aset-bmn-'.now()->format('Ymd-His').'.xlsx';

        return Excel::download(
            new AsetBmnExport($this->asetQuery($request)),
            $fileName
        );
    }

    /**
     * Export hanya aset BMN yang dipilih (di-centang) dari halaman index.
     *
     * Body POST membawa `selected_ids` berupa string CSV id (mis: "1,3,7"),
     * dikirim form di view yang di-populate oleh JS dari checkbox baris.
     *
     * Alur keamanan (TIDAK percaya input frontend):
     *  1. validate selected_ids = string non-kosong
     *  2. parse → array<int>, trim, filter 0/non-int, unique
     *  3. hard-limit jumlah id (selaras dgn bulkDelete: 5.000)
     *  4. confirm id benar-benar ada di tabel aset_bmn; jika 0 → tolak
     *  5. delegasikan ke AsetBmnSelectedExport (FromQuery → chunked)
     *
     * Otorisasi: route ini berada di group middleware ['auth'] yang sama
     * dengan index/export filter, sehingga hanya user yang boleh membuka
     * halaman Aset BMN yang dapat memanggil endpoint ini.
     */
    public function exportSelectedExcel(Request $request)
    {
        $request->validate([
            'selected_ids' => ['required', 'string'],
        ]);

        /** @var list<int> $ids */
        $ids = collect(explode(',', (string) $request->input('selected_ids')))
            ->map(fn ($v) => (int) trim((string) $v))
            ->filter(fn (int $v) => $v > 0)
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            return back()->withErrors([
                'selected_ids' => 'Pilih minimal 1 aset untuk diexport.',
            ]);
        }

        // Hard-limit jumlah ID per export agar konsisten dgn bulk-delete &
        // tidak memicu max_input_vars / memory_limit / timeout di server.
        if (count($ids) > 5000) {
            return back()->withErrors([
                'selected_ids' => 'Maksimal 5.000 aset sekali export.',
            ]);
        }

        // Filter ulang ke ID yang BENAR-BENAR ada di DB. Hindari trust
        // pada hidden input di frontend (user bisa rekayasa value).
        $validIds = AsetBmn::query()
            ->whereIn('id', $ids)
            ->pluck('id')
            ->map(fn ($v) => (int) $v)
            ->values()
            ->all();

        if ($validIds === []) {
            return back()->withErrors([
                'selected_ids' => 'Aset yang dipilih tidak ditemukan.',
            ]);
        }

        $fileName = 'aset-bmn-terpilih-'.now()->format('Ymd-His').'.xlsx';

        return Excel::download(
            new AsetBmnSelectedExport($validIds),
            $fileName
        );
    }
}
