<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class ApiKeyAuth
{
    public function handle(Request $request, Closure $next)
    {
        $key = $request->header('X-API-Key');
        if (!$key) {
            return response()->json(['message' => 'X-API-Key required'], 401);
        }

        $row = DB::connection('mysql_primary')
            ->table('api_keys')
            ->where('key', $key)
            ->where('is_active', true)
            ->first();

        if (!$row) {
            return response()->json(['message' => 'Invalid API key'], 401);
        }

        $request->attributes->set('api_key_id', $row->id);
        return $next($request);
    }
}
