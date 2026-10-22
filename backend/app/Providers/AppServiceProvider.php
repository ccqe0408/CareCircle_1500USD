<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('carecircle-login', fn ($request) => [
            Limit::perMinute(6)->by('login-account:'.strtolower((string) $request->input('email')).':'.$request->ip()),
            Limit::perMinute(30)->by('login-ip:'.$request->ip()),
        ]);
        RateLimiter::for('carecircle-register', fn ($request) => Limit::perMinute(5)->by('register:'.$request->ip()));

        RateLimiter::for('handover-issue', fn ($request) => Limit::perMinute(6)->by('issue:'.$request->user()?->id));
        RateLimiter::for('handover-confirm', fn ($request) => Limit::perMinute(10)->by('confirm:'.$request->user()?->id));
    }
}
