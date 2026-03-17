<?php

namespace App\Providers;

use App\Services\BhpNotificationService;
use Illuminate\Support\Collection;
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
        View::composer(['layouts.thema', 'layouts.thema_select2'], function ($view) {
            $notifications = new Collection();
            if (auth()->check()) {
                $service = app(BhpNotificationService::class);
                $notifications = $service->getNotifications(true);
            }
            $view->with('bhp_notifications', $notifications);
            $view->with('bhp_expired', $notifications->where('status', 'expired')->values());
            $view->with('bhp_upcoming', $notifications->where('status', 'not_expired')->values());
        });
    }
}
