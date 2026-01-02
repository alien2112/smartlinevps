<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppFeedback extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'app_feedback';

    protected $fillable = [
        'driver_id',
        'type',
        'subject',
        'message',
        'rating',
        'screen_name',
        'metadata',
        'status',
        'admin_response',
        'reviewed_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'rating' => 'integer',
        'reviewed_at' => 'datetime',
    ];

    const TYPE_FEATURE_REQUEST = 'feature_request';
    const TYPE_BUG_REPORT = 'bug_report';
    const TYPE_GENERAL_FEEDBACK = 'general_feedback';
    const TYPE_COMPLAINT = 'complaint';

    const STATUS_PENDING = 'pending';
    const STATUS_REVIEWED = 'reviewed';
    const STATUS_IMPLEMENTED = 'implemented';
    const STATUS_REJECTED = 'rejected';

    public function driver(): BelongsTo
    {
        return $this->belongsTo(\Modules\UserManagement\Entities\User::class, 'driver_id');
    }

    public function markAsReviewed(?string $response = null): void
    {
        $this->update([
            'status' => self::STATUS_REVIEWED,
            'admin_response' => $response,
            'reviewed_at' => now(),
        ]);
    }
}
