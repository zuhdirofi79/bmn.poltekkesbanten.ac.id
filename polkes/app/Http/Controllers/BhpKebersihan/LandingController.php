<?php

declare(strict_types=1);

namespace App\Http\Controllers\BhpKebersihan;

use App\Http\Controllers\Controller;
use App\Models\BhpKebersihan\BhpKebersihanBarang;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LandingController extends Controller
{
    public function index(): View
    {
        return view('bhp_kebersihan.public.index');
    }

    public function search(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'kode' => ['required', 'string', 'max:500'],
        ]);

        $kode = trim($data['kode']);

        if (preg_match('/^https?:\/\//i', $kode)) {
            if (preg_match('/bhp-kebersihan\/barang\/([^/?#]+)/i', $kode, $matches)) {
                return redirect()->route('public.bhp-kebersihan.barang.show', $matches[1]);
            }

            return redirect()->away($kode);
        }

        $barang = BhpKebersihanBarang::where('barcode_token', $kode)
            ->where('status', 'aktif')
            ->first();

        if ($barang === null) {
            $barang = BhpKebersihanBarang::where('kode_barang', $kode)
                ->where('status', 'aktif')
                ->first();
        }

        if ($barang === null) {
            return redirect()
                ->route('public.bhp-kebersihan.index')
                ->withInput()
                ->with('error', 'Barang BHP tidak ditemukan atau tidak aktif.');
        }

        return redirect()->route('public.bhp-kebersihan.barang.show', $barang->barcode_token);
    }
}
