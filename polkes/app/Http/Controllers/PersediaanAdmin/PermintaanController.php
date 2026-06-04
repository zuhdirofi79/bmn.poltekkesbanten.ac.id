<?php

declare(strict_types=1);

namespace App\Http\Controllers\PersediaanAdmin;

use App\Http\Controllers\Controller;
use App\Models\Persediaan\PersediaanBarang;
use App\Models\Persediaan\PersediaanPermintaan;
use App\Models\Persediaan\PersediaanStockMovement;
use App\Services\Persediaan\SignatureService;
use App\Services\Persediaan\StockService;
use App\Support\Persediaan\UnitKerja;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class PermintaanController extends Controller
{
    public function __construct(
        private StockService $stock,
        private SignatureService $signature,
    ) {
    }

    public function index(Request $request): View
    {
        $permintaan = PersediaanPermintaan::query()
            ->with('pemohon')
            ->when($request->filled('unit'), fn ($q) => $q->where('unit_kerja', $request->input('unit')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('q'), fn ($q) => $q->where('nomor_permintaan', 'like', '%' . $request->input('q') . '%'))
            ->orderByRaw("FIELD(status,'diajukan','menunggu_persetujuan','disetujui','barang_dikeluarkan','selesai','ditolak','draft')")
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return view('persediaan_admin.permintaan.index', [
            'permintaan' => $permintaan,
            'units'      => UnitKerja::all(),
        ]);
    }

    public function show(int $id): View
    {
        $permintaan = PersediaanPermintaan::with(['items', 'pemohon'])->findOrFail($id);

        return view('persediaan_admin.permintaan.show', [
            'permintaan' => $permintaan,
        ]);
    }

    public function setujui(Request $request, int $id): RedirectResponse
    {
        $permintaan = PersediaanPermintaan::with('items')->findOrFail($id);

        if (! $permintaan->isPending()) {
            return back()->with('error', 'Permintaan ini sudah diproses.');
        }

        $data = $request->validate([
            'tanda_tangan_admin'        => ['required', 'string'],
            'jumlah_disetujui'          => ['array'],
            'jumlah_disetujui.*'        => ['nullable', 'integer', 'min:0'],
        ]);

        $ttd = $this->signature->storeBase64($data['tanda_tangan_admin']);
        if ($ttd === null) {
            return back()->with('error', 'Tanda tangan admin tidak valid.');
        }

        DB::transaction(function () use ($permintaan, $data, $ttd, $request) {
            foreach ($permintaan->items as $item) {
                $approved = $data['jumlah_disetujui'][$item->id] ?? $item->jumlah_diminta;
                $item->jumlah_disetujui = max(0, (int) $approved);
                $item->save();
            }

            $permintaan->status = PersediaanPermintaan::STATUS_DISETUJUI;
            $permintaan->approved_by = $request->user()->id;
            $permintaan->approved_at = Carbon::now();
            $permintaan->tanda_tangan_admin_path = $ttd;
            $permintaan->save();
        });

        return back()->with('success', 'Permintaan disetujui dan ditandatangani.');
    }

    public function tolak(Request $request, int $id): RedirectResponse
    {
        $permintaan = PersediaanPermintaan::findOrFail($id);

        if (! $permintaan->isPending()) {
            return back()->with('error', 'Permintaan ini sudah diproses.');
        }

        $data = $request->validate([
            'catatan_admin' => ['required', 'string', 'max:1000'],
        ]);

        $permintaan->status = PersediaanPermintaan::STATUS_DITOLAK;
        $permintaan->catatan_admin = $data['catatan_admin'];
        $permintaan->approved_by = $request->user()->id;
        $permintaan->approved_at = Carbon::now();
        $permintaan->save();

        return back()->with('success', 'Permintaan ditolak.');
    }

    /**
     * Keluarkan barang -> status barang_dikeluarkan -> selesai,
     * dan stok berkurang otomatis (movement permintaan_keluar).
     */
    public function keluarkan(Request $request, int $id): RedirectResponse
    {
        $permintaan = PersediaanPermintaan::with('items')->findOrFail($id);

        if ($permintaan->status !== PersediaanPermintaan::STATUS_DISETUJUI) {
            return back()->with('error', 'Hanya permintaan berstatus disetujui yang dapat dikeluarkan.');
        }

        DB::transaction(function () use ($permintaan, $request) {
            foreach ($permintaan->items as $item) {
                $qty = (int) ($item->jumlah_disetujui ?? $item->jumlah_diminta);
                if ($qty <= 0 || ! $item->barang_id) {
                    continue;
                }

                $this->stock->recordMovement([
                    'barang_id'  => $item->barang_id,
                    'unit_kerja' => $permintaan->unit_kerja,
                    'tipe'       => PersediaanStockMovement::TIPE_KELUAR,
                    'ref_table'  => 'persediaan_permintaan',
                    'ref_id'     => $permintaan->id,
                    'qty_keluar' => $qty,
                    'keterangan' => 'Pengeluaran barang permintaan ' . $permintaan->nomor_permintaan,
                    'created_by' => $request->user()->id,
                ]);
            }

            $permintaan->status = PersediaanPermintaan::STATUS_SELESAI;
            $permintaan->save();
        });

        return back()->with('success', 'Barang dikeluarkan, stok berkurang otomatis, permintaan selesai.');
    }

    public function pdf(int $id): Response
    {
        $permintaan = PersediaanPermintaan::with(['items', 'pemohon'])->findOrFail($id);

        $pdf = Pdf::loadView('persediaan.permintaan.pdf', [
            'permintaan' => $permintaan,
            'pemohon'    => $permintaan->pemohon,
        ])->setPaper('a4', 'portrait');

        return $pdf->stream('Permintaan-' . $permintaan->nomor_permintaan . '.pdf');
    }
}
