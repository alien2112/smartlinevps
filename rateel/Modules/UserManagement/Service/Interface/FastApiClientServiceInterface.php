<?php

namespace Modules\UserManagement\Service\Interface;

interface FastApiClientServiceInterface
{
    /**
     * Call FastAPI verification endpoint.
     * 
     * @param string $sessionId The verification session ID
     * @param array $mediaUrls Signed URLs for media files
     * @return array Response from FastAPI service
     */
    public function verify(string $sessionId, array $mediaUrls): array;

    /**
     * Check if FastAPI service is healthy.
     */
    public function healthCheck(): bool;
}
