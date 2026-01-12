<?php

namespace App\Providers;

use App\Observability\Metrics\PrometheusMetrics;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

class ObservabilityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PrometheusMetrics::class, fn () => new PrometheusMetrics());
    }

    public function boot(): void
    {
        $metrics = $this->app->make(PrometheusMetrics::class);

        DB::listen(function (QueryExecuted $query) use ($metrics): void {
            $metrics->observeDbQuery($query->connectionName, $query->time / 1000);
        });
    }
}
