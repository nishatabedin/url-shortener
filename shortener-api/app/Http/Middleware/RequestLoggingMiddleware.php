<?php

namespace App\Http\Middleware;

use App\Observability\TraceContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class RequestLoggingMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $start = microtime(true);

        $response = $next($request);

        $durationMs = (int) round((microtime(true) - $start) * 1000);
        $route = $request->route();
        $context = [
            'method' => $request->getMethod(),
            'path' => '/'.ltrim($request->path(), '/'),
            'route' => $route?->uri(),
            'status' => $response->getStatusCode(),
            'duration_ms' => $durationMs,
            'request_id' => $request->attributes->get('request_id'),
        ];

        $traceId = TraceContext::currentTraceId();
        if ($traceId) {
            $context['trace_id'] = $traceId;
        }

        Log::info('http_request', $context);

        return $response;
    }
}
