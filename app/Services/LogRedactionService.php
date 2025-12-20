<?php

namespace App\Services;

class LogRedactionService
{
    /**
     * List of sensitive keys that should be redacted from logs
     */
    protected array $sensitiveKeys = [
        'password',
        'password_confirmation',
        'current_password',
        'new_password',
        'old_password',
        'api_key',
        'api_secret',
        'secret',
        'secret_key',
        'token',
        'access_token',
        'refresh_token',
        'bearer_token',
        'auth_token',
        'jwt',
        'credit_card',
        'card_number',
        'cvv',
        'cvc',
        'pin',
        'ssn',
        'social_security',
        'passport_number',
        'license_number',
        'account_number',
        'routing_number',
        'iban',
        'swift',
        'private_key',
        'encryption_key',
        'database_url',
        'db_password',
        'mail_password',
        'redis_password',
        'aws_secret_access_key',
        'stripe_secret',
        'paypal_secret',
        'twilio_auth_token',
        'firebase_private_key',
    ];

    /**
     * Patterns to match sensitive data in strings
     */
    protected array $patterns = [
        // Credit card numbers (basic pattern)
        '/\b\d{4}[\s-]?\d{4}[\s-]?\d{4}[\s-]?\d{4}\b/' => '****-****-****-****',
        // Email addresses (partial redaction)
        '/([a-zA-Z0-9._%+-]+)@([a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/' => '***@$2',
        // Phone numbers (basic pattern)
        '/\b\+?[\d\s()-]{10,}\b/' => '***-***-****',
        // API keys (long alphanumeric strings)
        '/\b[a-zA-Z0-9_-]{32,}\b/' => '***REDACTED***',
    ];

    /**
     * Redact sensitive data from an array
     *
     * @param array $data Data to redact
     * @param int $depth Current recursion depth
     * @param int $maxDepth Maximum recursion depth
     * @return array Redacted data
     */
    public function redactArray(array $data, int $depth = 0, int $maxDepth = 10): array
    {
        if ($depth >= $maxDepth) {
            return ['...max_depth_reached'];
        }

        $redacted = [];

        foreach ($data as $key => $value) {
            // Check if key is sensitive
            if ($this->isSensitiveKey($key)) {
                $redacted[$key] = '***REDACTED***';
                continue;
            }

            // Recursively redact arrays
            if (is_array($value)) {
                $redacted[$key] = $this->redactArray($value, $depth + 1, $maxDepth);
            }
            // Recursively redact objects
            elseif (is_object($value)) {
                try {
                    $redacted[$key] = $this->redactArray((array) $value, $depth + 1, $maxDepth);
                } catch (\Exception $e) {
                    $redacted[$key] = '***OBJECT***';
                }
            }
            // Redact strings with patterns
            elseif (is_string($value)) {
                $redacted[$key] = $this->redactString($value);
            }
            // Keep other types as-is
            else {
                $redacted[$key] = $value;
            }
        }

        return $redacted;
    }

    /**
     * Check if a key is sensitive
     *
     * @param string $key The key to check
     * @return bool True if sensitive
     */
    protected function isSensitiveKey(string $key): bool
    {
        $lowerKey = strtolower($key);

        foreach ($this->sensitiveKeys as $sensitiveKey) {
            if (str_contains($lowerKey, strtolower($sensitiveKey))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Redact sensitive patterns from a string
     *
     * @param string $string String to redact
     * @return string Redacted string
     */
    protected function redactString(string $string): string
    {
        foreach ($this->patterns as $pattern => $replacement) {
            $string = preg_replace($pattern, $replacement, $string);
        }

        return $string;
    }

    /**
     * Redact Authorization header
     *
     * @param string|null $authHeader Authorization header value
     * @return string|null Redacted header
     */
    public function redactAuthHeader(?string $authHeader): ?string
    {
        if (!$authHeader) {
            return null;
        }

        // Redact Bearer tokens
        if (str_starts_with($authHeader, 'Bearer ')) {
            return 'Bearer ***REDACTED***';
        }

        // Redact Basic auth
        if (str_starts_with($authHeader, 'Basic ')) {
            return 'Basic ***REDACTED***';
        }

        return '***REDACTED***';
    }

    /**
     * Partially redact an email address
     *
     * @param string $email Email address
     * @return string Partially redacted email
     */
    public function redactEmail(string $email): string
    {
        if (!str_contains($email, '@')) {
            return '***INVALID_EMAIL***';
        }

        [$local, $domain] = explode('@', $email, 2);
        $localLength = strlen($local);

        if ($localLength <= 2) {
            $redactedLocal = str_repeat('*', $localLength);
        } else {
            $redactedLocal = substr($local, 0, 1) . str_repeat('*', $localLength - 2) . substr($local, -1);
        }

        return $redactedLocal . '@' . $domain;
    }

    /**
     * Add custom sensitive keys
     *
     * @param array $keys Additional keys to treat as sensitive
     * @return void
     */
    public function addSensitiveKeys(array $keys): void
    {
        $this->sensitiveKeys = array_merge($this->sensitiveKeys, $keys);
    }

    /**
     * Redact request data for logging
     *
     * @param \Illuminate\Http\Request $request
     * @return array Redacted request data
     */
    public function redactRequest($request): array
    {
        $data = [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ];

        // Redact request body
        $data['body'] = $this->redactArray($request->all());

        // Redact headers
        $headers = $request->headers->all();
        if (isset($headers['authorization'])) {
            $headers['authorization'] = [$this->redactAuthHeader($headers['authorization'][0] ?? null)];
        }
        if (isset($headers['x-api-key'])) {
            $headers['x-api-key'] = ['***REDACTED***'];
        }
        $data['headers'] = $headers;

        return $data;
    }
}
