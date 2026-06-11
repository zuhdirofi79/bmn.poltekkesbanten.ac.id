<?php

declare(strict_types=1);

namespace App\Http\Controllers\Persediaan;

use App\Http\Controllers\Controller;
use App\Models\Persediaan\PersediaanBarang;
use App\Models\Persediaan\PersediaanPermintaan;
use App\Models\Persediaan\PersediaanPermintaanItem;
use App\Services\Persediaan\NomorPermintaanService;
use App\Services\Persediaan\SignatureService;
use App\Services\Persediaan\StockService;
use App\Support\Persediaan\PersediaanAccess;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class PermintaanController extends Controller
{
    public function __construct(
        private StockService $stock,
        private NomorPermintaanService $nomor,
        private SignatureService $signature,
    ) {
    }

    public function index(Request $request): View
    {
        $pemohon = $this->pemohon();

        $permintaan = PersediaanPermintaan::query()
            ->where('pemohon_id', $pemohon->id)
            ->when($request->filled('q'), function ($q) use ($request) {
                $q->where('nomor_permintaan', 'like', '%' . $request->input('q') . '%');
            })
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return view('persediaan.permintaan.index', [
            'pemohon'    => $pemohon,
            'permintaan' => $permintaan,
        ]);
    }

    public function create(Request $request): View
    {
        $pemohon = $this->pemohon();
        $unit = $pemohon->unit_kerja;
        $columnFilters = $this->extractColumnFilters($request);
        $satuanGlobal = is_string($request->input('satuan')) ? $request->input('satuan') : '';
        $filters = array_merge(
            $request->only(['q', 'stok']),
            ['satuan' => $satuanGlobal],
        );
        [$barangs, $ringkasan] = $this->filteredBarangsForUnit($request, $unit);

        return view('persediaan.permintaan.create', [
            'pemohon'             => $pemohon,
            'barangs'             => $barangs,
            'ringkasan'           => $ringkasan,
            'tanggal'             => Carbon::today(),
            'filters'             => $filters,
            'columnFilters'       => $columnFilters,
            'filterOptions'       => $this->buildColumnFilterOptions($unit),
            'satuanOptions'       => $this->satuanOptionsForUnit($unit),
            'hasBarangInUnit'     => $this->baseBarangQuery($unit)->exists(),
            'filterActive'        => $request->filled('q')
                || $satuanGlobal !== ''
                || $request->filled('stok')
                || $columnFilters !== [],
            'hasColumnFilter'     => $columnFilters !== [],
            'preservedKeterangan' => $request->input('keterangan', ''),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $pemohon = $this->pemohon();

        $data = $request->validate([
            'keterangan'              => ['required', 'string', 'max:1000'],
            'action'                  => ['required', 'in:draft,ajukan'],
            'items'                   => ['required', 'array', 'min:1'],
            'items.*.barang_id'       => ['required', 'integer'],
            'items.*.jumlah_diminta'  => ['nullable', 'integer', 'min:0'],
            'tanda_tangan_pemohon'    => ['nullable', 'string'],
        ]);

        // Hanya barang milik unit pemohon yang valid.
        $barangs = $this->barangForUnit($pemohon->unit_kerja)->keyBy('id');

        $chosen = [];
        foreach ($data['items'] as $row) {
            $jumlah = (int) ($row['jumlah_diminta'] ?? 0);
            if ($jumlah <= 0) {
                continue;
            }
            $barang = $barangs->get((int) $row['barang_id']);
            if ($barang === null) {
                continue;
            }
            $stokTersedia = $this->stock->stokAkhir($barang);
            if ($stokTersedia <= 0) {
                return back()
                    ->withInput()
                    ->withErrors([
                        'items' => 'Barang "' . $barang->nama_barang . '" stok kosong, tidak dapat diminta.',
                    ]);
            }
            if ($jumlah > $stokTersedia) {
                return back()
                    ->withInput()
                    ->withErrors([
                        'items' => sprintf(
                            'Jumlah diminta "%s" (%d) melebihi stok tersedia (%s).',
                            $barang->nama_barang,
                            $jumlah,
                            number_format($stokTersedia, 0, ',', '.'),
                        ),
                    ]);
            }
            $chosen[] = ['barang' => $barang, 'jumlah' => $jumlah];
        }

        if (empty($chosen)) {
            return back()
                ->withInput()
                ->withErrors(['items' => 'Minimal satu barang dengan jumlah diminta > 0.']);
        }

        $isAjukan = $data['action'] === 'ajukan';

        if ($isAjukan && empty($data['tanda_tangan_pemohon'])) {
            return back()
                ->withInput()
                ->withErrors(['tanda_tangan_pemohon' => 'Tanda tangan pemohon wajib diisi untuk mengajukan permintaan.']);
        }

        $ttdPath = $this->signature->storeBase64($data['tanda_tangan_pemohon'] ?? null);

        $permintaan = DB::transaction(function () use ($pemohon, $chosen, $data, $isAjukan, $ttdPath) {
            $nomor = $this->nomor->generate($pemohon->unit_kerja);
            $approverUserId = PersediaanAccess::approverUserIdForUnit($pemohon->unit_kerja);

            $permintaan = PersediaanPermintaan::create([
                'nomor_permintaan'          => $nomor,
                'pemohon_id'                => $pemohon->id,
                'unit_kerja'                => $pemohon->unit_kerja,
                'tanggal_permintaan'        => Carbon::today(),
                'keterangan'                => $data['keterangan'],
                'status'                    => $isAjukan
                    ? PersediaanPermintaan::STATUS_MENUNGGU
                    : PersediaanPermintaan::STATUS_DRAFT,
                'approver_user_id'          => $isAjukan ? $approverUserId : null,
                'tanda_tangan_pemohon_path' => $ttdPath,
            ]);

            foreach ($chosen as $row) {
                $barang = $row['barang'];
                PersediaanPermintaanItem::create([
                    'permintaan_id'  => $permintaan->id,
                    'barang_id'      => $barang->id,
                    'kode_barang'    => $barang->kode_barang,
                    'nama_barang'    => $barang->nama_barang,
                    'satuan'         => $barang->satuan,
                    'stok_tersedia'  => $this->stock->stokAkhir($barang),
                    'jumlah_diminta' => $row['jumlah'],
                ]);
            }

            return $permintaan;
        });

        $msg = $isAjukan
            ? 'Permintaan berhasil diajukan dengan nomor ' . $permintaan->nomor_permintaan . '.'
            : 'Draft permintaan tersimpan dengan nomor ' . $permintaan->nomor_permintaan . '.';

        return redirect()
            ->route('public.persediaan.permintaan.show', $permintaan->id)
            ->with('success', $msg);
    }

    public function ajukan(Request $request, int $id): RedirectResponse
    {
        $pemohon = $this->pemohon();
        $permintaan = $this->findOwned($id, $pemohon->id);

        if ($permintaan->status !== PersediaanPermintaan::STATUS_DRAFT) {
            return back()->with('error', 'Hanya draft yang dapat diajukan.');
        }

        $request->validate([
            'tanda_tangan_pemohon' => ['nullable', 'string'],
        ]);

        if (! $permintaan->tanda_tangan_pemohon_path && empty($request->input('tanda_tangan_pemohon'))) {
            return back()->withErrors(['tanda_tangan_pemohon' => 'Tanda tangan pemohon wajib diisi.']);
        }

        if ($ttd = $this->signature->storeBase64($request->input('tanda_tangan_pemohon'))) {
            $permintaan->tanda_tangan_pemohon_path = $ttd;
        }

        $permintaan->status = PersediaanPermintaan::STATUS_MENUNGGU;
        $permintaan->approver_user_id = PersediaanAccess::approverUserIdForUnit($pemohon->unit_kerja);
        $permintaan->save();

        return redirect()
            ->route('public.persediaan.permintaan.show', $permintaan->id)
            ->with('success', 'Permintaan berhasil diajukan.');
    }

    public function show(int $id): View
    {
        $pemohon = $this->pemohon();
        $permintaan = $this->findOwned($id, $pemohon->id, ['items', 'approver']);

        return view('persediaan.permintaan.show', [
            'pemohon'    => $pemohon,
            'permintaan' => $permintaan,
        ]);
    }

    public function pdf(int $id): Response
    {
        $pemohon = $this->pemohon();
        $permintaan = $this->findOwned($id, $pemohon->id, ['items']);

        $pdf = Pdf::loadView('persediaan.permintaan.pdf', [
            'permintaan' => $permintaan,
            'pemohon'    => $pemohon,
        ])->setPaper('a4', 'portrait');

        return $pdf->stream('Permintaan-' . $permintaan->nomor_permintaan . '.pdf');
    }

    private function pemohon()
    {
        $pemohon = Auth::guard('persediaan')->user();
        abort_if($pemohon === null, 403);

        return $pemohon;
    }

    /**
     * @return Builder<PersediaanBarang>
     */
    private function baseBarangQuery(string $unit): Builder
    {
        return PersediaanBarang::query()
            ->where('unit_kerja', $unit)
            ->where('is_active', true);
    }

    private function barangForUnit(string $unit): Collection
    {
        return $this->baseBarangQuery($unit)
            ->orderBy('kode_barang')
            ->get();
    }

    /**
     * @return array{0: Collection<int, PersediaanBarang>, 1: array<int, array{awal:int,masuk:int,keluar:int,akhir:int}>}
     */
    private function filteredBarangsForUnit(Request $request, string $unit): array
    {
        $query = $this->baseBarangQuery($unit);
        $columnFilters = $this->extractColumnFilters($request);

        if ($request->filled('q')) {
            $term = '%' . $request->input('q') . '%';
            $query->where(function ($w) use ($term) {
                $w->where('kode_barang', 'like', $term)
                    ->orWhere('nama_barang', 'like', $term);
            });
        }

        if (is_string($request->input('satuan')) && $request->filled('satuan')) {
            $query->where('satuan', $request->input('satuan'));
        }

        if (! empty($columnFilters['kode_barang'])) {
            $query->whereIn('kode_barang', $columnFilters['kode_barang']);
        }

        if (! empty($columnFilters['nama_barang'])) {
            $query->whereIn('nama_barang', $columnFilters['nama_barang']);
        }

        if (! empty($columnFilters['satuan'])) {
            $query->whereIn('satuan', $columnFilters['satuan']);
        }

        $barangs = $query->orderBy('kode_barang')->get();
        $ringkasan = $this->stock->ringkasanBanyak($barangs);

        $stok = $request->input('stok');
        if ($stok === 'tersedia') {
            $barangs = $barangs->filter(
                fn (PersediaanBarang $b): bool => ($ringkasan[(int) $b->id]['akhir'] ?? 0) > 0,
            );
        } elseif ($stok === 'kosong') {
            $barangs = $barangs->filter(
                fn (PersediaanBarang $b): bool => ($ringkasan[(int) $b->id]['akhir'] ?? 0) <= 0,
            );
        } elseif ($stok === 'menipis') {
            $barangs = $barangs->filter(function (PersediaanBarang $b) use ($ringkasan): bool {
                $akhir = (int) ($ringkasan[(int) $b->id]['akhir'] ?? 0);

                return $akhir > 0 && $akhir <= (int) $b->stok_minimum;
            });
        }

        if (! empty($columnFilters['stok_tersedia'])) {
            $allowed = array_map('intval', $columnFilters['stok_tersedia']);
            $barangs = $barangs->filter(function (PersediaanBarang $b) use ($ringkasan, $allowed): bool {
                $akhir = (int) ($ringkasan[(int) $b->id]['akhir'] ?? 0);

                return in_array($akhir, $allowed, true);
            });
        }

        return [$barangs->values(), $ringkasan];
    }

    /**
     * @return array<string, list<string>>
     */
    private function extractColumnFilters(Request $request): array
    {
        $filters = [];

        foreach (['kode_barang', 'nama_barang', 'stok_tersedia'] as $key) {
            $values = array_values(array_filter(
                array_map('strval', (array) $request->input($key, [])),
                static fn (string $v): bool => $v !== '',
            ));

            if ($values !== []) {
                $filters[$key] = $values;
            }
        }

        $satuan = $request->input('satuan');
        if (is_array($satuan)) {
            $values = array_values(array_filter(
                array_map('strval', $satuan),
                static fn (string $v): bool => $v !== '',
            ));

            if ($values !== []) {
                $filters['satuan'] = $values;
            }
        }

        return $filters;
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function buildColumnFilterOptions(string $unit): array
    {
        $barangs = $this->baseBarangQuery($unit)->orderBy('kode_barang')->get();
        $ringkasan = $this->stock->ringkasanBanyak($barangs);

        $kode = $barangs->pluck('kode_barang')
            ->filter(static fn ($v) => $v !== null && $v !== '')
            ->unique()
            ->sort()
            ->values()
            ->map(static fn ($v) => (string) $v)
            ->all();

        $nama = $barangs->pluck('nama_barang')
            ->filter(static fn ($v) => $v !== null && $v !== '')
            ->unique()
            ->sort()
            ->values()
            ->map(static fn ($v) => (string) $v)
            ->all();

        $satuan = $barangs->pluck('satuan')
            ->filter(static fn ($v) => $v !== null && $v !== '')
            ->unique()
            ->sort()
            ->values()
            ->map(static fn ($v) => (string) $v)
            ->all();

        $stokTersedia = [];
        foreach ($barangs as $barang) {
            $akhir = (int) ($ringkasan[(int) $barang->id]['akhir'] ?? 0);
            $stokTersedia[(string) $akhir] = number_format($akhir, 0, ',', '.');
        }
        ksort($stokTersedia, SORT_NUMERIC);

        return [
            'kode_barang'   => array_combine($kode, $kode) ?: [],
            'nama_barang'   => array_combine($nama, $nama) ?: [],
            'satuan'        => array_combine($satuan, $satuan) ?: [],
            'stok_tersedia' => $stokTersedia,
        ];
    }

    /**
     * @return list<string>
     */
    private function satuanOptionsForUnit(string $unit): array
    {
        return $this->baseBarangQuery($unit)
            ->select('satuan')
            ->distinct()
            ->orderBy('satuan')
            ->pluck('satuan')
            ->filter(static fn ($v) => $v !== null && $v !== '')
            ->map(static fn ($v) => (string) $v)
            ->values()
            ->all();
    }

    private function findOwned(int $id, int $pemohonId, array $with = []): PersediaanPermintaan
    {
        return PersediaanPermintaan::with($with)
            ->where('id', $id)
            ->where('pemohon_id', $pemohonId)
            ->firstOrFail();
    }
}
