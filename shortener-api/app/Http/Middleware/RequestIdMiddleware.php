<?php

namespace App\Http\Middleware;

use App\Observability\TraceContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class RequestIdMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $request->header('X-Request-Id', (string) Str::uuid());
        $request->attributes->set('request_id', $requestId);

        $context = ['request_id' => $requestId];
        $traceId = TraceContext::currentTraceId();
        if ($traceId) {
            $context['trace_id'] = $traceId;
        }

        Log::withContext($context);

        $response = $next($request);
        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }
}
