<?php

namespace Modules\UserManagement\Service;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\UserManagement\Entities\VerificationMedia;
use Modules\UserManagement\Entities\VerificationSession;
use Modules\UserManagement\Jobs\VerifyDriverKycJob;
use Modules\UserManagement\Repository\VerificationMediaRepositoryInterface;
use Modules\UserManagement\Repository\VerificationSessionRepositoryInterface;
use Modules\UserManagement\Service\Interface\VerificationSessionServiceInterface;

class VerificationSessionService implements VerificationSessionServiceInterface
{
    protected VerificationSessionRepositoryInterface $sessionRepository;
    protected VerificationMediaRepositoryInterface $mediaRepository;
    protected array $config;

    public function __construct(
        VerificationSessionRepositoryInterface $sessionRepository,
        VerificationMediaRepositoryInterface $mediaRepository
    ) {
        $this->sessionRepository = $sessionRepository;
        $this->mediaRepository = $mediaRepository;
        $this->config = config('verification', []);
    }

    /**
     * Create a new verification session for a user.
     */
    public function createSession(string $userId, string $type = 'driver_kyc'): VerificationSession
    {
        return $this->sessionRepository->create([
            'id' => Str::uuid()->toString(),
            'user_id' => $userId,
            'type' => $type,
            'status' => VerificationSession::STATUS_UNVERIFIED,
            'decision' => VerificationSession::DECISION_PENDING,
        ]);
    }

    /**
     * Get or create an active session for a user.
     */
    public function getOrCreateSession(string $userId, string $type = 'driver_kyc'): VerificationSession
    {
        // Check for existing active session
        $existingSession = $this->sessionRepository->findActiveSessionForUser($userId, $type);
        
        if ($existingSession) {
            return $existingSession;
        }

        // Create new session
        return $this->createSession($userId, $type);
    }

    /**
     * Store uploaded media for a session.
     */
    public function storeMedia(string $sessionId, string $kind, UploadedFile $file): VerificationMedia
    {
        // Validate kind - combine document types with video kinds from config
        $validKinds = [
            VerificationMedia::KIND_SELFIE,
            VerificationMedia::KIND_ID_FRONT,
            VerificationMedia::KIND_ID_BACK,
        ];

        // Add video kinds from config
        $videoKinds = $this->config['video_kinds'] ?? [
            VerificationMedia::KIND_LIVENESS_VIDEO,
            VerificationMedia::KIND_LIVENESS_VIDEO_1,
            VerificationMedia::KIND_LIVENESS_VIDEO_2,
        ];
        $validKinds = array_merge($validKinds, $videoKinds);

        if (!in_array($kind, $validKinds)) {
            throw new \InvalidArgumentException("Invalid media kind: {$kind}");
        }

        // Validate file type
        $mime = $file->getMimeType();
        $allowedMimes = array_merge(
            $this->config['storage']['allowed_image_mimes'] ?? [],
            $this->config['storage']['allowed_video_mimes'] ?? []
        );

        if (!empty($allowedMimes) && !in_array($mime, $allowedMimes)) {
            throw new \InvalidArgumentException("Invalid file type: {$mime}");
        }

        // Validate file size
        $maxSize = $this->config['storage']['max_file_size'] ?? 10 * 1024 * 1024;
        if ($file->getSize() > $maxSize) {
            throw new \InvalidArgumentException("File too large. Maximum size: " . ($maxSize / 1024 / 1024) . "MB");
        }

        // Delete existing media of same kind
        $existingMedia = $this->mediaRepository->findBySessionAndKind($sessionId, $kind);
        if ($existingMedia) {
            $existingMedia->deleteFile();
            $this->mediaRepository->delete($existingMedia->id);
        }

        // Store file
        $disk = $this->config['storage']['disk'] ?? 'local';
        $pathPrefix = $this->config['storage']['path_prefix'] ?? 'verification';
        $extension = $file->getClientOriginalExtension();
        $filename = Str::uuid()->toString() . '.' . $extension;
        $path = "{$pathPrefix}/{$sessionId}/{$kind}/{$filename}";

        Storage::disk($disk)->put($path, file_get_contents($file->getRealPath()));

        // Calculate checksum
        $checksum = hash_file('sha256', $file->getRealPath());

        // Create media record
        return $this->mediaRepository->create([
            'session_id' => $sessionId,
            'kind' => $kind,
            'storage_disk' => $disk,
            'path' => $path,
            'mime' => $mime,
            'size' => $file->getSize(),
            'checksum' => $checksum,
        ]);
    }

    /**
     * Submit a session for processing.
     */
    public function submitSession(string $sessionId): void
    {
        $session = $this->sessionRepository->findOne($sessionId);

        if (!$session) {
            throw new \RuntimeException("Session not found: {$sessionId}");
        }

        if (!$session->canBeSubmitted()) {
            throw new \RuntimeException("Session cannot be submitted. Current status: {$session->status}");
        }

        // Check required media
        $requiredMedia = $this->config['required_media'][$session->type] ?? ['selfie', 'id_front'];
        if (!$this->mediaRepository->hasRequiredMedia($sessionId, $requiredMedia)) {
            throw new \RuntimeException("Missing required media. Required: " . implode(', ', $requiredMedia));
        }

        // Update session status
        $this->sessionRepository->update($sessionId, [
            'status' => VerificationSession::STATUS_PENDING,
            'submitted_at' => now(),
        ]);

        // Dispatch verification job
        VerifyDriverKycJob::dispatch($sessionId);
    }

    /**
     * Apply decision based on verification scores from FastAPI.
     */
    public function applyDecision(string $sessionId, array $pythonResponse): void
    {
        $session = $this->sessionRepository->findOne($sessionId);

        if (!$session) {
            throw new \RuntimeException("Session not found: {$sessionId}");
        }

        // Extract scores
        $scores = [
            'liveness_score' => $pythonResponse['liveness_quality'] ?? false ? 100 : 0,
            'face_match_score' => $pythonResponse['face_match_score'] ?? 0,
            'doc_auth_score' => $pythonResponse['doc_auth_score'] ?? 0,
            'extracted_fields' => $pythonResponse['doc_extracted_fields'] ?? null,
        ];

        // Determine decision based on thresholds
        $thresholds = $this->config['thresholds'] ?? [];
        $autoApprove = $thresholds['auto_approve'] ?? [];
        $manualReview = $thresholds['manual_review'] ?? [];

        $livenessQuality = $pythonResponse['liveness_quality'] ?? false;
        $faceMatchScore = $scores['face_match_score'];
        $docAuthScore = $scores['doc_auth_score'];

        $reasonCodes = $pythonResponse['reason_codes'] ?? [];

        // Decision logic
        if (
            $livenessQuality &&
            $faceMatchScore >= ($autoApprove['face_match'] ?? 85) &&
            $docAuthScore >= ($autoApprove['doc_auth'] ?? 75)
        ) {
            $decision = VerificationSession::DECISION_APPROVED;
        } elseif (
            $faceMatchScore >= ($manualReview['face_match'] ?? 60) &&
            $docAuthScore >= ($manualReview['doc_auth'] ?? 50)
        ) {
            $decision = VerificationSession::DECISION_MANUAL_REVIEW;
        } else {
            $decision = VerificationSession::DECISION_REJECTED;
        }

        // Update session with scores and decision
        $this->sessionRepository->updateWithScores($sessionId, $scores, $decision, $reasonCodes);

        // Update user's KYC status
        $this->updateUserKycStatus($session->user_id, $decision);

        // TODO: Send notification to user
        // $this->sendVerificationNotification($session, $decision);
    }

    /**
     * Update user's KYC status based on verification decision.
     */
    protected function updateUserKycStatus(string $userId, string $decision): void
    {
        $kycStatus = match($decision) {
            VerificationSession::DECISION_APPROVED => 'verified',
            VerificationSession::DECISION_REJECTED => 'rejected',
            VerificationSession::DECISION_MANUAL_REVIEW => 'pending',
            default => 'pending',
        };

        $updateData = ['kyc_status' => $kycStatus];

        if ($kycStatus === 'verified') {
            $updateData['kyc_verified_at'] = now();
            // Move to pending_approval when KYC is verified - admin must approve
            $updateData['onboarding_step'] = 'pending_approval';
            $updateData['onboarding_state'] = 'pending_approval';
        }

        \Modules\UserManagement\Entities\User::where('id', $userId)->update($updateData);
    }

    /**
     * Get current verification status for a user.
     */
    public function getStatusForUser(string $userId, string $type = 'driver_kyc'): ?VerificationSession
    {
        return $this->sessionRepository->findLatestByUserId($userId, $type);
    }

    /**
     * Get media signed URLs for a session.
     */
    public function getMediaSignedUrls(string $sessionId): array
    {
        $media = $this->mediaRepository->getBySession($sessionId);
        $urls = [];

        foreach ($media as $item) {
            $urls[$item->kind] = $item->getSignedUrl(
                $this->config['storage']['signed_url_expiry'] ?? 15
            );
        }

        return $urls;
    }
}
