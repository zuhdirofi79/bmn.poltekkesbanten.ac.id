<?php

declare(strict_types=1);

namespace App\Http\Controllers\Persediaan;

use App\Http\Controllers\Controller;
use App\Models\Persediaan\PersediaanPemohon;
use App\Support\Persediaan\UnitKerja;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Login/Register pemohon Persediaan (guard 'persediaan', login via NIP).
 * Terpisah penuh dari login admin BMN (/login) dan admin sewa (/sewa-admin).
 */
class PemohonAuthController extends Controller
{
    public function showLogin(string $unit): View
    {
        $unitName = $this->resolveUnit($unit);

        return view('persediaan.auth.login', [
            'unitSlug' => $unit,
            'unitName' => $unitName,
        ]);
    }

    public function login(Request $request, string $unit): RedirectResponse
    {
        $this->resolveUnit($unit);

        $request->validate([
            'nip'      => ['required', 'string', 'max:50'],
            'password' => ['required', 'string'],
        ]);

        $credentials = [
            'nip'      => (string) $request->input('nip'),
            'password' => (string) $request->input('password'),
        ];

        if (! Auth::guard('persediaan')->attempt($credentials, (bool) $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'nip' => 'NIP atau password salah.',
            ]);
        }

        $request->session()->regenerate();

        return redirect()->route('public.persediaan.permintaan.create');
    }

    public function showRegister(string $unit): View
    {
        $unitName = $this->resolveUnit($unit);

        return view('persediaan.auth.register', [
            'unitSlug' => $unit,
            'unitName' => $unitName,
            'units'    => UnitKerja::all(),
        ]);
    }

    public function register(Request $request, string $unit): RedirectResponse
    {
        $unitName = $this->resolveUnit($unit);

        $data = $request->validate([
            'nama'       => ['required', 'string', 'max:150'],
            'nip'        => ['required', 'string', 'max:50', Rule::unique('persediaan_pemohon', 'nip')],
            'email'      => ['nullable', 'email', 'max:150'],
            'nomor_wa'   => ['nullable', 'string', 'max:30'],
            'jabatan'    => ['nullable', 'string', 'max:150'],
            'unit_kerja' => ['required', Rule::in(UnitKerja::all())],
            'password'   => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        $pemohon = PersediaanPemohon::create([
            'nama'       => $data['nama'],
            'nip'        => $data['nip'],
            'email'      => $data['email'] ?? null,
            'nomor_wa'   => $data['nomor_wa'] ?? null,
            'jabatan'    => $data['jabatan'] ?? null,
            'unit_kerja' => $data['unit_kerja'],
            'password'   => Hash::make($data['password']),
        ]);

        Auth::guard('persediaan')->login($pemohon);
        $request->session()->regenerate();

        return redirect()->route('public.persediaan.permintaan.create')
            ->with('success', 'Registrasi berhasil. Selamat datang, ' . $pemohon->nama . '.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('persediaan')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('public.persediaan.index')
            ->with('success', 'Anda berhasil keluar.');
    }

    private function resolveUnit(string $slug): string
    {
        $unit = UnitKerja::fromSlug($slug);
        abort_unless($unit !== null, 404);

        return $unit;
    }
}
