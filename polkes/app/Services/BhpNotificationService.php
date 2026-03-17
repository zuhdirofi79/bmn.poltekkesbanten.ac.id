<?php

namespace App\Services;

use App\BarangBHP_bahan;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class BhpNotificationService
{
    /** Cache key for "all jurusan" (user has no jurusan). */
    const CACHE_KEY_ALL = 'bhp_notifications_all';

    /** Cache key prefix; suffix = jurusan when user has jurusan. */
    const CACHE_KEY_PREFIX = 'bhp_notifications_';

    /** Cache TTL in seconds (5 minutes). */
    const CACHE_TTL = 300;

    /** Max expired items in dropdown. */
    const LIMIT_EXPIRED = 10;

    /** Max upcoming items in dropdown. */
    const LIMIT_UPCOMING = 10;

    /**
     * Get BHP notifications for the current user.
     * User with jurusan → only that jurusan; no jurusan → all (explicit key).
     */
    public function getNotifications(bool $useCache = true): Collection
    {
        if (app()->runningInConsole()) {
            return collect();
        }
        if (!auth()->check()) {
            return collect();
        }

        $jurusan = auth()->user()->jurusan ?? null;
        $cacheKey = ($jurusan !== null && $jurusan !== '') ? self::CACHE_KEY_PREFIX . $jurusan : self::CACHE_KEY_ALL;

        if ($useCache) {
            try {
                return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($jurusan) {
                    return $this->fetchNotifications($jurusan);
                });
            } catch (\Throwable $e) {
                return $this->fetchNotifications($jurusan);
            }
        }

        return $this->fetchNotifications($jurusan);
    }

    /**
     * Query and transform; no cache.
     * Strategy: expired (limit 10) first, then upcoming (limit 10). Group by nama+tgl_expired+jurusan, sum stok.
     *
     * @param string|null $jurusan null = all jurusan
     */
    protected function fetchNotifications(?string $jurusan): Collection
    {
        $timezone = config('app.timezone', 'UTC');
        $today = Carbon::today($timezone);
        $end = Carbon::today($timezone)->addDays(30);
        $todayStr = $today->format('Y-m-d');
        $endStr = $end->format('Y-m-d');

        $baseQuery = function () use ($jurusan) {
            $q = BarangBHP_bahan::whereNull('delete')
                ->whereNotNull('tgl_expired')
                ->select('nama', 'kimia', 'stok', 'tgl_expired', 'jurusan');
            if ($jurusan !== null) {
                $q->where('jurusan', $jurusan);
            }
            return $q;
        };

        $expiredItems = $baseQuery()
            ->where('tgl_expired', '<', $todayStr)
            ->orderBy('tgl_expired')
            ->limit(self::LIMIT_EXPIRED)
            ->get();

        $upcomingItems = $baseQuery()
            ->whereBetween('tgl_expired', [$todayStr, $endStr])
            ->orderBy('tgl_expired')
            ->limit(self::LIMIT_UPCOMING)
            ->get();

        $mapItem = function ($item) use ($today, $timezone) {
            $expired = Carbon::parse($item->tgl_expired, $timezone);
            $sisa = (int) $today->diffInDays($expired, false);
            $sisaHari = max($sisa, 0);
            $status = $sisa < 0 ? 'expired' : 'not_expired';
            $stok = isset($item->stok) && is_numeric($item->stok) ? (int) $item->stok : 0;
            return [
                'nama'        => $item->nama ?? '-',
                'merk'        => $item->merk ?? $item->kimia ?? '-',
                'stok'      => $stok,
                'tgl_expired' => $item->tgl_expired ?? '-',
                'sisa_hari'   => $sisaHari,
                'status'      => $status,
                'jurusan'     => $item->jurusan ?? '',
            ];
        };

        $all = $expiredItems->map($mapItem)->concat($upcomingItems->map($mapItem));

        return $this->groupByNamaTglJurusan($all);
    }

    /**
     * Group by (nama, tgl_expired, jurusan), sum stok. One notif per group.
     */
    protected function groupByNamaTglJurusan(Collection $items): Collection
    {
        $grouped = $items->groupBy(function ($item) {
            return $item['nama'] . '|' . $item['tgl_expired'] . '|' . $item['jurusan'];
        });

        return $grouped->map(function ($group) {
            $first = $group->first();
            $totalstok = $group->sum('stok');
            $first['stok'] = $totalstok;
            return $first;
        })->values();
    }

    /**
     * Clear cache for one jurusan (and "all"). Call when that jurusan's data changes.
     */
    public static function clearCacheByJurusan(?string $jurusan): void
    {
        if ($jurusan !== null && $jurusan !== '') {
            Cache::forget(self::CACHE_KEY_PREFIX . $jurusan);
        }
        Cache::forget(self::CACHE_KEY_ALL);
    }
}
