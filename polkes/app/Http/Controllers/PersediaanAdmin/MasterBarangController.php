<?php

declare(strict_types=1);

namespace App\Http\Controllers\PersediaanAdmin;

use App\Exports\Persediaan\MasterBarangTemplateExport;
use App\Http\Controllers\Controller;
use App\Imports\PersediaanMasterBarangImport;
use App\Models\Persediaan\PersediaanBarang;
use App\Models\Persediaan\PersediaanStockMovement;
use App\Support\Persediaan\PersediaanAdminSession;
use App\Support\Persediaan\UnitKerja;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MasterBarangController extends Controller
{
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
        $baseQuery = $this->scopedBarangQuery($request);

        $query = (clone $baseQuery)
            ->when($request->filled('unit'), fn ($q) => $q->where('unit_kerja', $request->input('unit')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%' . $request->input('q') . '%';
                $q->where(fn ($w) => $w->where('kode_barang', 'like', $term)->orWhere('nama_barang', 'like', $term));
            });

        $this->applyColumnFilters($query, $columnFilters);

        $barang = $query
            ->orderBy('unit_kerja')
            ->orderBy('kode_barang')
            ->paginate(20)
            ->withQueryString();

        $satker = $this->effectiveSatker($request);

        return view('persediaan_admin.master-barang.index', [
            'barang'        => $barang,
            'units'         => $satker !== null ? [$satker] : UnitKerja::all(),
            'filters'       => array_merge($request->only(['unit', 'q']), $columnFilters),
            'filterOptions' => $this->buildFilterOptions($baseQuery),
            'hasColumnFilter' => $columnFilters !== [],
        ]);
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
        $barang = PersediaanBarang::findOrFail($id);
        $data = $this->validateData($request, $id);

        $barang->update($data + ['is_active' => $request->boolean('is_active')]);

        return back()->with('success', 'Barang berhasil diperbarui.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $barang = PersediaanBarang::findOrFail($id);
        // Non-destruktif: nonaktifkan agar histori stok tetap utuh.
        $barang->is_active = false;
        $barang->save();

        return back()->with('success', 'Barang dinonaktifkan.');
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
            return $q->clone()
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
}
