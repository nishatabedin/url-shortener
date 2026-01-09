<?php

use App\Services\KgsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/v1/keys/reserve', function (KgsService $kgs) {
    // The shortener service calls this. Keep it private at network layer in real deploy.
    return response()->json(['key' => $kgs->reserveOne()]);
});

Route::post('/v1/keys/ensure-pool', function (KgsService $kgs) {
    $kgs->ensurePool();
    return response()->json(['ok' => true]);
})->middleware('kgs.admin');
