<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportTicket extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'ticket_number',
        'driver_id',
        'subject',
        'description',
        'category',
        'priority',
        'status',
        'assigned_to',
        'resolved_at',
        'closed_at',
        'resolution_note',
        'rating',
        'rating_comment',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
        'rating' => 'integer',
    ];

    const STATUS_OPEN = 'open';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_RESOLVED = 'resolved';
    const STATUS_CLOSED = 'closed';

    const PRIORITY_LOW = 'low';
    const PRIORITY_NORMAL = 'normal';
    const PRIORITY_HIGH = 'high';
    const PRIORITY_URGENT = 'urgent';

    const CATEGORY_TECHNICAL = 'technical';
    const CATEGORY_ACCOUNT = 'account';
    const CATEGORY_PAYMENT = 'payment';
    const CATEGORY_TRIP_ISSUE = 'trip_issue';
    const CATEGORY_OTHER = 'other';

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($ticket) {
            if (!$ticket->ticket_number) {
                $ticket->ticket_number = self::generateTicketNumber();
            }
        });
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(\Modules\UserManagement\Entities\User::class, 'driver_id');
    }

    public function assignedAgent(): BelongsTo
    {
        return $this->belongsTo(\Modules\UserManagement\Entities\User::class, 'assigned_to');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportTicketMessage::class, 'ticket_id');
    }

    public function latestMessage()
    {
        return $this->hasOne(SupportTicketMessage::class, 'ticket_id')->latestOfMany();
    }

    public static function generateTicketNumber(): string
    {
        do {
            $number = 'TKT-' . strtoupper(uniqid());
        } while (self::where('ticket_number', $number)->exists());

        return $number;
    }

    public function markAsResolved(?string $note = null): void
    {
        $this->update([
            'status' => self::STATUS_RESOLVED,
            'resolved_at' => now(),
            'resolution_note' => $note,
        ]);
    }

    public function markAsClosed(): void
    {
        $this->update([
            'status' => self::STATUS_CLOSED,
            'closed_at' => now(),
        ]);
    }

    public function rateSupport(int $rating, ?string $comment = null): void
    {
        $this->update([
            'rating' => $rating,
            'rating_comment' => $comment,
        ]);
    }
}
