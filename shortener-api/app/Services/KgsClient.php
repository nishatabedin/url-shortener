<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use OpenTelemetry\API\Globals;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Context\Propagation\ArrayAccessGetterSetter;

final class KgsClient
{
    public function reserveKey(): string
    {
        $baseUrl = rtrim(config('services.kgs.base_url'), '/');
        $headers = $this->traceHeaders();

        $resp = Http::timeout(2)
            ->withHeaders($headers)
            ->post($baseUrl.'/api/v1/keys/reserve');

        if (!$resp->ok() || !is_string($resp->json('key'))) {
            throw new \RuntimeException('KGS reserve failed: '.$resp->body());
        }

        return $resp->json('key');
    }

    private function traceHeaders(): array
    {
        if (!class_exists(Globals::class) || !class_exists(ArrayAccessGetterSetter::class)) {
            return [];
        }

        $headers = [];
        Globals::propagator()->inject(
            $headers,
            ArrayAccessGetterSetter::getInstance(),
            Context::getCurrent()
        );

        return $headers;
    }
}
