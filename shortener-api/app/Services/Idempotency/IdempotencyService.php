<?php

namespace App\Services\Idempotency;

use App\Models\IdempotencyKey;
use Illuminate\Cache\Lock;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;

class IdempotencyService
{
    public function handle(Request $request, callable $next): Response
    {
        $idempotencyKey = $request->header('Idempotency-Key');
        if (!$idempotencyKey) {
            /** @var Response $response */
            $response = $next($request);
            return $response;
        }

        $this->maybeCleanupExpired();
        $requestHash = $this->hashRequest($request);

        $existing = IdempotencyKey::where('key', $idempotencyKey)->first();
        if ($existing && $existing->expires_at->isPast()) {
            $existing->delete();
            $existing = null;
        }

        if ($existing) {
            if ($existing->request_hash !== $requestHash) {
                return response()->json([
                    'message' => 'Idempotency key reused with a different request payload.',
                ], 409);
            }

            if ($existing->response_body !== null && $existing->status_code !== null) {
                return response()->json($existing->response_body, $existing->status_code)
                    ->header('Idempotency-Key', $idempotencyKey);
            }
        }

        $lock = $this->acquireLock($idempotencyKey);
        if (!$lock) {
            return response()->json([
                'message' => 'Idempotency key is already being processed.',
            ], 409);
        }

        $record = IdempotencyKey::updateOrCreate(
            ['key' => $idempotencyKey],
            [
                'request_hash' => $requestHash,
                'locked_at' => now(),
                'expires_at' => now()->addSeconds(config('idempotency.ttl_seconds')),
            ]
        );

        try {
            /** @var Response $response */
            $response = $next($request);
        } finally {
            $lock->release();
        }

        $responseBody = $this->normalizeResponseBody($response);
        $record->fill([
            'response_body' => $responseBody,
            'status_code' => $response->getStatusCode(),
        ])->save();

        return $response->header('Idempotency-Key', $idempotencyKey);
    }

    private function hashRequest(Request $request): string
    {
        $payload = [
            'method' => $request->getMethod(),
            'path' => $request->path(),
            'query' => $request->query(),
            'body' => $request->all(),
        ];

        return hash('sha256', json_encode($payload));
    }

    private function normalizeResponseBody(Response $response): array
    {
        $contentType = (string) $response->headers->get('Content-Type');

        if (str_contains($contentType, 'application/json')) {
            $decoded = json_decode($response->getContent(), true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return ['raw' => $response->getContent()];
    }

    private function acquireLock(string $key): ?Lock
    {
        $lock = Cache::lock('idempotency:'.$key, config('idempotency.lock_ttl_seconds'));

        return $lock->get() ? $lock : null;
    }

    private function maybeCleanupExpired(): void
    {
        if (random_int(1, 100) !== 1) {
            return;
        }

        IdempotencyKey::where('expires_at', '<', Date::now())->delete();
    }
}
