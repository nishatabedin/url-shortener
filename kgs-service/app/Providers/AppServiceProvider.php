<?php

namespace App\Providers;

use App\Services\KgsService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
         $this->app->singleton(KgsService::class, function () {
            return new KgsService(
                config('kgs.pool_key'),
                config('kgs.pool_min'),
                config('kgs.pool_target'),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
