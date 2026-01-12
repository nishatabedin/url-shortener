<?php

use App\Http\Controllers\Observability\MetricsController;
use App\Http\Controllers\RedirectController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Route;
use Throwable;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/metrics', MetricsController::class);

Route::get('/healthz', function () {
    return response()->json(['status' => 'ok']);
});

Route::get('/readyz', function () {
    $checks = ['mysql' => 'ok', 'redis' => 'ok'];
    $ok = true;

    try {
        DB::connection()->getPdo();
    } catch (Throwable $exception) {
        $checks['mysql'] = 'error';
        $ok = false;
    }

    try {
        Redis::connection()->ping();
    } catch (Throwable $exception) {
        $checks['redis'] = 'error';
        $ok = false;
    }

    return response()->json([
        'status' => $ok ? 'ok' : 'degraded',
        'checks' => $checks,
    ], $ok ? 200 : 503);
});

Route::middleware('throttle:redirect')->get('/{hash}', [RedirectController::class, 'go'])
    ->where('hash', '[0-9A-Za-z]+');
