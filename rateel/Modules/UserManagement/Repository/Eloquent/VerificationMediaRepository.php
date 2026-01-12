<?php

namespace Modules\UserManagement\Repository\Eloquent;

use App\Repository\Eloquent\BaseRepository;
use Illuminate\Database\Eloquent\Collection;
use Modules\UserManagement\Entities\VerificationMedia;
use Modules\UserManagement\Repository\VerificationMediaRepositoryInterface;

class VerificationMediaRepository extends BaseRepository implements VerificationMediaRepositoryInterface
{
    public function __construct(VerificationMedia $model)
    {
        parent::__construct($model);
    }

    /**
     * Find media by session ID and kind.
     */
    public function findBySessionAndKind(string $sessionId, string $kind): ?VerificationMedia
    {
        return $this->model
            ->where('session_id', $sessionId)
            ->where('kind', $kind)
            ->first();
    }

    /**
     * Get all media for a session.
     */
    public function getBySession(string $sessionId): Collection
    {
        return $this->model
            ->where('session_id', $sessionId)
            ->get();
    }

    /**
     * Delete all media for a session.
     */
    public function deleteBySession(string $sessionId): int
    {
        return $this->model
            ->where('session_id', $sessionId)
            ->delete();
    }

    /**
     * Check if required media exists for a session.
     * Handles flexible video kind matching (e.g., liveness_video can be satisfied by liveness_video, liveness_video_1, or liveness_video_2)
     */
    public function hasRequiredMedia(string $sessionId, array $requiredKinds = ['selfie', 'id_front']): bool
    {
        $existingKinds = $this->model
            ->where('session_id', $sessionId)
            ->pluck('kind')
            ->toArray();

        $videoKinds = config('verification.video_kinds', ['liveness_video', 'liveness_video_1', 'liveness_video_2']);

        foreach ($requiredKinds as $kind) {
            // Check if it's a video requirement
            if ($kind === 'liveness_video') {
                // For liveness_video requirement, check if ANY video kind exists
                $hasVideo = false;
                foreach ($videoKinds as $videoKind) {
                    if (in_array($videoKind, $existingKinds)) {
                        $hasVideo = true;
                        break;
                    }
                }
                if (!$hasVideo) {
                    return false;
                }
            } else {
                // For other kinds (selfie, id_front, id_back), check exact match
                if (!in_array($kind, $existingKinds)) {
                    return false;
                }
            }
        }

        return true;
    }
}
