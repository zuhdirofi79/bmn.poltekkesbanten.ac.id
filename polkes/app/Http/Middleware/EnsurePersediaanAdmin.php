<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Persediaan\PersediaanAccess;
use App\Support\Persediaan\PersediaanAdminSession;
use App\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware Admin Persediaan — session khusus, tidak memakai guard web.
 */
class EnsurePersediaanAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! PersediaanAdminSession::isLoggedIn($request)) {
            return redirect()->route('persediaan.admin.login.show')
                ->with('error', 'Silakan login sebagai Admin Persediaan.');
        }

        $userId = PersediaanAdminSession::userId($request);
        if ($userId === null || ! PersediaanAccess::hasAdminAccess($userId)) {
            PersediaanAdminSession::logout($request);
            Auth::guard('persediaan_admin')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('persediaan.admin.login.show')
                ->with('error', 'Anda tidak memiliki akses admin persediaan.');
        }

        $admin = PersediaanAdminSession::activeAdmin($request);
        $request->attributes->set('persediaan_admin', $admin);

        if (! Auth::guard('persediaan_admin')->check()) {
            $user = User::query()->whereKey(PersediaanAdminSession::userId($request))->first();
            if ($user !== null) {
                Auth::guard('persediaan_admin')->login($user);
            }
        }

        return $next($request);
    }
}
