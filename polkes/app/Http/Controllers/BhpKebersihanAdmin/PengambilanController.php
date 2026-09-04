<?php

declare(strict_types=1);

namespace App\Http\Controllers\BhpKebersihanAdmin;

use App\Http\Controllers\Controller;
use App\Models\BhpKebersihan\BhpKebersihanPermintaan;
use App\Models\BhpKebersihan\BhpKebersihanStockMovement;
use App\Services\BhpKebersihan\StockService;
use App\Support\BhpKebersihan\BhpAccess;
use App\Support\BhpKebersihan\BhpAdminSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PengambilanController extends Controller
{
    public function __construct(private StockService $stock)
    {
    }

    public function index(Request $request): View
    {
        $permintaan = BhpKebersihanPermintaan::query()
            ->with('barang')
            ->whereIn('status', [
                BhpKebersihanPermintaan::STATUS_DISETUJUI,
                BhpKebersihanPermintaan::STATUS_SIAP_DIAMBIL,
            ])
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%' . $request->input('q') . '%';
                $q->where(fn ($w) => $w->where('nomor_permintaan', 'like', $term)
                    ->orWhere('nama_pengambil', 'like', $term));
            })
            ->orderByDesc('verified_at')
            ->paginate(15)
            ->withQueryString();

        return view('bhp_kebersihan_admin.pengambilan.index', [
            'permintaan' => $permintaan,
        ]);
    }

    public function show(int $id): View
    {
        $permintaan = BhpKebersihanPermintaan::with('barang', 'verifier')->findOrFail($id);

        return view('bhp_kebersihan_admin.pengambilan.show', [
            'permintaan' => $permintaan,
        ]);
    }

    public function sudahDiambil(Request $request, int $id): RedirectResponse
    {
        if (! BhpAccess::canProcessPickup(BhpAdminSession::role($request))) {
            abort(403);
        }

        $data = $request->validate([
            'process_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $userId = BhpAdminSession::userId($request);

        try {
            DB::transaction(function () use ($id, $data, $userId) {
                $permintaan = BhpKebersihanPermintaan::query()
                    ->whereKey($id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($permintaan->status === BhpKebersihanPermintaan::STATUS_SUDAH_DIAMBIL) {
                    return;
                }

                if (! $permintaan->canProcessPickup()) {
                    throw ValidationException::withMessages([
                        'status' => 'Permintaan tidak siap untuk pengambilan.',
                    ]);
                }

                $barang = $permintaan->barang()->lockForUpdate()->first();
                if ($barang === null) {
                    throw new \RuntimeException('Barang tidak ditemukan.');
                }

                $this->stock->recordKeluar(
                    $barang,
                    (int) $permintaan->jumlah_diminta,
                    BhpKebersihanStockMovement::SUMBER_PERMINTAAN,
                    $permintaan->id,
                    'Pengambilan permintaan ' . $permintaan->nomor_permintaan,
                    $userId,
                );

                $permintaan->status = BhpKebersihanPermintaan::STATUS_SUDAH_DIAMBIL;
                $permintaan->processed_by = $userId;
                $permintaan->processed_at = Carbon::now();
                $permintaan->process_note = $data['process_note'] ?? null;
                $permintaan->save();
            });
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        } catch (ValidationException $e) {
            $msg = collect($e->errors())->flatten()->first() ?: 'Permintaan tidak siap untuk pengambilan.';

            return back()->with('error', $msg);
        }

        return back()->with('success', 'Barang sudah diambil. Stok berkurang.');
    }
}
