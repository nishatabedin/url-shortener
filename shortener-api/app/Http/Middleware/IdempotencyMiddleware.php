<?php

namespace App\Http\Middleware;

use App\Services\Idempotency\IdempotencyService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IdempotencyMiddleware
{
    public function __construct(private readonly IdempotencyService $service)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        return $this->service->handle($request, $next);
    }
}
