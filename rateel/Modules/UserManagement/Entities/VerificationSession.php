<?php

namespace Modules\UserManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class VerificationSession extends Model
{
    use HasFactory, HasUuid;

    protected $table = 'verification_sessions';
    
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'user_id',
        'type',
        'status',
        'provider',
        'liveness_score',
        'face_match_score',
        'doc_auth_score',
        'extracted_fields',
        'decision',
        'decision_reason_codes',
        'reviewed_by_admin_id',
        'admin_notes',
        'submitted_at',
        'processed_at',
        'reviewed_at',
    ];

    protected $casts = [
        'extracted_fields' => 'array',
        'decision_reason_codes' => 'array',
        'liveness_score' => 'decimal:2',
        'face_match_score' => 'decimal:2',
        'doc_auth_score' => 'decimal:2',
        'submitted_at' => 'datetime',
        'processed_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    // Status constants
    const STATUS_UNVERIFIED = 'unverified';
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_VERIFIED = 'verified';
    const STATUS_REJECTED = 'rejected';
    const STATUS_MANUAL_REVIEW = 'manual_review';
    const STATUS_EXPIRED = 'expired';

    // Decision constants
    const DECISION_PENDING = 'pending';
    const DECISION_APPROVED = 'approved';
    const DECISION_REJECTED = 'rejected';
    const DECISION_MANUAL_REVIEW = 'manual_review';

    // Type constants
    const TYPE_DRIVER_KYC = 'driver_kyc';
    const TYPE_CUSTOMER_KYC = 'customer_kyc';

    /**
     * Get the user that owns this verification session.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the admin who reviewed this session.
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_admin_id');
    }

    /**
     * Get all media files for this session.
     */
    public function media(): HasMany
    {
        return $this->hasMany(VerificationMedia::class, 'session_id');
    }

    /**
     * Scope for pending review sessions.
     */
    public function scopePendingReview($query)
    {
        return $query->where('decision', self::DECISION_MANUAL_REVIEW)
                     ->where('status', self::STATUS_MANUAL_REVIEW);
    }

    /**
     * Scope for driver KYC sessions.
     */
    public function scopeForDriver($query)
    {
        return $query->where('type', self::TYPE_DRIVER_KYC);
    }

    /**
     * Scope by status.
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope for user's sessions.
     */
    public function scopeForUser($query, string $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Check if session is already processed (idempotency check).
     */
    public function isAlreadyProcessed(): bool
    {
        return in_array($this->status, [
            self::STATUS_VERIFIED,
            self::STATUS_REJECTED,
            self::STATUS_MANUAL_REVIEW,
        ]);
    }

    /**
     * Check if session can be submitted.
     */
    public function canBeSubmitted(): bool
    {
        return $this->status === self::STATUS_UNVERIFIED;
    }

    /**
     * Get selfie media.
     */
    public function getSelfie(): ?VerificationMedia
    {
        return $this->media()->where('kind', VerificationMedia::KIND_SELFIE)->first();
    }

    /**
     * Get ID front media.
     */
    public function getIdFront(): ?VerificationMedia
    {
        return $this->media()->where('kind', VerificationMedia::KIND_ID_FRONT)->first();
    }

    /**
     * Get ID back media.
     */
    public function getIdBack(): ?VerificationMedia
    {
        return $this->media()->where('kind', VerificationMedia::KIND_ID_BACK)->first();
    }

    /**
     * Get liveness video media.
     */
    public function getLivenessVideo(): ?VerificationMedia
    {
        return $this->media()->where('kind', VerificationMedia::KIND_LIVENESS_VIDEO)->first();
    }
}
