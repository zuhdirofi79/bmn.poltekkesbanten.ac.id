<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Persediaan\PersediaanAdmin;
use App\Support\Persediaan\PersediaanAdminSession;
use Closure;
use Illuminate\Http\Request;
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

        $admin = PersediaanAdminSession::activeAdmin($request);

        if ($admin === null) {
            PersediaanAdminSession::logout($request);
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('persediaan.admin.login.show')
                ->with('error', 'Akun ini tidak memiliki akses Admin Persediaan.');
        }

        $request->attributes->set('persediaan_admin', $admin);

        return $next($request);
    }
}
