<?php

declare(strict_types=1);

namespace App\Services\BhpKebersihan;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NomorPermintaanService
{
    public function generate(?int $year = null): string
    {
        $year = $year ?? (int) date('Y');
        $prefix = sprintf('BHP-%d-', $year);

        $last = DB::table('bhp_kebersihan_permintaan')
            ->where('nomor_permintaan', 'like', $prefix . '%')
            ->lockForUpdate()
            ->orderBy('nomor_permintaan', 'desc')
            ->value('nomor_permintaan');

        $next = 1;
        if ($last !== null) {
            $suffix = substr((string) $last, strlen($prefix));
            $next = ((int) $suffix) + 1;
        }

        return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
