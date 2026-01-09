<?php

use App\Http\Controllers\ShortenController;
use Illuminate\Support\Facades\Route;

Route::middleware(['apikey', 'throttle:shorten'])->group(function () {
    Route::post('/v1/shorten', [ShortenController::class, 'store']);
});
