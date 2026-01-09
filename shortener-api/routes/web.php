<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RedirectController;

Route::get('/', function () {
    return view('welcome');
});
Route::middleware('throttle:redirect')->get('/{hash}', [RedirectController::class, 'go'])
    ->where('hash', '[0-9A-Za-z]+');