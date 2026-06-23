<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware terpisah untuk modul Admin Sewa BMN.
 *
 * Hanya 3 user yang diizinkan (validasi id + username sesuai DB real):
 *   - Bu Indah   : id 3241, username Indah_Tryana
 *   - Bu Retno   : id 3242, username Retno_Pintoko
 *   - Pak Singgih: id 3240, username 199705042025211037
 *
 * Selain itu => redirect ke /sewa-admin/login dengan pesan tolak.
 * Tidak memengaruhi guard 'auth' / middleware role:admin BMN existing.
 */
class EnsureSewaAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('sewa-admin.login.show')
                ->with('error', 'Silakan login sebagai Admin Sewa BMN.');
        }

        /** @var User $user */
        if (! $user->canManageSewaBmn()) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('sewa-admin.login.show')
                ->with('error', 'Akun ini tidak memiliki akses Admin Sewa BMN.');
        }

        // Validasi tambahan: username harus cocok untuk masing-masing id (superadmin dikecualikan).
        if ($user->isSewaSuperadmin()) {
            if ($user->username !== User::SEWA_SUPERADMIN_USERNAME) {
                auth()->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('sewa-admin.login.show')
                    ->with('error', 'Akun ini tidak memiliki akses Admin Sewa BMN.');
            }

            return $next($request);
        }

        $idUsernameOk = match ((int) $user->id) {
            User::SEWA_ADMIN_INDAH_ID => $user->username === User::SEWA_ADMIN_INDAH_USERNAME,
            User::SEWA_ADMIN_RETNO_ID => $user->username === User::SEWA_ADMIN_RETNO_USERNAME,
            User::SEWA_ADMIN_SINGGIH_ID => $user->username === User::SEWA_ADMIN_SINGGIH_USERNAME,
            default => false,
        };

        if (! $idUsernameOk) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('sewa-admin.login.show')
                ->with('error', 'Akun ini tidak memiliki akses Admin Sewa BMN.');
        }

        return $next($request);
    }
}
