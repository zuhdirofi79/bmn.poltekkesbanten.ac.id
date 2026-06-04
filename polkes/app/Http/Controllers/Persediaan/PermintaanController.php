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
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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

    public function create(): View
    {
        $pemohon = $this->pemohon();
        $barangs = $this->barangForUnit($pemohon->unit_kerja);
        $ringkasan = $this->stock->ringkasanBanyak($barangs);

        return view('persediaan.permintaan.create', [
            'pemohon'   => $pemohon,
            'barangs'   => $barangs,
            'ringkasan' => $ringkasan,
            'tanggal'   => Carbon::today(),
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

            $permintaan = PersediaanPermintaan::create([
                'nomor_permintaan'          => $nomor,
                'pemohon_id'                => $pemohon->id,
                'unit_kerja'                => $pemohon->unit_kerja,
                'tanggal_permintaan'        => Carbon::today(),
                'keterangan'                => $data['keterangan'],
                'status'                    => $isAjukan
                    ? PersediaanPermintaan::STATUS_DIAJUKAN
                    : PersediaanPermintaan::STATUS_DRAFT,
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

        $permintaan->status = PersediaanPermintaan::STATUS_DIAJUKAN;
        $permintaan->save();

        return redirect()
            ->route('public.persediaan.permintaan.show', $permintaan->id)
            ->with('success', 'Permintaan berhasil diajukan.');
    }

    public function show(int $id): View
    {
        $pemohon = $this->pemohon();
        $permintaan = $this->findOwned($id, $pemohon->id, ['items']);

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

    private function barangForUnit(string $unit)
    {
        return PersediaanBarang::query()
            ->where('unit_kerja', $unit)
            ->where('is_active', true)
            ->orderBy('kode_barang')
            ->get();
    }

    private function findOwned(int $id, int $pemohonId, array $with = []): PersediaanPermintaan
    {
        return PersediaanPermintaan::with($with)
            ->where('id', $id)
            ->where('pemohon_id', $pemohonId)
            ->firstOrFail();
    }
}
