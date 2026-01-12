<?php

return [
    'service_name' => env('OTEL_SERVICE_NAME', env('APP_NAME', 'laravel-service')),
];
