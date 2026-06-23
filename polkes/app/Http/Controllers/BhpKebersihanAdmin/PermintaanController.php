<?php

declare(strict_types=1);

namespace App\Http\Controllers\BhpKebersihanAdmin;

use App\Http\Controllers\Controller;
use App\Models\BhpKebersihan\BhpKebersihanPermintaan;
use App\Support\BhpKebersihan\BhpAccess;
use App\Support\BhpKebersihan\BhpAdminSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class PermintaanController extends Controller
{
    public function index(Request $request): View
    {
        $permintaan = BhpKebersihanPermintaan::query()
            ->with('barang')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%' . $request->input('q') . '%';
                $q->where(fn ($w) => $w->where('nomor_permintaan', 'like', $term)
                    ->orWhere('nama_pengambil', 'like', $term));
            })
            ->orderByRaw("FIELD(status,'pending','disetujui','siap_diambil','sudah_diambil','ditolak')")
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return view('bhp_kebersihan_admin.permintaan.index', [
            'permintaan' => $permintaan,
            'role'       => BhpAdminSession::role($request),
            'canVerify'  => BhpAccess::canVerify(BhpAdminSession::role($request)),
        ]);
    }

    public function show(Request $request, int $id): View
    {
        $permintaan = BhpKebersihanPermintaan::with('barang', 'verifier', 'processor')->findOrFail($id);
        $role = BhpAdminSession::role($request);

        return view('bhp_kebersihan_admin.permintaan.show', [
            'permintaan' => $permintaan,
            'canVerify'  => BhpAccess::canVerify($role) && $permintaan->canVerify(),
        ]);
    }

    public function setujui(Request $request, int $id): RedirectResponse
    {
        if (! BhpAccess::canVerify(BhpAdminSession::role($request))) {
            abort(403);
        }

        $permintaan = BhpKebersihanPermintaan::with('barang')->findOrFail($id);
        if (! $permintaan->canVerify()) {
            return back()->with('error', 'Permintaan tidak dapat diverifikasi.');
        }

        $data = $request->validate([
            'verification_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $userId = BhpAdminSession::userId($request);

        $permintaan->status = BhpKebersihanPermintaan::STATUS_SIAP_DIAMBIL;
        $permintaan->verified_by = $userId;
        $permintaan->verified_at = Carbon::now();
        $permintaan->verification_note = $data['verification_note'] ?? null;
        $permintaan->save();

        return back()->with('success', 'Permintaan disetujui. Menunggu pengambilan barang.');
    }

    public function tolak(Request $request, int $id): RedirectResponse
    {
        if (! BhpAccess::canVerify(BhpAdminSession::role($request))) {
            abort(403);
        }

        $permintaan = BhpKebersihanPermintaan::findOrFail($id);
        if (! $permintaan->canVerify()) {
            return back()->with('error', 'Permintaan tidak dapat ditolak.');
        }

        $data = $request->validate([
            'verification_note' => ['required', 'string', 'max:1000'],
        ]);

        $permintaan->status = BhpKebersihanPermintaan::STATUS_DITOLAK;
        $permintaan->verified_by = BhpAdminSession::userId($request);
        $permintaan->verified_at = Carbon::now();
        $permintaan->verification_note = $data['verification_note'];
        $permintaan->save();

        return back()->with('success', 'Permintaan ditolak.');
    }
}
