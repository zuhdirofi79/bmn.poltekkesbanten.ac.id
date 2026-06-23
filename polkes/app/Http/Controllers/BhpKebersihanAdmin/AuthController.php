<?php

declare(strict_types=1);

namespace App\Http\Controllers\BhpKebersihanAdmin;

use App\Http\Controllers\Controller;
use App\Support\BhpKebersihan\BhpAdminSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLoginForm(Request $request): View|RedirectResponse
    {
        if (BhpAdminSession::isLoggedIn($request)) {
            return redirect()->route('bhp.admin.dashboard');
        }

        return view('bhp_kebersihan_admin.auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $request->validate([
            'username' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string'],
        ]);

        $pair = BhpAdminSession::attemptCredentials(
            (string) $request->input('username'),
            (string) $request->input('password'),
        );

        if ($pair === null) {
            throw ValidationException::withMessages([
                'username' => 'Username atau password salah, atau akun tidak memiliki akses Admin BHP Kebersihan.',
            ]);
        }

        BhpAdminSession::login($request, $pair['admin'], $pair['user']);

        $role = BhpAdminSession::role($request);
        if ($role === 'admin_bhp_pengambilan') {
            return redirect()->route('bhp.admin.pengambilan.index');
        }

        return redirect()->intended(route('bhp.admin.dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        BhpAdminSession::logout($request);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('bhp.admin.login.show')
            ->with('success', 'Anda berhasil keluar dari Admin BHP Kebersihan.');
    }
}
