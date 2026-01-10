<?php

namespace Modules\TripManagement\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class TripShare extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'trip_id',
        'driver_id',
        'shared_with_contact_id',
        'share_token',
        'share_method',
        'is_active',
        'expires_at',
        'last_accessed_at',
        'access_count',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'expires_at' => 'datetime',
        'last_accessed_at' => 'datetime',
        'access_count' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Share method constants
    const METHOD_SMS = 'sms';
    const METHOD_WHATSAPP = 'whatsapp';
    const METHOD_LINK = 'link';
    const METHOD_AUTO = 'auto';

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->share_token)) {
                $model->share_token = Str::random(32);
            }
        });
    }

    /**
     * Get the trip being shared
     */
    public function trip()
    {
        return $this->belongsTo(TripRequest::class, 'trip_id');
    }

    /**
     * Get the driver sharing the trip
     */
    public function driver()
    {
        return $this->belongsTo(\Modules\UserManagement\Entities\User::class, 'driver_id');
    }

    /**
     * Get the trusted contact this was shared with
     */
    public function trustedContact()
    {
        return $this->belongsTo(\Modules\UserManagement\Entities\TrustedContact::class, 'shared_with_contact_id');
    }

    /**
     * Scope to get active shares
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            });
    }

    /**
     * Check if share is expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Increment access count
     */
    public function recordAccess(): void
    {
        $this->increment('access_count');
        $this->update(['last_accessed_at' => now()]);
    }

    /**
     * Get shareable URL
     */
    public function getShareUrl(): string
    {
        return url("/track-trip/{$this->share_token}");
    }
}
