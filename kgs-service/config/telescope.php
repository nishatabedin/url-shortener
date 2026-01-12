<?php

return [
    'enabled' => (bool) env('TELESCOPE_ENABLED', false),

    'domain' => env('TELESCOPE_DOMAIN'),

    'path' => env('TELESCOPE_PATH', 'telescope'),

    'driver' => env('TELESCOPE_DRIVER', 'database'),

    'storage' => [
        'database' => [
            'connection' => env('DB_CONNECTION', 'mysql'),
            'chunk' => 1000,
        ],
    ],

    'queue' => [
        'connection' => env('TELESCOPE_QUEUE_CONNECTION', 'redis'),
        'queue' => env('TELESCOPE_QUEUE', 'default'),
    ],

    'middleware' => [
        'web',
        Laravel\Telescope\Http\Middleware\Authorize::class,
    ],

    'only_paths' => [
        //
    ],

    'ignore_paths' => [
        'nova-api*',
    ],
];
