<?php

declare(strict_types=1);

namespace App\Http\Controllers\Persediaan;

use App\Http\Controllers\Controller;
use App\Models\Persediaan\PersediaanRak;
use App\Services\Persediaan\PersediaanRackService;
use App\Services\Persediaan\PersediaanRequestService;
use App\Support\Persediaan\RackAccess;
use App\Support\Persediaan\UnitKerja;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;
use InvalidArgumentException;

class QrRakController extends Controller
{
    public function __construct(
        private PersediaanRackService $racks,
        private PersediaanRequestService $requests,
    ) {
    }

    public function show(Request $request, string $token): View|RedirectResponse|Response
    {
        $rak = $this->findActiveRak($token);
        if (! $rak instanceof PersediaanRak) {
            return $rak;
        }

        $auth = $this->requirePemohon($request, $rak);
        if ($auth instanceof RedirectResponse) {
            return $auth;
        }

        if (! RackAccess::pemohonCanUseRak($auth, $rak)) {
            return redirect()
                ->route('public.persediaan.index')
                ->with('error', 'QR Rak ini hanya untuk pemohon unit ' . $rak->unit_kerja . '. Barang tidak ditampilkan.');
        }

        $items = $this->racks->itemsWithStock($rak);
        $draft = (array) $request->session()->get($this->draftKey($rak), []);

        return view('persediaan.qr.rak', [
            'rak'     => $rak,
            'pemohon' => $auth,
            'items'   => $items,
            'draft'   => $draft,
        ]);
    }

    public function confirm(Request $request, string $token): View|RedirectResponse|Response
    {
        $rak = $this->findActiveRak($token);
        if (! $rak instanceof PersediaanRak) {
            return $rak;
        }

        $pemohon = $this->requirePemohon($request, $rak);
        if ($pemohon instanceof RedirectResponse) {
            return $pemohon;
        }
        if (! RackAccess::pemohonCanUseRak($pemohon, $rak)) {
            return redirect()
                ->route('public.persediaan.index')
                ->with('error', 'QR Rak ini hanya untuk pemohon unit ' . $rak->unit_kerja . '.');
        }

        $qty = $this->normalizeQty($request->input('qty', []));
        if ($qty === []) {
            return redirect()
                ->route('public.persediaan.rak.show', $token)
                ->with('error', 'Pilih minimal satu barang dengan jumlah lebih dari 0.');
        }

        $items = $this->racks->itemsWithStock($rak);
        $chosen = [];
        foreach ($items as $row) {
            $id = (int) $row['barang']->id;
            $jumlah = (int) ($qty[$id] ?? 0);
            if ($jumlah <= 0) {
                continue;
            }
            if ($jumlah > (int) $row['stok']) {
                return redirect()
                    ->route('public.persediaan.rak.show', $token)
                    ->withInput()
                    ->with('error', 'Jumlah "' . $row['barang']->nama_barang . '" melebihi stok tersedia.');
            }
            $chosen[] = [
                'barang' => $row['barang'],
                'stok'   => $row['stok'],
                'jumlah' => $jumlah,
            ];
        }

        if ($chosen === []) {
            return redirect()
                ->route('public.persediaan.rak.show', $token)
                ->with('error', 'Pilih minimal satu barang dengan jumlah lebih dari 0.');
        }

        $idempotencyKey = (string) $request->session()->get($this->idempKey($rak));
        if ($idempotencyKey === '') {
            $idempotencyKey = Str::lower(Str::random(40));
            $request->session()->put($this->idempKey($rak), $idempotencyKey);
        }

        $request->session()->put($this->draftKey($rak), $qty);

        return view('persediaan.qr.konfirmasi', [
            'rak'             => $rak,
            'pemohon'         => $pemohon,
            'chosen'          => $chosen,
            'idempotency_key' => $idempotencyKey,
        ]);
    }

    public function store(Request $request, string $token): RedirectResponse
    {
        $rak = $this->findActiveRak($token);
        if (! $rak instanceof PersediaanRak) {
            return redirect()->route('public.persediaan.index')->with('error', 'QR Rak tidak valid.');
        }

        $pemohon = $this->requirePemohon($request, $rak);
        if ($pemohon instanceof RedirectResponse) {
            return $pemohon;
        }
        if (! RackAccess::pemohonCanUseRak($pemohon, $rak)) {
            return redirect()
                ->route('public.persediaan.index')
                ->with('error', 'QR Rak ini hanya untuk pemohon unit ' . $rak->unit_kerja . '.');
        }

        $data = $request->validate([
            'qty'             => ['required', 'array'],
            'qty.*'           => ['nullable', 'integer', 'min:0'],
            'idempotency_key' => ['required', 'string', 'max:64'],
        ]);

        $sessionKey = (string) $request->session()->get($this->idempKey($rak));
        if ($sessionKey === '' || ! hash_equals($sessionKey, (string) $data['idempotency_key'])) {
            return redirect()
                ->route('public.persediaan.rak.show', $token)
                ->with('error', 'Sesi konfirmasi tidak valid. Silakan pilih ulang barang.');
        }

        $qty = $this->normalizeQty($data['qty']);

        try {
            $permintaan = $this->requests->createFromRack($pemohon, $rak, $qty, $sessionKey);
        } catch (InvalidArgumentException $e) {
            return redirect()
                ->route('public.persediaan.rak.show', $token)
                ->withInput()
                ->with('error', $e->getMessage());
        }

        $request->session()->forget($this->draftKey($rak));
        $request->session()->forget($this->idempKey($rak));

        return redirect()
            ->route('public.persediaan.rak.success', $permintaan->id)
            ->with('success', 'Permintaan berhasil diajukan.');
    }

    public function success(Request $request, int $id): View|RedirectResponse
    {
        $pemohon = Auth::guard('persediaan')->user();
        if ($pemohon === null) {
            return redirect()->route('public.persediaan.index');
        }

        $permintaan = \App\Models\Persediaan\PersediaanPermintaan::query()
            ->with(['items', 'rak'])
            ->whereKey($id)
            ->where('pemohon_id', $pemohon->id)
            ->firstOrFail();

        return view('persediaan.qr.success', [
            'pemohon'    => $pemohon,
            'permintaan' => $permintaan,
        ]);
    }

    private function findActiveRak(string $token): PersediaanRak|RedirectResponse|Response
    {
        $rak = PersediaanRak::query()->where('qr_token', $token)->first();
        if ($rak === null || ! $rak->isAktif()) {
            return $this->invalidQr();
        }

        if (! RackAccess::isEnabledForUnit((string) $rak->unit_kerja)) {
            return redirect()
                ->route('public.persediaan.index')
                ->with('error', 'Sistem rak belum aktif untuk unit ini.');
        }

        return $rak;
    }

    /**
     * @return \App\Models\Persediaan\PersediaanPemohon|RedirectResponse
     */
    private function requirePemohon(Request $request, PersediaanRak $rak)
    {
        $pemohon = Auth::guard('persediaan')->user();
        if ($pemohon === null) {
            $request->session()->put('url.intended', $request->fullUrl());

            return redirect()
                ->route('public.persediaan.login', UnitKerja::slugOf((string) $rak->unit_kerja))
                ->with('error', 'Silakan login sebagai pemohon ' . $rak->unit_kerja . ' untuk membuka rak.');
        }

        return $pemohon;
    }

    private function invalidQr(): RedirectResponse|\Illuminate\Http\Response
    {
        return response()->view('persediaan.qr.invalid', [
            'title'   => 'QR Rak tidak valid',
            'message' => 'QR Rak tidak ditemukan atau rak tidak aktif.',
        ], 404);
    }

    /**
     * @param  mixed  $raw
     * @return array<int,int>
     */
    private function normalizeQty(mixed $raw): array
    {
        $out = [];
        foreach ((array) $raw as $id => $qty) {
            $jumlah = (int) $qty;
            if ($jumlah > 0) {
                $out[(int) $id] = $jumlah;
            }
        }

        return $out;
    }

    private function draftKey(PersediaanRak $rak): string
    {
        return 'persediaan_rak_draft_' . $rak->id;
    }

    private function idempKey(PersediaanRak $rak): string
    {
        return 'persediaan_rak_idemp_' . $rak->id;
    }

    private function pintuTokenHint(PersediaanRak $rak): string
    {
        $akses = \App\Models\Persediaan\PersediaanQrAkses::query()
            ->where('unit_kerja', $rak->unit_kerja)
            ->where('is_active', true)
            ->orderByDesc('id')
            ->value('token');

        return (string) ($akses ?: 'invalid');
    }
}
