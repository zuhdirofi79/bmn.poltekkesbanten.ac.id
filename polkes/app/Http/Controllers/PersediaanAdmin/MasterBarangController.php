<?php

declare(strict_types=1);

namespace App\Http\Controllers\PersediaanAdmin;

use App\Exports\Persediaan\MasterBarangExport;
use App\Exports\Persediaan\MasterBarangTemplateExport;
use App\Http\Controllers\Controller;
use App\Imports\PersediaanMasterBarangImport;
use App\Models\Persediaan\PersediaanBarang;
use App\Models\Persediaan\PersediaanStockMovement;
use App\Models\Persediaan\PersediaanPurgeBatch;
use App\Services\Persediaan\PersediaanCodeService;
use App\Services\Persediaan\PersediaanDeletionService;
use App\Services\Persediaan\PersediaanPurgeService;
use App\Support\Persediaan\PersediaanAdminSession;
use App\Support\Persediaan\PublicUploadPath;
use App\Support\Persediaan\UnitKerja;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
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
        private PersediaanCodeService $codes,
        private PersediaanDeletionService $deletion,
        private PersediaanPurgeService $purge,
    ) {
    }

    /** @var list<int> */
    private const PER_PAGE_OPTIONS = [20, 100, 500, 1000];

    /** @var list<string> */
    private const COLUMN_FILTER_KEYS = [
        'kode_barang',
        'nama_barang',
        'unit_kerja',
        'satuan',
        'stok_awal',
        'stok_minimum',
        'harga_satuan',
        'status',
    ];

    public function index(Request $request): View
    {
        $columnFilters = $this->extractColumnFilters($request);
        $baseQuery = $this->listQuery($request);
        $lifecycle = $this->lifecycle($request);
        $perPage = $this->perPage($request);

        $barang = $this->applyListFilters(clone $baseQuery, $request, $columnFilters)
            ->with('rakAssignment.rak')
            ->orderBy('unit_kerja')
            ->orderBy('kode_barang')
            ->paginate($perPage)
            ->withQueryString();

        $satker = $this->effectiveSatker($request);

        return view('persediaan_admin.master-barang.index', [
            'barang'          => $barang,
            'units'           => $satker !== null ? [$satker] : UnitKerja::all(),
            'filters'         => array_merge($request->only(['unit', 'q']), $columnFilters),
            'filterOptions'   => $this->buildFilterOptions($baseQuery),
            'hasColumnFilter' => $columnFilters !== [],
            'barcodeUrls'     => $this->barcodeUrlsForPage($barang),
            'perPage'         => $perPage,
            'perPageOptions'  => self::PER_PAGE_OPTIONS,
            'lifecycle'       => $lifecycle,
        ]);
    }

    public function exportExcel(Request $request): BinaryFileResponse|RedirectResponse
    {
        $query = $this->applyListFilters(
            $this->listQuery($request),
            $request,
            $this->extractColumnFilters($request),
        );

        if (! (clone $query)->exists()) {
            return back()->with('error', 'Tidak ada data untuk diekspor.');
        }

        $filename = 'Master_Barang_Persediaan_' . now()->format('Ymd_His') . '.xlsx';

        return Excel::download(
            new MasterBarangExport(
                $query->with('rakAssignment.rak')->orderBy('unit_kerja')->orderBy('kode_barang'),
            ),
            $filename,
        );
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);

        DB::transaction(function () use ($data) {
            $barang = PersediaanBarang::create($data + ['is_active' => true]);

            if ((int) $data['stok_awal'] > 0) {
                PersediaanStockMovement::create([
                    'barang_id'  => $barang->id,
                    'unit_kerja' => $barang->unit_kerja,
                    'tipe'       => PersediaanStockMovement::TIPE_STOK_AWAL,
                    'ref_table'  => 'persediaan_barang',
                    'ref_id'     => $barang->id,
                    'qty_masuk'  => (int) $data['stok_awal'],
                    'keterangan' => 'Stok awal master barang',
                ]);
            }
        });

        return back()->with('success', 'Barang berhasil ditambahkan.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $barang = $this->scopedBarangQuery($request)->whereKey($id)->firstOrFail();
        $data = $this->validateData($request, $id);

        $barang->update($data + ['is_active' => $request->boolean('is_active')]);

        return back()->with('success', 'Barang berhasil diperbarui.');
    }

    public function deactivate(Request $request, int $id): RedirectResponse
    {
        $barang = $this->findBarang($request, $id, false);
        $barang->is_active = false;
        $barang->save();

        return back()->with('success', 'Barang dinonaktifkan.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $barang = $this->findBarang($request, $id, false);
        $barang->delete();

        return back()->with('success', 'Barang berhasil di-soft delete.');
    }

    public function forceDestroy(Request $request, int $id): RedirectResponse
    {
        $barang = $this->findBarang($request, $id, true);
        if ($barang->trashed()) {
            return $this->purgeTrashed($request, [(int) $barang->id], PersediaanPurgeBatch::MODE_SINGLE);
        }
        if ($this->deletion->barangIdsWithHistory([$barang->id]) !== []) {
            return back()->with('error', PersediaanDeletionService::BARANG_BLOCKED);
        }

        $path = $barang->barcode_path;
        try {
            DB::transaction(function () use ($barang): void {
                $barang->forceDelete();
            });
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Data gagal dihapus. Silakan coba kembali.');
        }

        $this->deletion->deleteExclusiveBarcodes([$path]);

        return back()->with('success', 'Barang berhasil dihapus permanen.');
    }

    public function restore(Request $request, int $id): RedirectResponse
    {
        $barang = $this->findBarang($request, $id, true);
        if (! $barang->trashed()) {
            return back()->with('error', 'Barang ini tidak berada di sampah.');
        }

        $barang->restore();

        return back()->with('success', 'Barang berhasil dipulihkan.');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $ids = $this->deletion->normalizeIds((array) $request->input('ids', []));
        if ($ids === []) {
            return back()->with('error', 'Pilih data terlebih dahulu.');
        }

        $foundIds = $this->scopedBarangQuery($request)->whereIn('id', $ids)->pluck('id')->map(static fn ($id): int => (int) $id)->all();
        if ($foundIds === []) {
            return back()->with('error', 'Data tidak ditemukan atau berada di luar akses Anda.');
        }

        try {
            DB::transaction(function () use ($foundIds): void {
                PersediaanBarang::query()->whereIn('id', $foundIds)->delete();
            });
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Data gagal dihapus. Silakan coba kembali.');
        }

        return back()->with('success', $this->countMessage(count($foundIds), count($ids) - count($foundIds), 'barang berhasil di-soft delete.'));
    }

    public function bulkForceDestroy(Request $request): RedirectResponse
    {
        $ids = $this->deletion->normalizeIds((array) $request->input('ids', []));
        if ($ids === []) {
            return back()->with('error', 'Pilih data terlebih dahulu.');
        }

        $found = $this->scopedBarangQuery($request)->withTrashed()->whereIn('id', $ids)->get();
        if ($found->isEmpty()) {
            return back()->with('error', 'Data tidak ditemukan atau berada di luar akses Anda.');
        }

        $blocked = array_flip($this->deletion->barangIdsWithHistory($found->pluck('id')->map(static fn ($id): int => (int) $id)->all()));
        $safe = $found->reject(static fn (PersediaanBarang $barang): bool => isset($blocked[$barang->id]));
        $paths = $safe->pluck('barcode_path')->all();

        try {
            DB::transaction(function () use ($safe): void {
                $safeIds = $safe->pluck('id')->all();
                if ($safeIds !== []) {
                    PersediaanBarang::withTrashed()->whereIn('id', $safeIds)->forceDelete();
                }
            });
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Data gagal dihapus. Silakan coba kembali.');
        }

        $this->deletion->deleteExclusiveBarcodes($paths);

        $done = $safe->count();
        $message = $this->partialMessage(
            $done,
            count($blocked),
            'barang berhasil dihapus permanen.',
            'barang tidak dapat dihapus karena memiliki histori transaksi.',
        );
        $missing = count($ids) - $found->count();
        if ($missing > 0) {
            $message .= ' ' . $missing . ' data tidak ditemukan.';
        }

        return back()->with($done > 0 ? 'success' : 'error', $message);
    }

    public function bulkRestore(Request $request): RedirectResponse
    {
        $ids = $this->deletion->normalizeIds((array) $request->input('ids', []));
        if ($ids === []) {
            return back()->with('error', 'Pilih data terlebih dahulu.');
        }

        $foundIds = $this->scopedBarangQuery($request)->onlyTrashed()->whereIn('id', $ids)->pluck('id')->all();
        if ($foundIds === []) {
            return back()->with('error', 'Data tidak ditemukan atau berada di luar akses Anda.');
        }

        PersediaanBarang::onlyTrashed()->whereIn('id', $foundIds)->restore();

        return back()->with('success', count($foundIds) . ' barang berhasil dipulihkan.');
    }

    public function templateExcel(): BinaryFileResponse
    {
        return Excel::download(
            new MasterBarangTemplateExport,
            'template-master-barang-persediaan.xlsx',
        );
    }

    public function importExcel(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls'],
        ]);

        $admin = PersediaanAdminSession::activeAdmin($request);
        $satker = PersediaanAdminSession::isSuperadmin($request)
            ? null
            : ($admin?->effectiveSatker());

        try {
            $result = (new PersediaanMasterBarangImport($satker))->import($request->file('file'));
        } catch (\Throwable $e) {
            report($e);

            return back()
                ->with('error', 'Import tidak dapat diproses. Periksa format berkas Excel.')
                ->with('master_barang_import_errors', []);
        }

        $created = (int) ($result['created'] ?? 0);
        $updated = (int) ($result['updated'] ?? 0);
        $skipped = (int) ($result['skipped'] ?? 0);
        $errors = $result['errors'] ?? [];

        if ($created === 0 && $updated === 0) {
            return back()
                ->with('error', 'Tidak ada baris yang berhasil diimpor. Periksa file dan daftar error di bawah.')
                ->with('master_barang_import_errors', $errors);
        }

        $msg = "Import berhasil. {$created} data dibuat, {$updated} data diperbarui, {$skipped} baris dilewati.";

        return back()
            ->with('success', $msg)
            ->with('master_barang_import_errors', $errors);
    }

    /**
     * Filter yang sama untuk halaman dan export. Tidak memakai paginate().
     *
     * @param  Builder<PersediaanBarang>  $query
     * @param  array<string, list<string>>  $columnFilters
     * @return Builder<PersediaanBarang>
     */
    private function applyListFilters(Builder $query, Request $request, array $columnFilters): Builder
    {
        $query
            ->when($request->filled('unit'), fn ($q) => $q->where('unit_kerja', $request->input('unit')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%' . $request->input('q') . '%';
                $q->where(fn ($w) => $w->where('kode_barang', 'like', $term)->orWhere('nama_barang', 'like', $term));
            });

        $this->applyColumnFilters($query, $columnFilters);

        return $query;
    }

    private function perPage(Request $request): int
    {
        $perPage = (int) $request->query('per_page', 20);

        if (! in_array($perPage, self::PER_PAGE_OPTIONS, true)) {
            return 20;
        }

        return $perPage;
    }

    /**
     * @return Builder<PersediaanBarang>
     */
    private function scopedBarangQuery(Request $request): Builder
    {
        $query = PersediaanBarang::query();
        $satker = $this->effectiveSatker($request);

        if ($satker !== null) {
            $query->where('unit_kerja', $satker);
        }

        return $query;
    }

    private function effectiveSatker(Request $request): ?string
    {
        if (PersediaanAdminSession::isSuperadmin($request)) {
            return null;
        }

        $admin = PersediaanAdminSession::activeAdmin($request);

        return $admin?->effectiveSatker();
    }

    /**
     * @return array<string, list<string>>
     */
    private function extractColumnFilters(Request $request): array
    {
        $filters = [];

        foreach (self::COLUMN_FILTER_KEYS as $key) {
            $values = array_values(array_filter(
                array_map('strval', (array) $request->input($key, [])),
                static fn (string $v): bool => $v !== '',
            ));

            if ($values !== []) {
                $filters[$key] = $values;
            }
        }

        return $filters;
    }

    /**
     * @param  Builder<PersediaanBarang>  $query
     * @param  array<string, list<string>>  $filters
     */
    private function applyColumnFilters(Builder $query, array $filters): void
    {
        foreach (['kode_barang', 'nama_barang', 'unit_kerja', 'satuan'] as $col) {
            if (! empty($filters[$col])) {
                $query->whereIn($col, $filters[$col]);
            }
        }

        if (! empty($filters['stok_awal'])) {
            $query->whereIn('stok_awal', array_map('intval', $filters['stok_awal']));
        }

        if (! empty($filters['stok_minimum'])) {
            $query->whereIn('stok_minimum', array_map('intval', $filters['stok_minimum']));
        }

        if (! empty($filters['harga_satuan'])) {
            $query->whereIn('harga_satuan', $filters['harga_satuan']);
        }

        if (! empty($filters['status'])) {
            $activeValues = [];
            foreach ($filters['status'] as $status) {
                if ($status === 'aktif') {
                    $activeValues[] = 1;
                } elseif ($status === 'nonaktif') {
                    $activeValues[] = 0;
                }
            }

            if ($activeValues !== []) {
                $query->whereIn('is_active', $activeValues);
            }
        }
    }

    /**
     * @param  Builder<PersediaanBarang>  $baseQuery
     * @return array<string, array<string, string>>
     */
    private function buildFilterOptions(Builder $baseQuery): array
    {
        $distinct = static function (Builder $q, string $col): array {
            return (clone $q)
                ->select($col)
                ->distinct()
                ->orderBy($col)
                ->pluck($col)
                ->filter(static fn ($v) => $v !== null && $v !== '')
                ->map(static fn ($v) => (string) $v)
                ->values()
                ->all();
        };

        $kode = $distinct($baseQuery, 'kode_barang');
        $nama = $distinct($baseQuery, 'nama_barang');
        $unit = $distinct($baseQuery, 'unit_kerja');
        $satuan = $distinct($baseQuery, 'satuan');
        $stokAwal = $distinct($baseQuery, 'stok_awal');
        $stokMin = $distinct($baseQuery, 'stok_minimum');
        $harga = $distinct($baseQuery, 'harga_satuan');

        $statusOptions = [];
        $hasAktif = (clone $baseQuery)->where('is_active', true)->exists();
        $hasNonaktif = (clone $baseQuery)->where('is_active', false)->exists();
        if ($hasAktif) {
            $statusOptions['aktif'] = 'Aktif';
        }
        if ($hasNonaktif) {
            $statusOptions['nonaktif'] = 'Nonaktif';
        }

        return [
            'kode_barang'  => array_combine($kode, $kode) ?: [],
            'nama_barang'  => array_combine($nama, $nama) ?: [],
            'unit_kerja'   => array_combine(
                $unit,
                array_map(static fn (string $u): string => UnitKerja::label($u), $unit),
            ) ?: [],
            'satuan'       => array_combine($satuan, $satuan) ?: [],
            'stok_awal'    => $this->numericOptions($stokAwal),
            'stok_minimum' => $this->numericOptions($stokMin),
            'harga_satuan' => $this->moneyOptions($harga),
            'status'       => $statusOptions,
        ];
    }

    /**
     * @param  list<string>  $values
     * @return array<string, string>
     */
    private function numericOptions(array $values): array
    {
        $options = [];
        foreach ($values as $value) {
            $options[$value] = number_format((int) $value, 0, ',', '.');
        }

        return $options;
    }

    /**
     * @param  list<string>  $values
     * @return array<string, string>
     */
    private function moneyOptions(array $values): array
    {
        $options = [];
        foreach ($values as $value) {
            $options[$value] = 'Rp ' . number_format((float) $value, 0, ',', '.');
        }

        return $options;
    }

    /**
     * @return array<string,mixed>
     */
    private function validateData(Request $request, ?int $id = null): array
    {
        $request->merge([
            'kode_barang' => trim((string) $request->input('kode_barang', '')),
            'nama_barang' => trim((string) $request->input('nama_barang', '')),
            'satuan'      => trim((string) $request->input('satuan', '')),
        ]);

        return $request->validate([
            'kode_barang'  => ['required', 'string', 'max:100'],
            'nama_barang'  => ['required', 'string', 'max:200'],
            'satuan'       => ['required', 'string', 'max:50'],
            'unit_kerja'   => ['required', Rule::in(UnitKerja::all())],
            'stok_awal'    => ['required', 'integer', 'min:0'],
            'stok_minimum' => ['required', 'integer', 'min:0'],
            'harga_satuan' => ['required', 'numeric', 'min:0'],
        ]);
    }

    public function generateBarcode(Request $request, int $id): RedirectResponse
    {
        $barang = $this->scopedBarangQuery($request)->whereKey($id)->firstOrFail();

        try {
            $this->codes->generateAndSave($barang);
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Gagal generate barcode: ' . $e->getMessage());
        }

        $barang->refresh();
        if ((string) $barang->barcode_payload !== $this->codes->identityPayload($barang)) {
            return back()->with('error', 'Identifier barcode berubah secara tidak terduga.');
        }

        return back()->with('success', 'Barcode Code 128 berhasil dibuat untuk ' . $barang->kode_barang . '.');
    }

    public function generateBarcodes(Request $request): RedirectResponse
    {
        @set_time_limit(180);
        ignore_user_abort(true);

        $scope = (string) $request->input('scope', 'all');
        $ids = array_values(array_filter(array_map('intval', (array) $request->input('ids', []))));

        $query = $this->scopedBarangQuery($request)->where('is_active', true);
        if ($scope === 'selected') {
            if ($ids === []) {
                return back()->with('error', 'Pilih barang terlebih dahulu untuk Generate Barcode Terpilih.');
            }
            $query->whereIn('id', $ids);
        }

        $generated = 0;
        $failed = 0;
        $total = (clone $query)->count();
        if ($total === 0) {
            return back()->with('error', 'Tidak ada barang aktif untuk digenerate.');
        }

        $query->orderBy('id')->chunkById(20, function ($barangs) use (&$generated, &$failed): void {
            foreach ($barangs as $barang) {
                try {
                    $this->codes->generateAndSave($barang);
                    $fresh = $barang->fresh();
                    if ($fresh === null || (string) $fresh->barcode_payload !== $this->codes->identityPayload($fresh)) {
                        $failed++;
                        continue;
                    }
                    $generated++;
                } catch (\Throwable $e) {
                    report($e);
                    $failed++;
                }
            }
        });

        $msg = "Barcode Code 128 berhasil dibuat/diperbarui untuk {$generated} barang.";
        if ($failed > 0) {
            $msg .= " {$failed} gagal.";
        }
        $msg .= " Diproses dari {$total} barang.";

        return back()->with($failed > 0 ? 'error' : 'success', $msg);
    }

    public function downloadBarcode(Request $request, int $id): BinaryFileResponse|RedirectResponse
    {
        $barang = $this->scopedBarangQuery($request)->whereKey($id)->firstOrFail();
        $relative = $barang->barcode_path ?: $this->codes->relativePath($barang);
        $path = PublicUploadPath::firstExistingFile($relative);
        if ($path === null) {
            return back()->with('error', 'File barcode belum ada. Generate barcode terlebih dahulu.');
        }

        return response()->download($path, 'barcode-' . $barang->kode_barang . '.png');
    }

    public function printBarcodes(Request $request): View|RedirectResponse
    {
        $ids = array_values(array_filter(array_map('intval', (array) $request->input('ids', []))));
        $query = $this->scopedBarangQuery($request)->where('is_active', true);
        if ($ids !== []) {
            $query->whereIn('id', $ids);
        }

        $barangs = $query->orderBy('unit_kerja')->orderBy('kode_barang')->get();
        $items = [];
        foreach ($barangs as $barang) {
            $url = $this->codes->fileUrl($barang);
            if ($url === null) {
                continue;
            }
            $items[] = [
                'barang'   => $barang,
                'imageUrl' => $url,
            ];
        }

        if ($items === []) {
            return back()->with('error', 'Tidak ada barcode yang siap dicetak. Generate barcode terlebih dahulu.');
        }

        return view('persediaan_admin.master-barang.print-barcodes', [
            'items' => $items,
        ]);
    }

    public function lookupBarcode(Request $request): JsonResponse
    {
        $code = trim((string) $request->input('code', ''));
        $unit = $request->input('unit');
        $unitContext = is_string($unit) && $unit !== '' ? $unit : $this->effectiveSatker($request);

        $barang = $this->codes->resolve($code, $unitContext);
        if ($barang === null) {
            return response()->json(['ok' => false, 'message' => 'Barcode tidak dikenali atau ambigu antar unit.'], 404);
        }

        $scoped = $this->scopedBarangQuery($request)->whereKey($barang->id)->first();
        if ($scoped === null) {
            return response()->json(['ok' => false, 'message' => 'Barang di luar unit Anda.'], 403);
        }

        $barang->loadMissing('rakAssignment.rak');
        $stok = app(\App\Services\Persediaan\StockService::class)->stokAkhir($barang);

        return response()->json([
            'ok'     => true,
            'barang' => [
                'id'           => $barang->id,
                'kode_barang'  => $barang->kode_barang,
                'nama_barang'  => $barang->nama_barang,
                'unit_kerja'   => $barang->unit_kerja,
                'satuan'       => $barang->satuan,
                'stok'         => $stok,
                'status'       => $barang->is_active ? 'Aktif' : 'Nonaktif',
                'rak'          => optional(optional($barang->rakAssignment)->rak)->kode_rak,
                'rak_nama'     => optional(optional($barang->rakAssignment)->rak)->nama_rak,
                'payload'      => $this->codes->payloadFor($barang),
            ],
        ]);
    }

    /**
     * @param  \Illuminate\Contracts\Pagination\LengthAwarePaginator<PersediaanBarang>  $page
     * @return array<int,string|null>
     */
    private function barcodeUrlsForPage($page): array
    {
        $urls = [];
        foreach ($page as $barang) {
            $urls[(int) $barang->id] = $this->codes->fileUrl($barang);
        }

        return $urls;
    }

    /**
     * @return Builder<PersediaanBarang>
     */
    private function listQuery(Request $request): Builder
    {
        $query = $this->scopedBarangQuery($request);
        $lifecycle = $this->lifecycle($request);

        if ($lifecycle === 'sampah') {
            $query->onlyTrashed();
        } elseif ($lifecycle === 'semua') {
            $query->withTrashed();
        }

        return $query;
    }

    private function lifecycle(Request $request): string
    {
        $lifecycle = (string) $request->input('lifecycle', 'aktif');

        return in_array($lifecycle, ['aktif', 'sampah', 'semua'], true) ? $lifecycle : 'aktif';
    }

    private function findBarang(Request $request, int $id, bool $withTrashed): PersediaanBarang
    {
        $query = $this->scopedBarangQuery($request);
        if ($withTrashed) {
            $query->withTrashed();
        }

        $barang = $query->whereKey($id)->first();
        abort_if($barang === null, 404);

        return $barang;
    }

    public function trash(Request $request): View
    {
        $perPage = $this->perPage($request);
        $barang = $this->applyListFilters($this->scopedBarangQuery($request)->onlyTrashed(), $request, [])
            ->orderByDesc('deleted_at')
            ->orderBy('kode_barang')
            ->paginate($perPage)
            ->withQueryString();

        $satker = $this->effectiveSatker($request);
        $pageIds = $barang->getCollection()->pluck('id')->map(static fn ($id): int => (int) $id)->all();

        return view('persediaan_admin.master-barang.trash', [
            'barang'         => $barang,
            'units'          => $satker !== null ? [$satker] : UnitKerja::all(),
            'perPage'        => $perPage,
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'historyIds'     => array_flip($this->deletion->barangIdsWithHistory($pageIds)),
        ]);
    }

    public function trashBulkForceDestroy(Request $request): RedirectResponse
    {
        $ids = $this->deletion->normalizeIds((array) $request->input('ids', []));
        if ($ids === []) {
            return back()->with('error', 'Pilih data terlebih dahulu.');
        }

        return $this->purgeTrashed($request, $ids, PersediaanPurgeBatch::MODE_SELECTED);
    }

    public function purgePreview(Request $request): JsonResponse
    {
        if ($denied = $this->denyPurge($request)) {
            return $denied;
        }

        $mode = (string) $request->input('mode', PersediaanPurgeBatch::MODE_ALL);
        $ids = $this->deletion->normalizeIds((array) $request->input('ids', []));
        $preview = $this->purge->preview(
            $this->scopedBarangQuery($request)->onlyTrashed(),
            $mode === PersediaanPurgeBatch::MODE_ALL ? [] : $ids,
        );

        return response()->json($preview);
    }

    public function purgeStart(Request $request): JsonResponse
    {
        if ($denied = $this->denyPurge($request)) {
            return $denied;
        }

        $mode = (string) $request->input('mode', '');
        if (! in_array($mode, [PersediaanPurgeBatch::MODE_SINGLE, PersediaanPurgeBatch::MODE_SELECTED, PersediaanPurgeBatch::MODE_ALL], true)) {
            return response()->json(['message' => 'Jenis penghapusan tidak dikenal.'], 422);
        }
        if ($mode === PersediaanPurgeBatch::MODE_ALL && $request->input('confirm') !== PersediaanPurgeService::CONFIRM_ALL) {
            return response()->json(['message' => 'Ketik HAPUS PERMANEN untuk melanjutkan.'], 422);
        }

        $ids = $this->deletion->normalizeIds((array) $request->input('ids', []));
        if ($mode !== PersediaanPurgeBatch::MODE_ALL && $ids === []) {
            return response()->json(['message' => 'Pilih data terlebih dahulu.'], 422);
        }

        $result = $this->purge->start($request, $this->scopedBarangQuery($request)->onlyTrashed(), $mode, $ids);

        return response()->json($result, (int) ($result['http_status'] ?? 200));
    }

    public function purgeStep(Request $request, string $batch): JsonResponse
    {
        if ($denied = $this->denyPurge($request)) {
            return $denied;
        }

        return response()->json($this->purge->step($this->findPurgeBatch($request, $batch)));
    }

    public function purgeFailures(Request $request, string $batch)
    {
        if (! PersediaanAdminSession::isLoggedIn($request)) {
            abort(403);
        }

        $row = $this->findPurgeBatch($request, $batch);
        $csv = $this->purge->failureCsv($row);

        return response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="kegagalan-sampah-' . $row->public_id . '.csv"',
        ]);
    }

    /**
     * @param  list<int>  $ids
     */
    private function purgeTrashed(Request $request, array $ids, string $mode): RedirectResponse
    {
        try {
            $result = $this->purge->purgeNow($request, $this->scopedBarangQuery($request)->onlyTrashed(), $ids, $mode);
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Data gagal dihapus. Silakan coba kembali.');
        }

        if (($result['ok'] ?? false) !== true) {
            return back()->with('error', (string) ($result['message'] ?? 'Data gagal dihapus.'));
        }

        $done = (int) ($result['succeeded'] ?? 0);
        $failed = (int) ($result['failed'] ?? 0);
        if ($mode === PersediaanPurgeBatch::MODE_SINGLE && $done === 1 && $failed === 0) {
            return back()->with('success', 'Barang berhasil dihapus permanen.');
        }
        if ($done === 0) {
            $reason = $result['failures'][0]['message'] ?? 'Tidak ada barang yang dihapus.';

            return back()->with('error', $reason);
        }

        $message = $done . ' barang berhasil dihapus permanen.';
        if ($failed > 0) {
            $message .= ' ' . $failed . ' barang gagal dan tetap ada di Sampah.';
        }

        return back()->with('success', $message);
    }

    private function denyPurge(Request $request): ?JsonResponse
    {
        if (! PersediaanAdminSession::isLoggedIn($request)) {
            return response()->json(['message' => 'Akses ditolak.'], 403);
        }

        return null;
    }

    private function findPurgeBatch(Request $request, string $publicId): PersediaanPurgeBatch
    {
        $batch = PersediaanPurgeBatch::query()->where('public_id', $publicId)->first();
        abort_if($batch === null, 404);
        if (! PersediaanAdminSession::isSuperadmin($request) && (int) $batch->admin_id !== (int) PersediaanAdminSession::userId($request)) {
            abort(403);
        }

        return $batch;
    }

    private function countMessage(int $done, int $missing, string $doneLabel): string
    {
        $message = $done . ' ' . $doneLabel;
        if ($missing > 0) {
            $message .= ' ' . $missing . ' data tidak ditemukan.';
        }

        return $message;
    }

    private function partialMessage(int $done, int $blocked, string $doneLabel, string $blockedLabel): string
    {
        $parts = [];
        if ($done > 0) {
            $parts[] = $done . ' ' . $doneLabel;
        }
        if ($blocked > 0) {
            $parts[] = $blocked . ' ' . $blockedLabel;
        }

        return $parts === [] ? 'Tidak ada data yang diproses.' : implode(' ', $parts);
    }
}
