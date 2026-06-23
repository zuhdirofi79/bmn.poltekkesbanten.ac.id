<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\BhpKebersihan\BhpAccess;
use App\Support\BhpKebersihan\BhpAdminSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBhpAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! BhpAdminSession::isLoggedIn($request)) {
            return redirect()->route('bhp.admin.login.show')
                ->with('error', 'Silakan login sebagai Admin BHP Kebersihan.');
        }

        $userId = BhpAdminSession::userId($request);
        if ($userId === null || ! BhpAccess::hasAdminAccess($userId)) {
            BhpAdminSession::logout($request);
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('bhp.admin.login.show')
                ->with('error', 'Anda tidak memiliki akses admin BHP Kebersihan.');
        }

        $role = BhpAdminSession::syncRoleFromDatabase($request);
        $routeName = $request->route()?->getName();

        if (! BhpAccess::canAccessRoute($role, $routeName)) {
            abort(403, 'Anda tidak memiliki akses ke halaman ini.');
        }

        return $next($request);
    }
}
