<?php

namespace App\Providers;

use App\Models\BMN\AsetBmn;
use App\Observers\BMN\AsetBmnObserver;
use App\Services\BhpNotificationService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        AsetBmn::observe(AsetBmnObserver::class);

        View::composer(['layouts.thema', 'layouts.thema_select2'], function ($view) {
            if (! auth()->check()) {
                $view->with('bhp_notifications', collect());
                $view->with('bhp_expired', collect());
                $view->with('bhp_upcoming', collect());

                return;
            }

            $jurusan = auth()->user()->jurusan ?? null;
            $cacheKey = ($jurusan !== null && $jurusan !== '')
                ? BhpNotificationService::CACHE_KEY_PREFIX . $jurusan
                : BhpNotificationService::CACHE_KEY_ALL;

            if (Cache::has($cacheKey)) {
                $cache = Cache::get($cacheKey);
                if ($cache instanceof Collection) {
                    $notifications = $cache;
                } elseif (is_array($cache) && array_key_exists('data', $cache)) {
                    $notifications = collect($cache['data'] ?? []);
                } else {
                    $notifications = collect($cache ?? []);
                }
            } else {
                $notifications = app(BhpNotificationService::class)->getNotifications(true);
            }

            if (! $notifications instanceof Collection) {
                $notifications = collect($notifications ?? []);
            }

            $view->with('bhp_notifications', $notifications);
            $view->with('bhp_expired', $notifications->where('status', 'expired')->values());
            $view->with('bhp_upcoming', $notifications->where('status', 'not_expired')->values());
        });
    }
}
