<?php

namespace App\Services\Media;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

/**
 * Service for secure file uploads to private R2 storage.
 * 
 * Handles file validation, secure object key generation, and storage.
 * All files are stored with non-guessable UUID-based names.
 */
class SecureMediaUploader
{
    private string $disk;
    private array $allowedMimeTypes;
    private array $maxFileSizes;

    public function __construct()
    {
        $this->disk = config('media.disk', 'public');
        $this->allowedMimeTypes = config('media.allowed_mime_types', []);
        $this->maxFileSizes = config('media.max_file_sizes', []);
    }

    /**
     * Upload file to secure storage with proper object key structure.
     *
     * @param UploadedFile $file The uploaded file
     * @param string $category    Category (e.g., "driver", "customer", "vehicle")
     * @param string $subCategory Sub-category (e.g., "profile", "identity", "document")
     * @param string|int $ownerId User/entity ID for path prefix
     * @return string             Object key (to be stored in DB)
     * @throws InvalidArgumentException|RuntimeException
     */
    public function upload(UploadedFile $file, string $category, string $subCategory, string|int $ownerId): string
    {
        // Validate the file
        $this->validateFile($file, $subCategory);

        // Get file extension
        $extension = $this->getSecureExtension($file);

        // Generate secure object key
        $objectKey = $this->generateObjectKey($category, $subCategory, (string) $ownerId, $extension);

        // Get file contents
        $contents = file_get_contents($file->getRealPath());
        if ($contents === false) {
            throw new RuntimeException('Failed to read file contents');
        }

        // Store the file
        $stored = Storage::disk($this->disk)->put($objectKey, $contents, [
            'visibility' => 'private',
            'ContentType' => $file->getMimeType(),
        ]);

        if (!$stored) {
            throw new RuntimeException('Failed to store file');
        }

        return $objectKey;
    }

    /**
     * Upload file from a path or URL.
     *
     * @param string $filePath    Path to file or URL
     * @param string $category    Category
     * @param string $subCategory Sub-category
     * @param string|int $ownerId Owner ID
     * @param string $extension   File extension
     * @param string|null $mimeType MIME type (optional)
     * @return string Object key
     */
    public function uploadFromPath(string $filePath, string $category, string $subCategory, string|int $ownerId, string $extension, ?string $mimeType = null): string
    {
        $contents = file_get_contents($filePath);
        if ($contents === false) {
            throw new RuntimeException('Failed to read file from path: ' . $filePath);
        }

        // Generate secure object key
        $objectKey = $this->generateObjectKey($category, $subCategory, (string) $ownerId, $extension);

        // Store the file
        $options = ['visibility' => 'private'];
        if ($mimeType) {
            $options['ContentType'] = $mimeType;
        }

        $stored = Storage::disk($this->disk)->put($objectKey, $contents, $options);

        if (!$stored) {
            throw new RuntimeException('Failed to store file');
        }

        return $objectKey;
    }

    /**
     * Delete an object from storage.
     *
     * @param string $objectKey Object key to delete
     * @return bool Success status
     */
    public function delete(string $objectKey): bool
    {
        if (empty($objectKey)) {
            return false;
        }

        try {
            return Storage::disk($this->disk)->delete($objectKey);
        } catch (\Exception $e) {
            \Log::warning('Failed to delete media object', [
                'object_key' => $objectKey,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Delete multiple objects.
     *
     * @param array $objectKeys Array of object keys
     * @return int Number of successfully deleted objects
     */
    public function deleteBatch(array $objectKeys): int
    {
        $deleted = 0;
        foreach ($objectKeys as $objectKey) {
            if ($this->delete($objectKey)) {
                $deleted++;
            }
        }
        return $deleted;
    }

    /**
     * Check if an object exists.
     *
     * @param string $objectKey Object key
     * @return bool
     */
    public function exists(string $objectKey): bool
    {
        if (empty($objectKey)) {
            return false;
        }

        return Storage::disk($this->disk)->exists($objectKey);
    }

    /**
     * Validate file against category constraints.
     *
     * @throws InvalidArgumentException
     */
    private function validateFile(UploadedFile $file, string $subCategory): void
    {
        // Map subcategory to validation category
        $categoryMap = [
            'profile' => 'profile',
            'identity' => 'kyc',
            'kyc' => 'kyc',
            'license' => 'document',
            'vehicle' => 'vehicle',
            'document' => 'document',
            'record' => 'document',
            'car' => 'vehicle',
            'receipt' => 'receipt',
            'evidence' => 'evidence',
        ];

        $validationCategory = $categoryMap[$subCategory] ?? 'document';

        // Check MIME type
        $mimeType = $file->getMimeType();
        $allowedTypes = $this->allowedMimeTypes[$validationCategory] ?? [];
        
        if (!empty($allowedTypes) && !in_array($mimeType, $allowedTypes, true)) {
            throw new InvalidArgumentException(
                "File type '{$mimeType}' is not allowed for category '{$subCategory}'. " .
                "Allowed types: " . implode(', ', $allowedTypes)
            );
        }

        // Check file size
        $maxSize = $this->maxFileSizes[$validationCategory] ?? (10 * 1024 * 1024);
        $fileSize = $file->getSize();

        if ($fileSize > $maxSize) {
            $maxSizeMB = round($maxSize / (1024 * 1024), 2);
            $fileSizeMB = round($fileSize / (1024 * 1024), 2);
            throw new InvalidArgumentException(
                "File size ({$fileSizeMB}MB) exceeds maximum allowed size ({$maxSizeMB}MB) for category '{$subCategory}'"
            );
        }
    }

    /**
     * Generate secure object key.
     * 
     * Format: {category}/{ownerId}/{subCategory}/{uuid}.{ext}
     */
    private function generateObjectKey(string $category, string $subCategory, string $ownerId, string $extension): string
    {
        // Sanitize inputs
        $category = $this->sanitizePathSegment($category);
        $subCategory = $this->sanitizePathSegment($subCategory);
        $ownerId = $this->sanitizePathSegment($ownerId);
        $extension = $this->sanitizeExtension($extension);

        // Generate UUID for filename
        $uuid = Str::uuid()->toString();

        return "{$category}/{$ownerId}/{$subCategory}/{$uuid}.{$extension}";
    }

    /**
     * Get secure file extension from uploaded file.
     */
    private function getSecureExtension(UploadedFile $file): string
    {
        // Prefer guessing from MIME type for security
        $mimeType = $file->getMimeType();
        $mimeToExt = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'application/pdf' => 'pdf',
            'video/mp4' => 'mp4',
        ];

        if (isset($mimeToExt[$mimeType])) {
            return $mimeToExt[$mimeType];
        }

        // Fallback to original extension (sanitized)
        $ext = $file->getClientOriginalExtension();
        return $this->sanitizeExtension($ext);
    }

    /**
     * Sanitize path segment to prevent path traversal.
     */
    private function sanitizePathSegment(string $segment): string
    {
        // Remove any path separators and dangerous characters
        $segment = preg_replace('/[\/\\\\\.]+/', '', $segment);
        
        // Limit length
        if (strlen($segment) > 100) {
            $segment = substr($segment, 0, 100);
        }

        return $segment;
    }

    /**
     * Sanitize file extension.
     */
    private function sanitizeExtension(string $extension): string
    {
        // Only allow alphanumeric characters
        $extension = preg_replace('/[^a-zA-Z0-9]/', '', $extension);
        
        // Lowercase
        $extension = strtolower($extension);
        
        // Limit length
        if (strlen($extension) > 10) {
            $extension = substr($extension, 0, 10);
        }

        return $extension ?: 'bin';
    }

    /**
     * Get the current storage disk name.
     */
    public function getDisk(): string
    {
        return $this->disk;
    }

    /**
     * Set the storage disk (useful for testing).
     */
    public function setDisk(string $disk): void
    {
        $this->disk = $disk;
    }
}
