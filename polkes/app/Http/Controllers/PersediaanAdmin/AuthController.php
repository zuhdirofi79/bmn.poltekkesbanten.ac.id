<?php

declare(strict_types=1);

namespace App\Http\Controllers\PersediaanAdmin;

use App\Http\Controllers\Controller;
use App\Support\Persediaan\PersediaanAdminSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Login/Logout Admin Persediaan — session custom, terpisah dari /login (admin BMN).
 */
class AuthController extends Controller
{
    public function showLoginForm(Request $request): View|RedirectResponse
    {
        if (PersediaanAdminSession::isLoggedIn($request)) {
            return redirect()->route('persediaan.admin.dashboard');
        }

        return view('persediaan_admin.auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $request->validate([
            'username' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string'],
        ]);

        $pair = PersediaanAdminSession::attemptCredentials(
            (string) $request->input('username'),
            (string) $request->input('password'),
        );

        if ($pair === null) {
            throw ValidationException::withMessages([
                'username' => 'Username atau password salah, atau akun tidak memiliki akses Admin Persediaan.',
            ]);
        }

        PersediaanAdminSession::login($request, $pair['admin'], $pair['user']);
        Auth::guard('persediaan_admin')->login($pair['user']);

        return redirect()->intended(route('persediaan.admin.dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        PersediaanAdminSession::logout($request);
        Auth::guard('persediaan_admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('persediaan.admin.login.show')
            ->with('success', 'Anda berhasil keluar dari Admin Persediaan.');
    }
}
