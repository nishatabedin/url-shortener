<?php

namespace App\Http\Middleware;

use App\Observability\Metrics\PrometheusMetrics;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PrometheusMetricsMiddleware
{
    public function __construct(private readonly PrometheusMetrics $metrics)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $start = microtime(true);

        /** @var Response $response */
        $response = $next($request);

        $path = $request->route()?->uri() ?? $request->path();
        $route = $path === 'metrics' ? 'metrics' : $path;
        $duration = microtime(true) - $start;

        if ($route !== 'metrics') {
            $this->metrics->observeRequest(
                $request->getMethod(),
                $route,
                $response->getStatusCode(),
                $duration
            );
        }

        return $response;
    }
}
