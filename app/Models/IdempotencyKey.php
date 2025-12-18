<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IdempotencyKey extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'key',
        'resource_type',
        'resource_id',
        'request_hash',
        'response_code',
        'response_body',
    ];

    protected $casts = [
        'response_body' => 'array',
        'created_at' => 'datetime',
    ];

    public const UPDATED_AT = null;

    public static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (!$model->id) {
                $model->id = (string) \Illuminate\Support\Str::uuid();
            }
        });
    }
}
