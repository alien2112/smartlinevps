<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IssueReport extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'report_number',
        'driver_id',
        'trip_id',
        'issue_type',
        'description',
        'attachments',
        'severity',
        'status',
        'investigated_at',
        'resolved_at',
        'resolution_notes',
    ];

    protected $casts = [
        'attachments' => 'array',
        'investigated_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    const TYPE_CUSTOMER_BEHAVIOR = 'customer_behavior';
    const TYPE_APP_MALFUNCTION = 'app_malfunction';
    const TYPE_PAYMENT_ISSUE = 'payment_issue';
    const TYPE_SAFETY_CONCERN = 'safety_concern';
    const TYPE_OTHER = 'other';

    const SEVERITY_LOW = 'low';
    const SEVERITY_MEDIUM = 'medium';
    const SEVERITY_HIGH = 'high';
    const SEVERITY_CRITICAL = 'critical';

    const STATUS_REPORTED = 'reported';
    const STATUS_INVESTIGATING = 'investigating';
    const STATUS_RESOLVED = 'resolved';
    const STATUS_CLOSED = 'closed';

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($report) {
            if (!$report->report_number) {
                $report->report_number = self::generateReportNumber();
            }
        });
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(\Modules\UserManagement\Entities\User::class, 'driver_id');
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(\Modules\TripManagement\Entities\TripRequest::class, 'trip_id');
    }

    public static function generateReportNumber(): string
    {
        do {
            $number = 'RPT-' . strtoupper(uniqid());
        } while (self::where('report_number', $number)->exists());

        return $number;
    }

    public function markAsInvestigating(): void
    {
        $this->update([
            'status' => self::STATUS_INVESTIGATING,
            'investigated_at' => now(),
        ]);
    }

    public function markAsResolved(?string $notes = null): void
    {
        $this->update([
            'status' => self::STATUS_RESOLVED,
            'resolved_at' => now(),
            'resolution_notes' => $notes,
        ]);
    }
}
