<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IdempotencyKey extends Model
{
    use HasFactory;

    protected $fillable = [
        'idempotency_key',
        'user_id',
        'endpoint',
        'request_payload',
        'response_payload',
        'status_code',
        'expires_at',
    ];

    protected $casts = [
        'request_payload' => 'array',
        'response_payload' => 'array',
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
    ];
}
