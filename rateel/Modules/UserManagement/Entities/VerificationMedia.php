<?php

namespace Modules\UserManagement\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class VerificationMedia extends Model
{
    use HasFactory;

    protected $table = 'verification_media';

    protected $fillable = [
        'session_id',
        'kind',
        'storage_disk',
        'path',
        'mime',
        'size',
        'checksum',
    ];

    protected $casts = [
        'size' => 'integer',
    ];

    // Kind constants
    const KIND_SELFIE = 'selfie';
    const KIND_LIVENESS_VIDEO = 'liveness_video';
    const KIND_LIVENESS_VIDEO_1 = 'liveness_video_1';
    const KIND_LIVENESS_VIDEO_2 = 'liveness_video_2';
    const KIND_ID_FRONT = 'id_front';
    const KIND_ID_BACK = 'id_back';

    /**
     * Get the verification session this media belongs to.
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(VerificationSession::class, 'session_id');
    }

    /**
     * Get the full path to the file.
     */
    public function getFullPath(): string
    {
        return Storage::disk($this->storage_disk)->path($this->path);
    }

    /**
     * Get a temporary signed URL for accessing this media.
     * 
     * @param int $expirationMinutes URL expiration time in minutes
     * @return string
     */
    public function getSignedUrl(int $expirationMinutes = 15): string
    {
        $disk = Storage::disk($this->storage_disk);
        
        // For S3/R2, use native temporary URL
        if (method_exists($disk, 'temporaryUrl')) {
            try {
                return $disk->temporaryUrl(
                    $this->path,
                    now()->addMinutes($expirationMinutes)
                );
            } catch (\Exception $e) {
                // Fall back to signed route
            }
        }
        
        // For local storage, use signed Laravel route
        return URL::temporarySignedRoute(
            'verification.media.download',
            now()->addMinutes($expirationMinutes),
            ['media' => $this->id]
        );
    }

    /**
     * Get file contents.
     */
    public function getContents(): ?string
    {
        return Storage::disk($this->storage_disk)->get($this->path);
    }

    /**
     * Check if file exists.
     */
    public function fileExists(): bool
    {
        return Storage::disk($this->storage_disk)->exists($this->path);
    }

    /**
     * Delete the file from storage.
     */
    public function deleteFile(): bool
    {
        return Storage::disk($this->storage_disk)->delete($this->path);
    }

    /**
     * Get human-readable kind label.
     */
    public function getKindLabel(): string
    {
        return match($this->kind) {
            self::KIND_SELFIE => 'Selfie',
            self::KIND_LIVENESS_VIDEO => 'Liveness Video',
            self::KIND_ID_FRONT => 'ID Front',
            self::KIND_ID_BACK => 'ID Back',
            default => ucfirst($this->kind),
        };
    }

    /**
     * Check if this is an image.
     */
    public function isImage(): bool
    {
        return str_starts_with($this->mime, 'image/');
    }

    /**
     * Check if this is a video.
     */
    public function isVideo(): bool
    {
        return str_starts_with($this->mime, 'video/');
    }
}
