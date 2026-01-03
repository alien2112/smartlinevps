<?php

namespace App\Services\Driver;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

/**
 * Rate Limiter for Driver Onboarding
 *
 * Provides multi-layer rate limiting for OTP operations:
 * - Per phone number (prevents abuse of a single number)
 * - Per IP address (prevents distributed attacks)
 * - Per device (prevents app cloning attacks)
 * - Global (prevents DoS attacks)
 *
 * Uses Redis for atomic operations and TTL management.
 */
class OnboardingRateLimiter
{
    private const PREFIX = 'driver_onboarding:';

    /**
     * Check if OTP send is allowed for given identifiers
     *
     * @return array{allowed: bool, reason?: string, retry_after?: int, retry_after_at?: string}
     */
    public function checkOtpSend(string $phone, string $ip, ?string $deviceId = null): array
    {
        $config = config('driver_onboarding.rate_limits');

        // Check phone limits
        $phoneCheck = $this->checkPhoneLimits($phone, $config['phone']);
        if (!$phoneCheck['allowed']) {
            return $phoneCheck;
        }

        // Check IP limits
        $ipCheck = $this->checkIpLimits($ip, $config['ip']);
        if (!$ipCheck['allowed']) {
            return $ipCheck;
        }

        // Check device limits (if provided)
        if ($deviceId) {
            $deviceCheck = $this->checkDeviceLimits($deviceId, $config['device']);
            if (!$deviceCheck['allowed']) {
                return $deviceCheck;
            }
        }

        // Check global limits
        $globalCheck = $this->checkGlobalLimits($config['global']);
        if (!$globalCheck['allowed']) {
            return $globalCheck;
        }

        return ['allowed' => true];
    }

    /**
     * Record a successful OTP send
     */
    public function recordOtpSend(string $phone, string $ip, ?string $deviceId = null): void
    {
        $phoneHash = $this->hashPhone($phone);

        Redis::pipeline(function ($pipe) use ($phoneHash, $ip, $deviceId) {
            // Phone counters
            $phoneHourlyKey = self::PREFIX . "phone:{$phoneHash}:hourly";
            $phoneDailyKey = self::PREFIX . "phone:{$phoneHash}:daily";

            $pipe->incr($phoneHourlyKey);
            $pipe->expire($phoneHourlyKey, 3600);
            $pipe->incr($phoneDailyKey);
            $pipe->expire($phoneDailyKey, 86400);

            // IP counters
            $ipHourlyKey = self::PREFIX . "ip:{$ip}:hourly";
            $ipDailyKey = self::PREFIX . "ip:{$ip}:daily";

            $pipe->incr($ipHourlyKey);
            $pipe->expire($ipHourlyKey, 3600);
            $pipe->incr($ipDailyKey);
            $pipe->expire($ipDailyKey, 86400);

            // Device counter
            if ($deviceId) {
                $deviceKey = self::PREFIX . "device:{$deviceId}:hourly";
                $pipe->incr($deviceKey);
                $pipe->expire($deviceKey, 3600);
            }

            // Global counter
            $globalKey = self::PREFIX . "global:minute";
            $pipe->incr($globalKey);
            $pipe->expire($globalKey, 60);
        });

        Log::debug('OTP send recorded', [
            'phone_hash' => substr($phoneHash, 0, 8) . '...',
            'ip' => $ip,
            'device_id' => $deviceId ? substr($deviceId, 0, 8) . '...' : null,
        ]);
    }

    /**
     * Check OTP verification rate limit
     *
     * @return array{allowed: bool, attempts_remaining?: int, locked?: bool, retry_after?: int}
     */
    public function checkOtpVerify(string $onboardingId): array
    {
        $key = self::PREFIX . "verify:{$onboardingId}";
        $maxAttempts = config('driver_onboarding.otp.max_verify_attempts', 5);
        $lockoutMinutes = config('driver_onboarding.otp.verify_lockout_minutes', 30);

        $attempts = (int) Redis::get($key) ?? 0;

        if ($attempts >= $maxAttempts) {
            $ttl = Redis::ttl($key);
            return [
                'allowed' => false,
                'locked' => true,
                'retry_after' => $ttl > 0 ? $ttl : $lockoutMinutes * 60,
                'retry_after_at' => now()->addSeconds($ttl > 0 ? $ttl : $lockoutMinutes * 60)->toIso8601String(),
            ];
        }

        return [
            'allowed' => true,
            'attempts_remaining' => $maxAttempts - $attempts - 1,
        ];
    }

    /**
     * Record an OTP verification attempt
     */
    public function recordOtpVerifyAttempt(string $onboardingId, bool $success): void
    {
        $key = self::PREFIX . "verify:{$onboardingId}";
        $lockoutMinutes = config('driver_onboarding.otp.verify_lockout_minutes', 30);

        if ($success) {
            Redis::del($key);
        } else {
            $attempts = Redis::incr($key);
            // Set TTL only if this is the first attempt or TTL expired
            if ($attempts === 1 || Redis::ttl($key) === -1) {
                Redis::expire($key, $lockoutMinutes * 60);
            }
        }
    }

    /**
     * Check resend cooldown
     *
     * @return array{allowed: bool, retry_after?: int, retry_after_at?: string}
     */
    public function checkResendCooldown(string $onboardingId): array
    {
        $key = self::PREFIX . "resend_cooldown:{$onboardingId}";
        $ttl = Redis::ttl($key);

        if ($ttl > 0) {
            return [
                'allowed' => false,
                'retry_after' => $ttl,
                'retry_after_at' => now()->addSeconds($ttl)->toIso8601String(),
            ];
        }

        return ['allowed' => true];
    }

    /**
     * Set resend cooldown
     */
    public function setResendCooldown(string $onboardingId): void
    {
        $key = self::PREFIX . "resend_cooldown:{$onboardingId}";
        $cooldownSeconds = config('driver_onboarding.otp.resend_cooldown_seconds', 60);

        Redis::setex($key, $cooldownSeconds, 1);
    }

    /**
     * Clear all rate limit data for a phone (use carefully, mainly for testing)
     */
    public function clearForPhone(string $phone): void
    {
        $phoneHash = $this->hashPhone($phone);
        $pattern = self::PREFIX . "phone:{$phoneHash}:*";

        $keys = Redis::keys($pattern);
        if (!empty($keys)) {
            Redis::del($keys);
        }
    }

    /**
     * Lock a phone number (e.g., after detecting abuse)
     */
    public function lockPhone(string $phone, int $minutes = 60): void
    {
        $phoneHash = $this->hashPhone($phone);
        $key = self::PREFIX . "phone_locked:{$phoneHash}";

        Redis::setex($key, $minutes * 60, now()->toIso8601String());

        Log::warning('Phone locked due to rate limit abuse', [
            'phone_hash' => substr($phoneHash, 0, 8) . '...',
            'locked_until' => now()->addMinutes($minutes)->toIso8601String(),
        ]);
    }

    /**
     * Check if phone is locked
     *
     * @return array{locked: bool, locked_until?: string}
     */
    public function isPhoneLocked(string $phone): array
    {
        $phoneHash = $this->hashPhone($phone);
        $key = self::PREFIX . "phone_locked:{$phoneHash}";

        $ttl = Redis::ttl($key);
        if ($ttl > 0) {
            return [
                'locked' => true,
                'locked_until' => now()->addSeconds($ttl)->toIso8601String(),
            ];
        }

        return ['locked' => false];
    }

    // ============================================
    // Private Methods
    // ============================================

    private function checkPhoneLimits(string $phone, array $config): array
    {
        $phoneHash = $this->hashPhone($phone);

        // Check if phone is locked
        $lockCheck = $this->isPhoneLocked($phone);
        if ($lockCheck['locked']) {
            return [
                'allowed' => false,
                'reason' => 'phone_locked',
                'locked_until' => $lockCheck['locked_until'],
            ];
        }

        $hourlyKey = self::PREFIX . "phone:{$phoneHash}:hourly";
        $dailyKey = self::PREFIX . "phone:{$phoneHash}:daily";

        $hourlyCount = (int) Redis::get($hourlyKey) ?? 0;
        $dailyCount = (int) Redis::get($dailyKey) ?? 0;

        if ($hourlyCount >= $config['max_per_hour']) {
            $ttl = Redis::ttl($hourlyKey);
            return [
                'allowed' => false,
                'reason' => 'phone_hourly_limit',
                'retry_after' => $ttl > 0 ? $ttl : 3600,
                'retry_after_at' => now()->addSeconds($ttl > 0 ? $ttl : 3600)->toIso8601String(),
            ];
        }

        if ($dailyCount >= $config['max_per_day']) {
            $ttl = Redis::ttl($dailyKey);

            // Lock the phone for the configured lockout duration
            $this->lockPhone($phone, $config['lockout_minutes']);

            return [
                'allowed' => false,
                'reason' => 'phone_daily_limit',
                'retry_after' => $config['lockout_minutes'] * 60,
                'retry_after_at' => now()->addMinutes($config['lockout_minutes'])->toIso8601String(),
            ];
        }

        return ['allowed' => true];
    }

    private function checkIpLimits(string $ip, array $config): array
    {
        $hourlyKey = self::PREFIX . "ip:{$ip}:hourly";
        $dailyKey = self::PREFIX . "ip:{$ip}:daily";

        $hourlyCount = (int) Redis::get($hourlyKey) ?? 0;
        $dailyCount = (int) Redis::get($dailyKey) ?? 0;

        if ($hourlyCount >= $config['max_per_hour']) {
            $ttl = Redis::ttl($hourlyKey);
            return [
                'allowed' => false,
                'reason' => 'ip_hourly_limit',
                'retry_after' => $ttl > 0 ? $ttl : 3600,
            ];
        }

        if ($dailyCount >= $config['max_per_day']) {
            $ttl = Redis::ttl($dailyKey);
            return [
                'allowed' => false,
                'reason' => 'ip_daily_limit',
                'retry_after' => $ttl > 0 ? $ttl : 86400,
            ];
        }

        return ['allowed' => true];
    }

    private function checkDeviceLimits(string $deviceId, array $config): array
    {
        $key = self::PREFIX . "device:{$deviceId}:hourly";
        $count = (int) Redis::get($key) ?? 0;

        if ($count >= $config['max_per_hour']) {
            $ttl = Redis::ttl($key);
            return [
                'allowed' => false,
                'reason' => 'device_limit',
                'retry_after' => $ttl > 0 ? $ttl : 3600,
            ];
        }

        return ['allowed' => true];
    }

    private function checkGlobalLimits(array $config): array
    {
        $key = self::PREFIX . "global:minute";
        $count = (int) Redis::get($key) ?? 0;

        if ($count >= $config['max_per_minute']) {
            return [
                'allowed' => false,
                'reason' => 'server_busy',
                'retry_after' => 60,
            ];
        }

        return ['allowed' => true];
    }

    /**
     * Hash phone number for storage (privacy protection)
     */
    private function hashPhone(string $phone): string
    {
        // Normalize phone number
        $normalized = preg_replace('/[^0-9]/', '', $phone);
        return hash('sha256', $normalized . config('app.key'));
    }
}
