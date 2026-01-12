<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\TelescopeApplicationServiceProvider;

class TelescopeServiceProvider extends TelescopeApplicationServiceProvider
{
    public function register(): void
    {
        if (!config('telescope.enabled')) {
            return;
        }

        parent::register();
    }

    public function boot(): void
    {
        if (!config('telescope.enabled')) {
            return;
        }

        parent::boot();

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
