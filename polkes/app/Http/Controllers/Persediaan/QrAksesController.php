<?php

declare(strict_types=1);

namespace App\Http\Controllers\Persediaan;

use App\Http\Controllers\Controller;
use App\Models\Persediaan\PersediaanQrAkses;
use App\Support\Persediaan\RackAccess;
use App\Support\Persediaan\UnitKerja;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class QrAksesController extends Controller
{
    public function show(Request $request, string $token): View|RedirectResponse|\Illuminate\Http\Response
    {
        $akses = PersediaanQrAkses::query()
            ->where('token', $token)
            ->where('is_active', true)
            ->first();

        if ($akses === null) {
            return response()
                ->view('persediaan.qr.invalid', [
                    'title'   => 'QR Pintu tidak valid',
                    'message' => 'QR Pintu tidak ditemukan atau sudah tidak aktif.',
                ], 404);
        }

        if (! RackAccess::isEnabledForUnit((string) $akses->unit_kerja)) {
            return redirect()
                ->route('public.persediaan.index')
                ->with('error', 'Sistem rak belum aktif untuk unit ini.');
        }

        $pemohon = Auth::guard('persediaan')->user();
        if ($pemohon === null) {
            $request->session()->put('url.intended', $request->fullUrl());

            return redirect()
                ->route('public.persediaan.login', UnitKerja::slugOf((string) $akses->unit_kerja))
                ->with('error', 'Silakan login sebagai pemohon ' . $akses->unit_kerja . ' untuk melanjutkan.');
        }

        if ((string) $pemohon->unit_kerja !== (string) $akses->unit_kerja) {
            return redirect()
                ->route('public.persediaan.permintaan.create')
                ->with('error', 'QR Pintu ini hanya untuk pemohon unit ' . $akses->unit_kerja . '.');
        }

        return view('persediaan.qr.identitas', [
            'akses'   => $akses,
            'pemohon' => $pemohon,
            'tanggal' => Carbon::now(),
        ]);
    }
}
