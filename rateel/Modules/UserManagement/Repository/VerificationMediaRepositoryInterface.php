<?php

namespace Modules\UserManagement\Repository;

use App\Repository\EloquentRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Modules\UserManagement\Entities\VerificationMedia;

interface VerificationMediaRepositoryInterface extends EloquentRepositoryInterface
{
    /**
     * Find media by session ID and kind.
     */
    public function findBySessionAndKind(string $sessionId, string $kind): ?VerificationMedia;

    /**
     * Get all media for a session.
     */
    public function getBySession(string $sessionId): Collection;

    /**
     * Delete all media for a session.
     */
    public function deleteBySession(string $sessionId): int;

    /**
     * Check if required media exists for a session.
     */
    public function hasRequiredMedia(string $sessionId, array $requiredKinds = ['selfie', 'id_front']): bool;
}
