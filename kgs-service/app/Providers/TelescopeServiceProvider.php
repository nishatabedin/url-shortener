<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\TelescopeApplicationServiceProvider;

class TelescopeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if (!config('telescope.enabled') || !class_exists(TelescopeApplicationServiceProvider::class)) {
            return;
        }

        $this->app->register(TelescopeApplicationServiceProvider::class);
    }

    public function boot(): void
    {
        if (!config('telescope.enabled') || !class_exists(TelescopeApplicationServiceProvider::class)) {
            return;
        }

        $provider = $this->app->getProvider(TelescopeApplicationServiceProvider::class);
        if ($provider) {
            $provider->boot();
        }

        Telescope::filter(function (IncomingEntry $entry) {
            return true;
        });
    }

    protected function gate(): void
    {
        Gate::define('viewTelescope', function ($user = null) {
            return app()->environment('local');
        });
    }
}
