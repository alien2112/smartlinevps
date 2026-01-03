<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Version extends Model
{
    protected $fillable = [
        'version',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the current active version
     *
     * @return string
     */
    public static function getCurrentVersion(): string
    {
        $version = self::where('is_active', true)
            ->orderBy('id', 'desc')
            ->first();

        return $version?->version ?? 'v1';
    }
}
