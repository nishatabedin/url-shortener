<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

final class KgsClient
{
    public function reserveKey(): string
    {
        $baseUrl = rtrim(config('services.kgs.base_url'), '/');

        $resp = Http::timeout(2)->post($baseUrl.'/api/v1/keys/reserve');

        if (!$resp->ok() || !is_string($resp->json('key'))) {
            throw new \RuntimeException('KGS reserve failed: '.$resp->body());
        }

        return $resp->json('key');
    }
}
