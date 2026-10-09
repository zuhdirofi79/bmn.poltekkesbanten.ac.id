<?php

declare(strict_types=1);

namespace App\Http\Controllers\PersediaanAdmin;

use App\Exports\Persediaan\PembelianExport;
use App\Exports\Persediaan\PembelianTemplateExport;
use App\Http\Controllers\Controller;
use App\Models\Persediaan\PersediaanBarang;
use App\Models\Persediaan\PersediaanPembelian;
use App\Services\Persediaan\DuplicateFakturException;
use App\Services\Persediaan\PembelianExcelReader;
use App\Services\Persediaan\PembelianImportException;
use App\Services\Persediaan\PembelianImportNormalizer;
use App\Services\Persediaan\PurchaseException;
use App\Services\Persediaan\PurchaseService;
use App\Support\Persediaan\PersediaanAccess;
use App\Support\Persediaan\PersediaanAdminSession;
use App\Support\Persediaan\UnitKerja;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PembelianController extends Controller
{
    private const IMPORT_SESSION = 'persediaan.pembelian.import';

    public function __construct(
        private PurchaseService $purchases,
        private PembelianExcelReader $reader,
        private PembelianImportNormalizer $normalizer,
    ) {
    }

    public function index(Request $request): View
    {
        $locked = PersediaanAccess::lockedUnitForApprover($request);
        $filterUnit = $this->requestedUnit($request, $locked);

        $pembelian = $this->filteredQuery($request)
            ->paginate(15)
            ->withQueryString();

        return view('persediaan_admin.pembelian.index', [
            'pembelian'  => $pembelian,
            'units'      => $locked ? [$locked] : UnitKerja::all(),
            'lockedUnit' => $locked,
            'filterUnit' => $filterUnit,
        ]);
    }

    public function create(Request $request): View
    {
        $locked = PersediaanAccess::lockedUnitForApprover($request);
        $units = $locked ? [$locked] : UnitKerja::all();

        $barangs = PersediaanBarang::query()
            ->where('is_active', true)
            ->when($locked, fn ($q) => $q->where('unit_kerja', $locked))
            ->orderBy('unit_kerja')
            ->orderBy('kode_barang')
            ->get();

        return view('persediaan_admin.pembelian.create', [
            'units'      => $units,
            'barangJson' => $barangs->groupBy('unit_kerja')->map(function ($g) {
                return $g->map(fn ($b) => [
                    'id'     => $b->id,
                    'kode'   => $b->kode_barang,
                    'nama'   => $b->nama_barang,
                    'satuan' => $b->satuan,
                    'harga'  => (float) $b->harga_satuan,
                ])->values();
            }),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nomor_faktur'         => ['required', 'string', 'max:100'],
            'tanggal'              => ['required', 'date'],
            'penyedia'             => ['nullable', 'string', 'max:150'],
            'unit_kerja'           => ['required', 'in:' . implode(',', UnitKerja::all())],
            'items'                => ['required', 'array', 'min:1'],
            'items.*.mode'         => ['nullable', 'in:master,manual'],
            'items.*.barang_id'    => ['nullable', 'integer'],
            'items.*.kode_barang'  => ['nullable', 'string', 'max:100'],
            'items.*.nama_barang'  => ['nullable', 'string', 'max:200'],
            'items.*.satuan'       => ['nullable', 'string', 'max:50'],
            'items.*.harga_satuan' => ['nullable', 'numeric', 'min:0'],
            'items.*.jumlah'       => ['nullable'],
            'faktur'               => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
            'bast'                 => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
            'invoice'              => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
            'foto_barang'          => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:5120'],
        ]);

        $locked = PersediaanAccess::lockedUnitForApprover($request);
        if ($locked !== null && $data['unit_kerja'] !== $locked) {
            return back()->withInput()->withErrors(['unit_kerja' => 'Unit kerja tidak sesuai hak akses.']);
        }

        $normalized = $this->purchases->normalizeFormItems($data['items'], $data['unit_kerja']);
        if ($normalized['errors'] !== []) {
            return back()->withInput()->withErrors(['items' => implode(' ', $normalized['errors'])]);
        }

        $header = [
            'nomor_faktur'     => $data['nomor_faktur'],
            'penyedia'         => $data['penyedia'] ?? null,
            'tanggal'          => $data['tanggal'],
            'unit_kerja'       => $data['unit_kerja'],
            'faktur_path'      => $this->storeFile($request, 'faktur', ['pdf']),
            'bast_path'        => $this->storeFile($request, 'bast', ['pdf']),
            'invoice_path'     => $this->storeFile($request, 'invoice', ['pdf']),
            'foto_barang_path' => $this->storeFile($request, 'foto_barang', ['jpg', 'jpeg', 'png']),
        ];

        try {
            $this->purchases->store($header, $normalized['rows'], PersediaanAdminSession::userId($request));
        } catch (DuplicateFakturException | PurchaseException $e) {
            return back()->withInput()->withErrors(['nomor_faktur' => $e->getMessage()]);
        } catch (\Throwable $e) {
            report($e);

            return back()->withInput()->with('error', 'Pembelian gagal disimpan. Periksa data lalu coba lagi.');
        }

        $masterCount = count(array_filter($normalized['rows'], fn ($row) => ! empty($row['barang_id'])));
        $message = $masterCount > 0
            ? 'Pembelian disimpan. Stok master bertambah untuk barang yang dipilih dari master.'
            : 'Pembelian disimpan. Item manual tidak mengubah stok master.';

        return redirect()->route('persediaan.admin.pembelian.index')->with('success', $message);
    }

    public function show(Request $request, int $id): View
    {
        $pembelian = PersediaanPembelian::with('items')->findOrFail($id);
        $locked = PersediaanAccess::lockedUnitForApprover($request);
        if ($locked !== null && $pembelian->unit_kerja !== $locked) {
            abort(403);
        }

        return view('persediaan_admin.pembelian.show', [
            'pembelian' => $pembelian,
        ]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        $pembelian = $this->filteredQuery($request)
            ->with(['items' => fn ($q) => $q->orderBy('id')])
            ->get();

        $filename = 'Pembelian_Persediaan_' . now()->format('Ymd_His') . '.xlsx';

        return Excel::download(PembelianExport::fromModels($pembelian), $filename);
    }

    public function importTemplate(): BinaryFileResponse
    {
        return Excel::download(new PembelianTemplateExport(), 'template-pembelian-persediaan.xlsx');
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:5120'],
        ]);

        $file = $request->file('file');
        $ext = strtolower((string) $file?->getClientOriginalExtension());
        if (! in_array($ext, ['xlsx', 'xls', 'csv'], true)) {
            return back()->with('error', 'File harus berupa Excel (.xlsx, .xls) atau CSV.');
        }

        try {
            $rawRows = $this->reader->read($file);
            $preview = $this->normalizer->build(
                $rawRows,
                $this->masterMap(),
                PersediaanPembelian::query()->pluck('nomor_faktur')->all(),
                PersediaanAccess::lockedUnitForApprover($request),
            );
        } catch (PembelianImportException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'File Excel tidak dapat dibaca. Periksa template lalu coba lagi.');
        }

        $request->session()->put(self::IMPORT_SESSION, [
            'token'   => Str::random(40),
            'preview' => $preview,
        ]);

        return redirect()->route('persediaan.admin.pembelian.import.preview');
    }

    public function importPreview(Request $request): View|RedirectResponse
    {
        $payload = $request->session()->get(self::IMPORT_SESSION);
        if (! is_array($payload) || empty($payload['preview'])) {
            return redirect()
                ->route('persediaan.admin.pembelian.index')
                ->with('error', 'Tidak ada preview import. Unggah file Excel terlebih dahulu.');
        }

        return view('persediaan_admin.pembelian.import-preview', [
            'token'   => (string) ($payload['token'] ?? ''),
            'preview' => $payload['preview'],
        ]);
    }

    public function importConfirm(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
        ]);

        $payload = $request->session()->get(self::IMPORT_SESSION);
        $token = is_array($payload) ? (string) ($payload['token'] ?? '') : '';
        if ($token === '' || ! hash_equals($token, (string) $request->input('token'))) {
            return redirect()
                ->route('persediaan.admin.pembelian.index')
                ->with('error', 'Sesi import tidak valid. Unggah ulang file Excel.');
        }

        $preview = $payload['preview'] ?? [];
        if (empty($preview['can_import']) || empty($preview['groups'])) {
            return back()->with('error', 'Import tidak dapat disimpan karena masih ada baris error atau duplikat.');
        }

        try {
            $created = $this->purchases->storeMany(
                $preview['groups'],
                PersediaanAdminSession::userId($request),
            );
        } catch (DuplicateFakturException | PurchaseException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Import gagal disimpan. Tidak ada data yang masuk.');
        }

        $request->session()->forget(self::IMPORT_SESSION);

        return redirect()
            ->route('persediaan.admin.pembelian.index')
            ->with('success', 'Import berhasil. ' . count($created) . ' transaksi disimpan. Stok master bertambah hanya untuk barang yang terhubung ke master.');
    }

    private function filteredQuery(Request $request): Builder
    {
        $locked = PersediaanAccess::lockedUnitForApprover($request);
        $unit = $this->requestedUnit($request, $locked);

        return PersediaanPembelian::query()
            ->when($unit !== null, fn (Builder $q) => $q->where('unit_kerja', $unit))
            ->orderByDesc('tanggal')
            ->orderByDesc('id');
    }

    private function requestedUnit(Request $request, ?string $locked): ?string
    {
        if ($locked !== null) {
            return $locked;
        }

        $unit = $request->query('unit_kerja');
        if (is_string($unit) && UnitKerja::isValid($unit)) {
            return $unit;
        }

        return null;
    }

    /**
     * @return array<string,array{id:int,kode:string,nama:string,satuan:string,harga:float}>
     */
    private function masterMap(): array
    {
        $grouped = [];
        foreach (PersediaanBarang::query()->get() as $barang) {
            $key = $barang->unit_kerja . '|' . mb_strtoupper((string) $barang->kode_barang);
            $grouped[$key][] = [
                'id'     => (int) $barang->id,
                'kode'   => (string) $barang->kode_barang,
                'nama'   => (string) $barang->nama_barang,
                'satuan' => (string) $barang->satuan,
                'harga'  => (float) $barang->harga_satuan,
            ];
        }

        $map = [];
        foreach ($grouped as $key => $rows) {
            if (count($rows) === 1) {
                $map[$key] = $rows[0];
            }
        }

        return $map;
    }

    /**
     * @param  list<string>  $allowedExt
     */
    private function storeFile(Request $request, string $field, array $allowedExt): ?string
    {
        if (! $request->hasFile($field)) {
            return null;
        }

        $file = $request->file($field);
        if ($file === null) {
            return null;
        }

        $ext = strtolower($file->getClientOriginalExtension());
        if (! in_array($ext, $allowedExt, true)) {
            return null;
        }

        $dir = public_path('uploads/persediaan/pembelian');
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $name = $field . '_' . date('Ymd_His') . '_' . Str::random(16) . '.' . $ext;
        $file->move($dir, $name);

        return 'uploads/persediaan/pembelian/' . $name;
    }
}
