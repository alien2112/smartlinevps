<?php

namespace Modules\UserManagement\Service\Interface;

use Illuminate\Http\UploadedFile;
use Modules\UserManagement\Entities\VerificationMedia;
use Modules\UserManagement\Entities\VerificationSession;

interface VerificationSessionServiceInterface
{
    /**
     * Create a new verification session for a user.
     */
    public function createSession(string $userId, string $type = 'driver_kyc'): VerificationSession;

    /**
     * Get or create an active session for a user.
     */
    public function getOrCreateSession(string $userId, string $type = 'driver_kyc'): VerificationSession;

    /**
     * Store uploaded media for a session.
     */
    public function storeMedia(string $sessionId, string $kind, UploadedFile $file): VerificationMedia;

    /**
     * Submit a session for processing.
     */
    public function submitSession(string $sessionId): void;

    /**
     * Apply decision based on verification scores from FastAPI.
     */
    public function applyDecision(string $sessionId, array $pythonResponse): void;

    /**
     * Get current verification status for a user.
     */
    public function getStatusForUser(string $userId, string $type = 'driver_kyc'): ?VerificationSession;

    /**
     * Get media signed URLs for a session.
     */
    public function getMediaSignedUrls(string $sessionId): array;
}
