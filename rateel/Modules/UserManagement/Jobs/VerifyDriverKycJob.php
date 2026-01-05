<?php

namespace Modules\UserManagement\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\UserManagement\Entities\VerificationSession;
use Modules\UserManagement\Repository\VerificationSessionRepositoryInterface;
use Modules\UserManagement\Service\Interface\FastApiClientServiceInterface;
use Modules\UserManagement\Service\Interface\VerificationSessionServiceInterface;
use Throwable;

class VerifyDriverKycJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 60;

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     */
    public int $maxExceptions = 2;

    /**
     * The session ID to process.
     */
    protected string $sessionId;

    /**
     * Create a new job instance.
     */
    public function __construct(string $sessionId)
    {
        $this->sessionId = $sessionId;
        $this->onQueue('verification'); // Use dedicated queue for verification jobs
    }

    /**
     * Execute the job.
     */
    public function handle(
        VerificationSessionRepositoryInterface $sessionRepository,
        VerificationSessionServiceInterface $sessionService,
        FastApiClientServiceInterface $fastApiClient
    ): void {
        Log::info('VerifyDriverKycJob started', ['session_id' => $this->sessionId]);

        // 1. Get the session
        $session = $sessionRepository->findOne($this->sessionId);

        if (!$session) {
            Log::error('Session not found', ['session_id' => $this->sessionId]);
            return;
        }

        // 2. Idempotency check - don't reprocess completed sessions
        if ($session->isAlreadyProcessed()) {
            Log::info('Session already processed, skipping', [
                'session_id' => $this->sessionId,
                'status' => $session->status,
                'decision' => $session->decision,
            ]);
            return;
        }

        // 3. Mark session as processing
        $marked = $sessionRepository->markAsProcessing($this->sessionId);
        if (!$marked) {
            Log::warning('Could not mark session as processing (race condition?)', [
                'session_id' => $this->sessionId,
            ]);
            return;
        }

        try {
            // 4. Get signed URLs for media
            $mediaUrls = $sessionService->getMediaSignedUrls($this->sessionId);

            if (empty($mediaUrls)) {
                throw new \RuntimeException('No media found for session');
            }

            Log::info('Calling FastAPI verification', [
                'session_id' => $this->sessionId,
                'media_kinds' => array_keys($mediaUrls),
            ]);

            // 5. Call FastAPI service
            $pythonResponse = $fastApiClient->verify($this->sessionId, $mediaUrls);

            Log::info('FastAPI response received', [
                'session_id' => $this->sessionId,
                'suggested_decision' => $pythonResponse['suggested_decision'] ?? 'unknown',
                'face_match_score' => $pythonResponse['face_match_score'] ?? 0,
                'doc_auth_score' => $pythonResponse['doc_auth_score'] ?? 0,
            ]);

            // 6. Apply decision based on thresholds
            $sessionService->applyDecision($this->sessionId, $pythonResponse);

            Log::info('VerifyDriverKycJob completed', ['session_id' => $this->sessionId]);

        } catch (Throwable $e) {
            Log::error('VerifyDriverKycJob failed', [
                'session_id' => $this->sessionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Re-throw to trigger retry logic
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $exception): void
    {
        Log::error('VerifyDriverKycJob failed permanently', [
            'session_id' => $this->sessionId,
            'error' => $exception->getMessage(),
        ]);

        // Update session to expired/failed state
        try {
            $sessionRepository = app(VerificationSessionRepositoryInterface::class);
            $sessionRepository->update($this->sessionId, [
                'status' => VerificationSession::STATUS_EXPIRED,
                'decision' => VerificationSession::DECISION_PENDING,
                'decision_reason_codes' => [
                    ['code' => 'PROCESSING_FAILED', 'message' => 'Verification processing failed after multiple attempts'],
                ],
            ]);

            // TODO: Notify admin about failed verification
            // TODO: Notify user that verification needs to be retried

        } catch (Throwable $e) {
            Log::error('Failed to update session after job failure', [
                'session_id' => $this->sessionId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get the unique ID for the job.
     */
    public function uniqueId(): string
    {
        return $this->sessionId;
    }

    /**
     * Get the tags for the job.
     */
    public function tags(): array
    {
        return ['verification', 'kyc', 'session:' . $this->sessionId];
    }
}
