<?php

namespace Modules\UserManagement\Repository\Eloquent;

use App\Repository\Eloquent\BaseRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\UserManagement\Entities\VerificationSession;
use Modules\UserManagement\Repository\VerificationSessionRepositoryInterface;

class VerificationSessionRepository extends BaseRepository implements VerificationSessionRepositoryInterface
{
    public function __construct(VerificationSession $model)
    {
        parent::__construct($model);
    }

    /**
     * Find the latest session for a user by type.
     */
    public function findLatestByUserId(string $userId, string $type = 'driver_kyc'): ?VerificationSession
    {
        return $this->model
            ->where('user_id', $userId)
            ->where('type', $type)
            ->orderBy('created_at', 'desc')
            ->first();
    }

    /**
     * Find pending or unverified session for a user.
     */
    public function findActiveSessionForUser(string $userId, string $type = 'driver_kyc'): ?VerificationSession
    {
        return $this->model
            ->where('user_id', $userId)
            ->where('type', $type)
            ->whereIn('status', [
                VerificationSession::STATUS_UNVERIFIED,
                VerificationSession::STATUS_PENDING,
                VerificationSession::STATUS_PROCESSING,
            ])
            ->orderBy('created_at', 'desc')
            ->first();
    }

    /**
     * Get sessions for admin list with filters.
     */
    public function getForAdminList(array $filters = [], int $limit = 15, int $offset = 0): LengthAwarePaginator
    {
        $query = $this->model->with(['user', 'reviewedBy']);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['decision'])) {
            $query->where('decision', $filters['decision']);
        }

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (!empty($filters['from_date'])) {
            $query->whereDate('created_at', '>=', $filters['from_date']);
        }

        if (!empty($filters['to_date'])) {
            $query->whereDate('created_at', '<=', $filters['to_date']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('created_at', 'desc')->paginate($limit);
    }

    /**
     * Mark session as processing.
     */
    public function markAsProcessing(string $sessionId): bool
    {
        return $this->model
            ->where('id', $sessionId)
            ->whereIn('status', [
                VerificationSession::STATUS_PENDING,
                VerificationSession::STATUS_UNVERIFIED,
            ])
            ->update([
                'status' => VerificationSession::STATUS_PROCESSING,
            ]) > 0;
    }

    /**
     * Update session with verification scores and decision.
     */
    public function updateWithScores(string $sessionId, array $scores, string $decision, array $reasonCodes = []): bool
    {
        $updateData = [
            'liveness_score' => $scores['liveness_score'] ?? null,
            'face_match_score' => $scores['face_match_score'] ?? null,
            'doc_auth_score' => $scores['doc_auth_score'] ?? null,
            'extracted_fields' => $scores['extracted_fields'] ?? null,
            'decision' => $decision,
            'decision_reason_codes' => $reasonCodes,
            'processed_at' => now(),
        ];

        // Map decision to status
        $status = match($decision) {
            VerificationSession::DECISION_APPROVED => VerificationSession::STATUS_VERIFIED,
            VerificationSession::DECISION_REJECTED => VerificationSession::STATUS_REJECTED,
            VerificationSession::DECISION_MANUAL_REVIEW => VerificationSession::STATUS_MANUAL_REVIEW,
            default => VerificationSession::STATUS_PENDING,
        };

        $updateData['status'] = $status;

        return $this->model
            ->where('id', $sessionId)
            ->update($updateData) > 0;
    }

    /**
     * Update session with admin decision.
     */
    public function updateAdminDecision(string $sessionId, string $decision, ?string $notes, string $adminId): bool
    {
        $status = match($decision) {
            VerificationSession::DECISION_APPROVED => VerificationSession::STATUS_VERIFIED,
            VerificationSession::DECISION_REJECTED => VerificationSession::STATUS_REJECTED,
            default => VerificationSession::STATUS_MANUAL_REVIEW,
        };

        return $this->model
            ->where('id', $sessionId)
            ->update([
                'decision' => $decision,
                'status' => $status,
                'admin_notes' => $notes,
                'reviewed_by_admin_id' => $adminId,
                'reviewed_at' => now(),
            ]) > 0;
    }

    /**
     * Get sessions pending manual review.
     */
    public function getPendingManualReview(int $limit = 50): Collection
    {
        return $this->model
            ->where('status', VerificationSession::STATUS_MANUAL_REVIEW)
            ->where('decision', VerificationSession::DECISION_MANUAL_REVIEW)
            ->orderBy('created_at', 'asc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get sessions that are expired (submitted but not processed within timeout).
     */
    public function getExpiredSessions(int $timeoutMinutes = 30): Collection
    {
        return $this->model
            ->where('status', VerificationSession::STATUS_PROCESSING)
            ->where('submitted_at', '<', now()->subMinutes($timeoutMinutes))
            ->get();
    }
}
