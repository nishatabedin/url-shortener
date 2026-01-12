<?php

return [
    'ttl_seconds' => (int) env('IDEMPOTENCY_TTL_SECONDS', 86400),
    'lock_ttl_seconds' => (int) env('IDEMPOTENCY_LOCK_TTL_SECONDS', 30),
];
