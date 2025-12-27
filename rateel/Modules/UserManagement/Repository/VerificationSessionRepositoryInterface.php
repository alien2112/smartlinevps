<?php

namespace Modules\UserManagement\Repository;

use App\Repository\EloquentRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\UserManagement\Entities\VerificationSession;

interface VerificationSessionRepositoryInterface extends EloquentRepositoryInterface
{
    /**
     * Find the latest session for a user by type.
     */
    public function findLatestByUserId(string $userId, string $type = 'driver_kyc'): ?VerificationSession;

    /**
     * Find pending or unverified session for a user.
     */
    public function findActiveSessionForUser(string $userId, string $type = 'driver_kyc'): ?VerificationSession;

    /**
     * Get sessions for admin list with filters.
     */
    public function getForAdminList(array $filters = [], int $limit = 15, int $offset = 0): LengthAwarePaginator;

    /**
     * Mark session as processing.
     */
    public function markAsProcessing(string $sessionId): bool;

    /**
     * Update session with verification scores and decision.
     */
    public function updateWithScores(string $sessionId, array $scores, string $decision, array $reasonCodes = []): bool;

    /**
     * Update session with admin decision.
     */
    public function updateAdminDecision(string $sessionId, string $decision, ?string $notes, string $adminId): bool;

    /**
     * Get sessions pending manual review.
     */
    public function getPendingManualReview(int $limit = 50): Collection;

    /**
     * Get sessions that are expired (submitted but not processed within timeout).
     */
    public function getExpiredSessions(int $timeoutMinutes = 30): Collection;
}
