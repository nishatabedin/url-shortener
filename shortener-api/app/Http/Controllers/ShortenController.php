<?php

namespace App\Http\Controllers;

use App\Repositories\UrlRepository;
use App\Services\KgsClient;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

final class ShortenController extends Controller
{
    public function store(Request $request, KgsClient $kgs, UrlRepository $repo)
    {
        $data = $request->validate([
            'original_url' => ['required', 'url', 'max:5000'],
            'expires_in_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
        ]);

        $hash = $kgs->reserveKey();

        $expiresDays = $data['expires_in_days'] ?? (int) env('DEFAULT_TTL_DAYS', 365);
        $expiresAt = Carbon::now()->addDays($expiresDays);

        $apiKeyId = (int) $request->attributes->get('api_key_id');

        $repo->create($hash, $data['original_url'], $apiKeyId, $expiresAt);

        // Also warm cache immediately for fast first redirect
        cache()->store('redis')->put("url:{$hash}", [
            'original_url' => $data['original_url'],
            'expires_at' => $expiresAt->toDateTimeString(),
        ], $expiresAt);

        $shortUrl = rtrim(env('SHORT_DOMAIN', $request->getSchemeAndHttpHost()), '/') . '/' . $hash;

        return response()->json([
            'hash' => $hash,
            'short_url' => $shortUrl,
            'expires_at' => $expiresAt->toIso8601String(),
        ], 201);
    }
}
