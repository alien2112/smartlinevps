<?php

namespace Modules\UserManagement\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SupportTicket extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'ticket_number',
        'driver_id',
        'user_id',
        'user_type',
        'subject',
        'description',
        'message',
        'category',
        'priority',
        'status',
        'trip_id',
        'admin_response',
        'responded_at',
        'responded_by',
        'driver_reply',
        'replied_at',
        'rating',
        'rating_feedback',
        'rated_at',
    ];

    protected $casts = [
        'responded_at' => 'datetime',
        'replied_at' => 'datetime',
        'rated_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Status constants
    const STATUS_OPEN = 'open';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_RESOLVED = 'resolved';
    const STATUS_CLOSED = 'closed';

    // Category constants
    const CATEGORY_PAYMENT = 'payment';
    const CATEGORY_TRIP = 'trip';
    const CATEGORY_ACCOUNT = 'account';
    const CATEGORY_OTHER = 'other';

    // Priority constants
    const PRIORITY_LOW = 'low';
    const PRIORITY_NORMAL = 'normal';
    const PRIORITY_HIGH = 'high';
    const PRIORITY_URGENT = 'urgent';

    /**
     * Get the user that owns the ticket
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the trip associated with the ticket
     */
    public function trip()
    {
        return $this->belongsTo(\Modules\TripManagement\Entities\TripRequest::class, 'trip_id');
    }

    /**
     * Get the admin who responded to the ticket
     */
    public function responder()
    {
        return $this->belongsTo(User::class, 'responded_by');
    }

    /**
     * Scope to filter by status
     */
    public function scopeStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to filter by user type
     */
    public function scopeUserType($query, $userType)
    {
        return $query->where('user_type', $userType);
    }

    /**
     * Scope to filter by category
     */
    public function scopeCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Check if ticket is open
     */
    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    /**
     * Check if ticket is resolved
     */
    public function isResolved(): bool
    {
        return in_array($this->status, [self::STATUS_RESOLVED, self::STATUS_CLOSED]);
    }

    /**
     * Generate a unique ticket number
     */
    public static function generateTicketNumber(): string
    {
        $date = date('Ymd');
        $random = strtoupper(substr(uniqid(mt_rand(), true), -6));
        return "TKT-{$date}-{$random}";
    }
}
