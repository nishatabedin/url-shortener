<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

final class KgsAdminAuth
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->header('X-KGS-Admin');
        if (!$token || $token !== config('kgs.admin_token')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        return $next($request);
    }
}
