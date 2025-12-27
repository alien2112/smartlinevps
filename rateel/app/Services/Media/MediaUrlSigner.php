<?php

namespace App\Services\Media;

use InvalidArgumentException;

/**
 * Service for generating signed URLs for private media access.
 * 
 * Uses HMAC-SHA256 signatures with support for key rotation via `kid` parameter.
 * Signed URLs include expiration time and optional user/scope restrictions.
 */
class MediaUrlSigner
{
    private string $cdnDomain;
    private array $signingSecrets;
    private string $currentKid;
    private int $defaultTtl;
    private array $ttlByCategory;

    public function __construct()
    {
        $this->cdnDomain = config('media.cdn_domain');
        $this->signingSecrets = config('media.signing_secrets', []);
        $this->currentKid = config('media.current_kid', 'v1');
        $this->defaultTtl = config('media.default_ttl', 300);
        $this->ttlByCategory = config('media.ttl_by_category', []);
    }

    /**
     * Generate a signed URL for an object key.
     *
     * @param string $objectKey  Object key in R2 (e.g., "driver/123/kyc/id_front/uuid.jpg")
     * @param int|null $expiresIn TTL in seconds (null = use default or category-based TTL)
     * @param string|null $uid    Optional user ID for per-user validation
     * @param string|null $scope  Optional scope (e.g., "kyc", "vehicle", "profile")
     * @return string             Full signed URL
     * @throws InvalidArgumentException
     */
    public function sign(string $objectKey, ?int $expiresIn = null, ?string $uid = null, ?string $scope = null): string
    {
        // Validate object key
        $this->validateObjectKey($objectKey);

        // Determine TTL
        $ttl = $expiresIn ?? $this->getTtlForObjectKey($objectKey, $scope);
        
        // Calculate expiration timestamp
        $exp = time() + $ttl;

        // Get current signing secret
        $secret = $this->getSigningSecret($this->currentKid);
        if (empty($secret)) {
            throw new InvalidArgumentException("No signing secret configured for kid: {$this->currentKid}");
        }

        // Generate canonical string
        $canonical = $this->getCanonicalString($objectKey, $exp, $uid, $scope);

        // Generate signature
        $signature = $this->generateSignature($canonical, $secret);

        // Build URL
        return $this->buildUrl($objectKey, $exp, $signature, $this->currentKid, $uid, $scope);
    }

    /**
     * Sign multiple object keys at once (batch signing).
     *
     * @param array $objectKeys Array of object keys
     * @param int|null $expiresIn TTL in seconds
     * @param string|null $uid User ID
     * @param string|null $scope Scope
     * @return array Array of [object_key => signed_url]
     */
    public function signBatch(array $objectKeys, ?int $expiresIn = null, ?string $uid = null, ?string $scope = null): array
    {
        $result = [];
        foreach ($objectKeys as $objectKey) {
            if (!empty($objectKey)) {
                $result[$objectKey] = $this->sign($objectKey, $expiresIn, $uid, $scope);
            }
        }
        return $result;
    }

    /**
     * Get the canonical string for signing (v1 protocol).
     *
     * Format:
     *   v1
     *   GET
     *   {cdn_domain}
     *   /img/{object_key}
     *   exp={exp}
     *   uid={uid_or_empty}
     *   scope={scope_or_empty}
     */
    private function getCanonicalString(string $objectKey, int $exp, ?string $uid, ?string $scope): string
    {
        $lines = [
            'v1',
            'GET',
            $this->cdnDomain,
            '/img/' . $objectKey,
            'exp=' . $exp,
            'uid=' . ($uid ?? ''),
            'scope=' . ($scope ?? ''),
        ];

        return implode("\n", $lines);
    }

    /**
     * Generate HMAC-SHA256 signature (base64url encoded).
     */
    private function generateSignature(string $canonical, string $secret): string
    {
        $hash = hash_hmac('sha256', $canonical, $secret, true);
        return $this->base64UrlEncode($hash);
    }

    /**
     * Build the full signed URL.
     */
    private function buildUrl(string $objectKey, int $exp, string $signature, string $kid, ?string $uid, ?string $scope): string
    {
        $params = [
            'exp' => $exp,
            'sig' => $signature,
            'kid' => $kid,
        ];

        if (!empty($uid)) {
            $params['uid'] = $uid;
        }

        if (!empty($scope)) {
            $params['scope'] = $scope;
        }

        $queryString = http_build_query($params);
        
        return "https://{$this->cdnDomain}/img/{$objectKey}?{$queryString}";
    }

    /**
     * Get signing secret for a key ID.
     */
    private function getSigningSecret(string $kid): ?string
    {
        return $this->signingSecrets[$kid] ?? null;
    }

    /**
     * Determine TTL based on object key path or scope.
     */
    private function getTtlForObjectKey(string $objectKey, ?string $scope): int
    {
        // If scope is provided, use it
        if ($scope && isset($this->ttlByCategory[$scope])) {
            return $this->ttlByCategory[$scope];
        }

        // Try to infer category from object key path
        // Pattern: {category}/{id}/{subcategory}/...
        $parts = explode('/', $objectKey);
        
        if (count($parts) >= 3) {
            $subCategory = $parts[2] ?? '';
            
            // Map subcategory to TTL category
            $categoryMap = [
                'identity' => 'kyc',
                'kyc' => 'kyc',
                'profile' => 'profile',
                'vehicle' => 'vehicle',
                'document' => 'document',
                'license' => 'document',
                'record' => 'document',
                'car' => 'vehicle',
                'receipt' => 'receipt',
                'evidence' => 'evidence',
            ];

            $category = $categoryMap[$subCategory] ?? null;
            if ($category && isset($this->ttlByCategory[$category])) {
                return $this->ttlByCategory[$category];
            }
        }

        return $this->defaultTtl;
    }

    /**
     * Validate object key for security issues.
     */
    private function validateObjectKey(string $objectKey): void
    {
        if (empty($objectKey)) {
            throw new InvalidArgumentException('Object key cannot be empty');
        }

        // Check for path traversal
        if (str_contains($objectKey, '..')) {
            throw new InvalidArgumentException('Object key cannot contain path traversal sequences');
        }

        // Check for backslashes (Windows path separator)
        if (str_contains($objectKey, '\\')) {
            throw new InvalidArgumentException('Object key cannot contain backslashes');
        }

        // Check for null bytes
        if (str_contains($objectKey, "\0")) {
            throw new InvalidArgumentException('Object key cannot contain null bytes');
        }

        // Check for double encoding
        if (preg_match('/%25|%2e%2e|%2f/i', $objectKey)) {
            throw new InvalidArgumentException('Object key cannot contain encoded special characters');
        }
    }

    /**
     * Base64 URL-safe encoding.
     */
    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Get the current key ID being used for signing.
     */
    public function getCurrentKid(): string
    {
        return $this->currentKid;
    }

    /**
     * Get the CDN domain.
     */
    public function getCdnDomain(): string
    {
        return $this->cdnDomain;
    }
}
