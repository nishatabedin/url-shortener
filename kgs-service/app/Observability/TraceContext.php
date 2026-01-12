<?php

namespace App\Observability;

use OpenTelemetry\API\Trace\Span;

class TraceContext
{
    public static function currentTraceId(): ?string
    {
        if (!class_exists(Span::class)) {
            return null;
        }

        $span = Span::getCurrent();
        $context = $span->getContext();

        if (!$context->isValid()) {
            return null;
        }

        return $context->getTraceId();
    }
}
