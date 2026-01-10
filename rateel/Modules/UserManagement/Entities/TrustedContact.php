<?php

namespace Modules\UserManagement\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TrustedContact extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'name',
        'phone',
        'relationship',
        'is_active',
        'priority',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'priority' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationship constants
    const RELATIONSHIP_FAMILY = 'family';
    const RELATIONSHIP_FRIEND = 'friend';
    const RELATIONSHIP_COLLEAGUE = 'colleague';
    const RELATIONSHIP_OTHER = 'other';

    /**
     * Get the user that owns the contact
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get trip shares for this contact
     */
    public function tripShares()
    {
        return $this->hasMany(TripShare::class, 'shared_with_contact_id');
    }

    /**
     * Scope to get active contacts
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to order by priority
     */
    public function scopeByPriority($query)
    {
        return $query->orderBy('priority', 'asc');
    }
}
