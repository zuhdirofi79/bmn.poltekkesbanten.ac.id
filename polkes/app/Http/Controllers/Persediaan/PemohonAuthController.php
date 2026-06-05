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
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Login/Register pemohon Persediaan (guard 'persediaan', login via nama/username).
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
        $unitName = $this->resolveUnit($unit);

        $request->validate([
            'login'    => ['required', 'string', 'max:150'],
            'password' => ['required', 'string'],
        ]);

        $login = (string) $request->input('login');
        $password = (string) $request->input('password');

        $pemohon = $this->findPemohonForLogin($unitName, $login);

        if ($pemohon === null || ! Hash::check($password, $pemohon->password)) {
            throw ValidationException::withMessages([
                'login' => 'Nama/username atau password salah.',
            ]);
        }

        Auth::guard('persediaan')->login($pemohon, (bool) $request->boolean('remember'));
        $request->session()->regenerate();

        return redirect()->route('public.persediaan.permintaan.create');
    }

    public function showRegister(string $unit): View
    {
        $unitName = $this->resolveUnit($unit);

        return view('persediaan.auth.register', [
            'unitSlug' => $unit,
            'unitName' => $unitName,
            'units'    => UnitKerja::forPublic(),
        ]);
    }

    public function register(Request $request, string $unit): RedirectResponse
    {
        $unitName = $this->resolveUnit($unit);

        $data = $request->validate([
            'nama_lengkap' => ['required', 'string', 'max:255'],
            'jabatan'      => ['required', 'string', 'max:255'],
            'password'     => ['required', 'string', 'min:6', 'confirmed'],
            'unit_kerja'   => ['required', Rule::in(UnitKerja::forPublic())],
            'nip_nidn_nik' => ['nullable', 'string', 'max:100', Rule::unique('persediaan_pemohon', 'nip')],
            'email'        => ['nullable', 'email', 'max:255'],
            'nomor_wa'     => ['nullable', 'string', 'max:30'],
        ]);

        $pemohon = PersediaanPemohon::create([
            'nama'       => $data['nama_lengkap'],
            'nip'        => $this->nullableString($data['nip_nidn_nik'] ?? null),
            'email'      => $this->nullableString($data['email'] ?? null),
            'nomor_wa'   => $this->nullableString($data['nomor_wa'] ?? null),
            'jabatan'    => $data['jabatan'],
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

    private function findPemohonForLogin(string $unitKerja, string $login): ?PersediaanPemohon
    {
        $query = PersediaanPemohon::query()
            ->where('unit_kerja', $unitKerja)
            ->where(function ($q) use ($login): void {
                $q->where('nama', $login);

                if (Schema::hasColumn('persediaan_pemohon', 'username')) {
                    $q->orWhere('username', $login);
                }
            });

        return $query->first();
    }

    private function nullableString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
