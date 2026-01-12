<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IdempotencyKey extends Model
{
    protected $fillable = [
        'key',
        'request_hash',
        'response_body',
        'status_code',
        'locked_at',
        'expires_at',
    ];

    protected $casts = [
        'response_body' => 'array',
        'locked_at' => 'datetime',
        'expires_at' => 'datetime',
    ];
}
