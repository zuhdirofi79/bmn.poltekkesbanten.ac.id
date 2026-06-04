<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Memastikan request berasal dari pemohon Persediaan yang sudah login
 * (guard 'persediaan'). Terpisah penuh dari guard 'web'.
 */
class EnsurePersediaanPemohon
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::guard('persediaan')->guest()) {
            return redirect()
                ->route('public.persediaan.index')
                ->with('error', 'Silakan login sebagai pemohon Persediaan terlebih dahulu.');
        }

        return $next($request);
    }
}
