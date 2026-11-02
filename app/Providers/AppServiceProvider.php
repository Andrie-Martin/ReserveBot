<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
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
        // Phase 9: how often someone can ask for password links, set a password or open email links
        // (routes/web.php: ->middleware('throttle:...')). Each limit counts on its own.
        RateLimiter::for('forgot-password', fn (Request $request) => Limit::perMinute(5)->by('forgot|'.$request->ip()));
        RateLimiter::for('reset-password', fn (Request $request) => Limit::perMinute(10)->by('reset|'.$request->ip()));
        RateLimiter::for('email-links', fn (Request $request) => Limit::perMinute(20)->by('links|'.$request->ip()));
    }
}
