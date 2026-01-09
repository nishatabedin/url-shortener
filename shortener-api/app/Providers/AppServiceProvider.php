<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

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
        RateLimiter::for('shorten', function (Request $request) {
            $apiKeyId = (string) ($request->attributes->get('api_key_id') ?? 'guest');
            return [
                Limit::perMinute(60)->by('shorten:'.$apiKeyId), // create endpoint
            ];
        });

        RateLimiter::for('redirect', function (Request $request) {
            // Redirects can be huge; rate-limit softly by IP (optional)
            return [
                Limit::perMinute(600)->by('redir:'.$request->ip()),
            ];
        });
    }
}
