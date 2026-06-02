<?php

declare(strict_types=1);

namespace App\Http\Controllers\SewaAdmin;

use App\Http\Controllers\Controller;
use App\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Login/Logout TERPISAH untuk modul Admin Sewa BMN.
 *
 * Memakai guard 'web' (auth Laravel existing) tetapi UI + route + endpoint
 * di prefix /sewa-admin sehingga tidak ber-tumpang-tindih dengan login admin
 * BMN utama (yang ada di /login). Hanya 3 user real (id 3240/3241/3242 dengan
 * username yang sesuai) yang diizinkan masuk; selain itu request ditolak
 * tanpa membuat session login.
 */
class SewaAdminAuthController extends Controller
{
    public function showLoginForm(): View
    {
        return view('sewa_admin.auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $request->validate([
            'username' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string'],
        ]);

        $username = (string) $request->input('username');
        $password = (string) $request->input('password');

        // Pre-check: pastikan username termasuk whitelist Admin Sewa.
        if (! self::isWhitelistedUsername($username)) {
            throw ValidationException::withMessages([
                'username' => 'Akun ini tidak memiliki akses Admin Sewa BMN.',
            ]);
        }

        $credentials = [
            'username' => $username,
            'password' => $password,
        ];

        if (! Auth::guard('web')->attempt($credentials, (bool) $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'username' => 'Username atau password salah.',
            ]);
        }

        /** @var User|null $user */
        $user = Auth::guard('web')->user();

        if ($user === null || ! $user->canManageSewaBmn() || ! self::matchIdUsername($user)) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'username' => 'Akun ini tidak memiliki akses Admin Sewa BMN.',
            ]);
        }

        $request->session()->regenerate();
        $request->session()->put('sewa_admin.signed_in', true);

        return redirect()->intended(route('sewa-admin.dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('sewa-admin.login.show')
            ->with('success', 'Anda berhasil keluar dari Admin Sewa BMN.');
    }

    private static function isWhitelistedUsername(string $username): bool
    {
        return in_array($username, [
            User::SEWA_ADMIN_INDAH_USERNAME,
            User::SEWA_ADMIN_RETNO_USERNAME,
            User::SEWA_ADMIN_SINGGIH_USERNAME,
        ], true);
    }

    private static function matchIdUsername(User $user): bool
    {
        return match ((int) $user->id) {
            User::SEWA_ADMIN_INDAH_ID => $user->username === User::SEWA_ADMIN_INDAH_USERNAME,
            User::SEWA_ADMIN_RETNO_ID => $user->username === User::SEWA_ADMIN_RETNO_USERNAME,
            User::SEWA_ADMIN_SINGGIH_ID => $user->username === User::SEWA_ADMIN_SINGGIH_USERNAME,
            default => false,
        };
    }
}
