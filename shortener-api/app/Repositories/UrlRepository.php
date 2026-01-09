<?php

namespace App\Repositories;

use App\Services\ShardManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class UrlRepository
{
    public function __construct(private readonly ShardManager $sm) {}

    public function create(string $hash, string $originalUrl, ?int $apiKeyId, ?\DateTimeInterface $expiresAt): void
    {
        $conn = $this->sm->connectionForHash($hash);

        DB::connection($conn)->table('urls')->insert([
            'hash' => $hash,
            'original_url' => $originalUrl,
            'api_key_id' => $apiKeyId,
            'expires_at' => $expiresAt,
            'clicks' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function findForRedirect(string $hash): ?array
    {
        // Cache-first (hot path)
        $cacheKey = "url:{$hash}";
        $cached = Cache::store('redis')->get($cacheKey);
        if (is_array($cached) && isset($cached['original_url'])) {
            return $cached;
        }

        $conn = $this->sm->connectionForHash($hash);
        $row = DB::connection($conn)->table('urls')->where('hash', $hash)->first();
        if (!$row) return null;

        $data = [
            'original_url' => $row->original_url,
            'expires_at' => $row->expires_at,
        ];

        // TTL: until expires_at, otherwise a default
        $ttlSeconds = 3600;
        if ($row->expires_at) {
            $ttlSeconds = max(1, now()->diffInSeconds($row->expires_at, false));
        }

        Cache::store('redis')->put($cacheKey, $data, $ttlSeconds);

        return $data;
    }

    public function incrementClicks(string $hash): void
    {
        $conn = $this->sm->connectionForHash($hash);
        DB::connection($conn)->table('urls')->where('hash', $hash)->increment('clicks');
    }

    public function deleteIfExpired(string $hash): void
    {
        $conn = $this->sm->connectionForHash($hash);
        DB::connection($conn)->table('urls')->where('hash', $hash)->delete();
        Cache::store('redis')->forget("url:{$hash}");
    }
}
