<?php

namespace Modules\UserManagement\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * DriverDocument Model
 * 
 * Stores driver documents uploaded during the onboarding process.
 * Supports multiple document types: ID (front/back), license, car photo, selfie.
 */
class DriverDocument extends Model
{
    use HasFactory, SoftDeletes;

    // Document type constants
    public const TYPE_ID_FRONT = 'id_front';
    public const TYPE_ID_BACK = 'id_back';
    public const TYPE_LICENSE_FRONT = 'license_front';
    public const TYPE_LICENSE_BACK = 'license_back';
    public const TYPE_CAR_FRONT = 'car_front';
    public const TYPE_CAR_BACK = 'car_back';
    public const TYPE_SELFIE = 'selfie';

    // Required documents for onboarding completion
    public const REQUIRED_DOCUMENTS = [
        self::TYPE_ID_FRONT,
        self::TYPE_ID_BACK,
        self::TYPE_LICENSE_FRONT,
        self::TYPE_LICENSE_BACK,
        self::TYPE_CAR_FRONT,
        self::TYPE_CAR_BACK,
        self::TYPE_SELFIE,
    ];

    // Document types by vehicle type
    public const DOCUMENTS_BY_VEHICLE_TYPE = [
        'car' => [
            self::TYPE_ID_FRONT,
            self::TYPE_ID_BACK,
            self::TYPE_LICENSE_FRONT,
            self::TYPE_LICENSE_BACK,
            self::TYPE_CAR_FRONT,
            self::TYPE_CAR_BACK,
            self::TYPE_SELFIE,
        ],
        'taxi' => [
            self::TYPE_ID_FRONT,
            self::TYPE_ID_BACK,
            self::TYPE_LICENSE_FRONT,
            self::TYPE_LICENSE_BACK,
            self::TYPE_CAR_FRONT,
            self::TYPE_CAR_BACK,
            self::TYPE_SELFIE,
        ],
        'motor_bike' => [
            self::TYPE_ID_FRONT,
            self::TYPE_ID_BACK,
            self::TYPE_LICENSE_FRONT,
            self::TYPE_LICENSE_BACK,
            self::TYPE_SELFIE,
        ],
        'scooter' => [
            self::TYPE_ID_FRONT,
            self::TYPE_ID_BACK,
            self::TYPE_LICENSE_FRONT,
            self::TYPE_LICENSE_BACK,
            self::TYPE_SELFIE,
        ],
    ];

    protected $table = 'driver_documents';

    protected $fillable = [
        'driver_id',
        'type',
        'file_path',
        'original_name',
        'mime_type',
        'file_size',
        'verified',
        'verified_at',
        'verified_by',
        'rejection_reason',
        'metadata',
    ];

    protected $casts = [
        'verified' => 'boolean',
        'verified_at' => 'datetime',
        'file_size' => 'integer',
        'metadata' => 'array',
    ];

    protected $hidden = [
        'file_path', // Hide actual file path from API responses
    ];

    protected $appends = [
        'file_url',
        'status',
        'uploaded_at',
    ];
    
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($model) {
            $model->uuid = (string) Str::uuid();
        });
    }

    /**
     * Relationship to the driver (User)
     */
    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    /**
     * Relationship to the user who verified the document
     */
    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * Get the document file URL (signed URL for security)
     */
    public function getFileUrlAttribute(): ?string
    {
        if (!$this->file_path) {
            return null;
        }

        // Use getMediaUrl helper to match vehicle image format
        return getMediaUrl($this->file_path, 'driver/document');
    }

    /**
     * Get document status for API response
     */
    public function getStatusAttribute(): string
    {
        if ($this->verified) {
            return 'verified';
        }
        
        if ($this->rejection_reason) {
            return 'rejected';
        }
        
        return 'pending';
    }

    /**
     * Get uploaded_at timestamp (uses created_at)
     */
    public function getUploadedAtAttribute(): ?string
    {
        return $this->created_at?->toDateTimeString();
    }

    /**
     * Mark document as verified
     */
    public function markAsVerified(string $verifierId): bool
    {
        $this->verified = true;
        $this->verified_at = now();
        $this->verified_by = $verifierId;
        $this->rejection_reason = null;
        
        return $this->save();
    }

    /**
     * Mark document as rejected
     */
    public function markAsRejected(string $verifierId, string $reason): bool
    {
        $this->verified = false;
        $this->verified_at = null;
        $this->verified_by = $verifierId;
        $this->rejection_reason = $reason;
        
        return $this->save();
    }
    
    /**
     * Check if a document type is valid
     */
    public static function isValidType(string $type): bool
    {
        return in_array($type, self::REQUIRED_DOCUMENTS);
    }
    
    /**
     * Get required documents based on vehicle type
     * Returns an associative array with type => display name
     * Supports both old and new document type naming conventions
     */
    public static function getRequiredDocuments(?string $vehicleType = null): array
    {
        // New document types (actually used in database)
        $newTypes = [
            'national_id' => 'National ID',
            'driving_license' => 'Driving License',
            'vehicle_registration' => 'Vehicle Registration',
            'vehicle_photo' => 'Vehicle Photo',
            'profile_photo' => 'Profile Photo',
        ];
        
        // Old document types (for backward compatibility)
        $oldTypes = [
            'id_front' => 'ID Front',
            'id_back' => 'ID Back',
            'license_front' => 'License Front',
            'license_back' => 'License Back',
            'car_front' => 'Car Front',
            'car_back' => 'Car Back',
            'selfie' => 'Selfie',
        ];
        
        // Merge both to support old and new types
        return array_merge($newTypes, $oldTypes);
    }

    /**
     * Get human readable name for document type
     */
    public static function getTypeName(string $type): string
    {
        return ucwords(str_replace('_', ' ', $type));
    }

    /**
     * Scope for verified documents
     */
    public function scopeVerified($query)
    {
        return $query->where('verified', true);
    }

    /**
     * Scope for pending documents
     */
    public function scopePending($query)
    {
        return $query->where('verified', false)->whereNull('rejection_reason');
    }

    /**
     * Scope for rejected documents
     */
    public function scopeRejected($query)
    {
        return $query->where('verified', false)->whereNotNull('rejection_reason');
    }
    
    /**
     * Scope by type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    protected static function newFactory()
    {
        return \Modules\UserManagement\Database\factories\DriverDocumentFactory::new();
    }
}
