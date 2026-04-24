<?php

namespace App\Services;

use App\BarangBHP_bahan;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

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
     *
     * @return Collection
     */
    public function getNotifications(bool $useCache = true): Collection
    {
        try {
            if (config('app.disable_bhp_notifications', false)) {
                return collect();
            }
            if (app()->runningInConsole()) {
                return collect();
            }
            if (! auth()->check()) {
                return collect();
            }

            $jurusan = auth()->user()->jurusan ?? null;
            $cacheKey = ($jurusan !== null && $jurusan !== '') ? self::CACHE_KEY_PREFIX . $jurusan : self::CACHE_KEY_ALL;

            if ($useCache === true && Cache::has($cacheKey)) {
                $cache = Cache::get($cacheKey);
                $data = $this->extractCachedData($cache);
                if ($data->isEmpty()) {
                    Log::warning('BHP_NOTIFICATION_FALLBACK_USED', [
                        'jurusan' => $jurusan,
                        'user_id' => auth()->id(),
                        'method' => 'getNotifications',
                        'reason' => 'cache_exists_but_data_empty',
                    ]);
                }
                return $data;
            }

            $data = $this->fetchNotifications($jurusan);
            $data = $this->normalizeCachedNotifications($data);

            Cache::put($cacheKey, [
                'data' => $data->values()->all(),
                'cached_at' => now(),
            ], self::CACHE_TTL);

            return $data;
        } catch (\Throwable $e) {
            Log::error('BHP_NOTIFICATION_ERROR', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'jurusan' => $jurusan,
                'user_id' => auth()->id(),
                'method' => 'getNotifications',
            ]);

            $jurusan = auth()->check() ? (auth()->user()->jurusan ?? null) : null;
            $cacheKey = ($jurusan !== null && $jurusan !== '') ? self::CACHE_KEY_PREFIX . $jurusan : self::CACHE_KEY_ALL;

            if (Cache::has($cacheKey)) {
                $cache = Cache::get($cacheKey);
                $data = $this->extractCachedData($cache);
                $cachedAt = $this->extractCachedAt($cache);
                $data = $this->addCacheAge($data, $cachedAt);

                Log::warning('BHP_NOTIFICATION_FALLBACK_USED', [
                    'jurusan' => $jurusan,
                    'user_id' => auth()->id(),
                    'method' => 'getNotifications',
                    'reason' => 'exception_fallback_to_cache',
                    'cache_age_minutes' => $this->resolveCacheAgeMinutes($cachedAt),
                ]);

                return $data;
            }

            return collect();
        }
    }

    /**
     * @param  mixed  $value
     */
    protected function normalizeCachedNotifications($value): Collection
    {
        if ($value instanceof Collection) {
            return $value;
        }

        return collect($value ?? []);
    }

    /**
     * @param mixed $cache
     */
    protected function extractCachedData($cache): Collection
    {
        if ($cache instanceof Collection) {
            return $cache;
        }

        if (is_array($cache) && array_key_exists('data', $cache)) {
            return collect($cache['data'] ?? []);
        }

        return collect($cache ?? []);
    }

    /**
     * @param mixed $cache
     * @return mixed
     */
    protected function extractCachedAt($cache)
    {
        if (is_array($cache) && array_key_exists('cached_at', $cache)) {
            return $cache['cached_at'];
        }

        return null;
    }

    /**
     * @param mixed $cachedAt
     */
    protected function addCacheAge(Collection $data, $cachedAt): Collection
    {
        $minutesOld = $this->resolveCacheAgeMinutes($cachedAt);
        if ($minutesOld === null) {
            return $data;
        }

        return $data->map(function ($item) use ($minutesOld) {
            if (!is_array($item)) {
                return $item;
            }
            $item['cache_age'] = $minutesOld;
            return $item;
        })->values();
    }

    /**
     * @param mixed $cachedAt
     */
    protected function resolveCacheAgeMinutes($cachedAt): ?int
    {
        if ($cachedAt === null) {
            return null;
        }

        try {
            return now()->diffInMinutes(Carbon::parse($cachedAt));
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Query and transform; no cache.
     * Expired: last 30 days (< today, >= today-30). Upcoming: next 90 days (today..today+90).
     *
     * @param  string|null  $jurusan  null = all jurusan
     */
    protected function fetchNotifications(?string $jurusan): Collection
    {
        $timezone = config('app.timezone', 'UTC');
        $today = Carbon::today($timezone);

        $expiredFrom = $today->copy()->subDays(30);
        $upcomingTo = $today->copy()->addDays(90);

        $todayStr = $today->format('Y-m-d');
        $expiredFromStr = $expiredFrom->format('Y-m-d');
        $upcomingToStr = $upcomingTo->format('Y-m-d');

        $baseQuery = function () use ($jurusan) {
            $q = BarangBHP_bahan::aktif()
                ->whereNotNull('tgl_expired')
                ->select('nama', 'kimia', 'stok', 'tgl_expired', 'jurusan');
            if ($jurusan !== null) {
                $q->where('jurusan', $jurusan);
            }

            return $q;
        };

        $expiredItems = $baseQuery()
            ->where('tgl_expired', '<', $todayStr)
            ->where('tgl_expired', '>=', $expiredFromStr)
            ->orderBy('tgl_expired')
            ->limit(self::LIMIT_EXPIRED)
            ->get();

        $upcomingItems = $baseQuery()
            ->whereBetween('tgl_expired', [$todayStr, $upcomingToStr])
            ->orderBy('tgl_expired')
            ->limit(self::LIMIT_UPCOMING)
            ->get();

        $mapItem = function ($item) use ($today, $timezone) {
            $raw = $item->tgl_expired ?? null;
            if ($raw === null || $raw === '') {
                return null;
            }

            $dateStr = substr(preg_replace('/\s+/', '', (string) $raw), 0, 10);
            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStr)) {
                return null;
            }

            $parts = array_map('intval', explode('-', $dateStr));
            if (count($parts) !== 3 || ! checkdate($parts[1], $parts[2], $parts[0])) {
                return null;
            }

            $expiredDate = Carbon::create($parts[0], $parts[1], $parts[2], 0, 0, 0, $timezone)->startOfDay();

            $sisaDays = (int) $today->diffInDays($expiredDate, false);
            $sisaHari = max($sisaDays, 0);
            $status = $expiredDate->lt($today) ? 'expired' : 'not_expired';

            $stok = (isset($item->stok) && is_numeric($item->stok)) ? (int) $item->stok : 0;

            return [
                'nama' => $item->nama ?? '-',
                'merk' => $item->kimia ?? '-',
                'stok' => $stok,
                'tgl_expired' => $item->tgl_expired ?? '-',
                'sisa_hari' => $sisaHari,
                'status' => $status,
                'jurusan' => $item->jurusan ?? '',
            ];
        };

        $expiredMapped = $expiredItems->map($mapItem)->filter()->values();
        $upcomingMapped = $upcomingItems->map($mapItem)->filter()->values();

        $all = $expiredMapped->concat($upcomingMapped);

        return $this->groupByNamaTglJurusan($all);
    }

    /**
     * Group by (nama, tgl_expired, jurusan), sum stok. One notif per group.
     */
    protected function groupByNamaTglJurusan(Collection $items): Collection
    {
        $grouped = $items->groupBy(function ($item) {
            if (! is_array($item)) {
                return uniqid('bhp_bad_', true);
            }
            $nama = (string) ($item['nama'] ?? '');
            $tgl = (string) ($item['tgl_expired'] ?? '');
            $jur = (string) ($item['jurusan'] ?? '');

            return $nama . '|' . $tgl . '|' . $jur;
        });

        return $grouped->map(function ($group) {
            $first = $group->first();
            if (! is_array($first)) {
                return null;
            }
            $totalstok = $group->sum('stok');
            $first['stok'] = $totalstok;

            return $first;
        })->filter()->values();
    }

    /**
     * Clear cache for one jurusan (and "all"). Call when that jurusan's data changes.
     */
    public static function clearCacheByJurusan(?string $jurusan): void
    {
        try {
            if ($jurusan !== null && $jurusan !== '') {
                Cache::forget(self::CACHE_KEY_PREFIX . $jurusan);
            }
            Cache::forget(self::CACHE_KEY_ALL);
        } catch (\Throwable $e) {
            Log::error('BHP_NOTIFICATION_ERROR', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'context' => 'clearCacheByJurusan',
            ]);
        }
    }
}
