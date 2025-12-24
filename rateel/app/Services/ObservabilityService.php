<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Deep Observability Service for Laravel
 * 
 * PURPOSE: OBSERVE ONLY - NO FIXES, NO LOGIC CHANGES
 * 
 * This service provides structured logging to help diagnose timing,
 * ordering, and delivery issues without changing any application logic.
 */
class ObservabilityService
{
    /**
     * Get trace ID from current request
     */
    public static function getTraceId(): string
    {
        try {
            return request()?->attributes?->get('trace_id') ?? self::generateTraceId();
        } catch (\Exception $e) {
            return self::generateTraceId();
        }
    }

    /**
     * Generate a trace ID
     */
    public static function generateTraceId(): string
    {
        return 'trc_' . time() . '_' . substr(md5(uniqid()), 0, 8);
    }

    /**
     * Create structured log entry
     */
    public static function createLogEntry(array $context): array
    {
        return array_merge([
            'timestamp' => now()->toIso8601String(),
            'timestamp_ms' => (int)(microtime(true) * 1000),
            'service' => 'laravel-api',
            'trace_id' => self::getTraceId(),
        ], $context);
    }

    /**
     * Log controller entry point
     */
    public static function observeControllerEntry(
        string $controller,
        string $method,
        array $params = [],
        ?string $userId = null,
        ?string $rideId = null
    ): float {
        $startTime = microtime(true);
        
        Log::info('OBSERVE: Controller entry', self::createLogEntry([
            'event_type' => 'controller_entry',
            'source' => 'http',
            'status' => 'started',
            'controller' => $controller,
            'method' => $method,
            'user_id' => $userId ?? auth('api')->id(),
            'ride_id' => $rideId,
            'params' => self::sanitizeParams($params),
        ]));
        
        return $startTime;
    }

    /**
     * Log controller exit
     */
    public static function observeControllerExit(
        string $controller,
        string $method,
        float $startTime,
        string $status = 'success',
        ?string $userId = null,
        ?string $rideId = null,
        ?array $extra = []
    ): void {
        $duration = round((microtime(true) - $startTime) * 1000, 2);
        
        Log::info('OBSERVE: Controller exit', self::createLogEntry([
            'event_type' => 'controller_exit',
            'source' => 'http',
            'status' => $status,
            'controller' => $controller,
            'method' => $method,
            'user_id' => $userId ?? auth('api')->id(),
            'ride_id' => $rideId,
            'duration_ms' => $duration,
            'slow_request' => $duration > 3000,
            'extra' => $extra,
        ]));
    }

    /**
     * Log validation phase
     */
    public static function observeValidation(
        string $context,
        bool $passed,
        ?array $errors = null
    ): void {
        Log::info('OBSERVE: Validation', self::createLogEntry([
            'event_type' => 'validation',
            'source' => 'http',
            'status' => $passed ? 'passed' : 'failed',
            'context' => $context,
            'errors' => $passed ? null : $errors,
        ]));
    }

    /**
     * Log database transaction start
     */
    public static function observeDbTransactionStart(
        string $context,
        ?string $rideId = null
    ): float {
        $startTime = microtime(true);
        
        Log::info('OBSERVE: DB Transaction start', self::createLogEntry([
            'event_type' => 'db_transaction_start',
            'source' => 'database',
            'status' => 'started',
            'context' => $context,
            'ride_id' => $rideId,
        ]));
        
        return $startTime;
    }

    /**
     * Log database transaction commit
     */
    public static function observeDbTransactionCommit(
        string $context,
        float $startTime,
        ?string $rideId = null
    ): void {
        $duration = round((microtime(true) - $startTime) * 1000, 2);
        
        Log::info('OBSERVE: DB Transaction commit', self::createLogEntry([
            'event_type' => 'db_transaction_commit',
            'source' => 'database',
            'status' => 'committed',
            'context' => $context,
            'ride_id' => $rideId,
            'duration_ms' => $duration,
        ]));
    }

    /**
     * Log database transaction rollback
     */
    public static function observeDbTransactionRollback(
        string $context,
        float $startTime,
        ?string $error = null,
        ?string $rideId = null
    ): void {
        $duration = round((microtime(true) - $startTime) * 1000, 2);
        
        Log::warning('OBSERVE: DB Transaction rollback', self::createLogEntry([
            'event_type' => 'db_transaction_rollback',
            'source' => 'database',
            'status' => 'rolled_back',
            'context' => $context,
            'ride_id' => $rideId,
            'error' => $error,
            'duration_ms' => $duration,
        ]));
    }

    /**
     * Log queue job dispatch
     */
    public static function observeJobDispatched(
        string $jobClass,
        string $queue,
        ?string $rideId = null,
        ?string $userId = null,
        ?array $jobData = []
    ): void {
        Log::info('OBSERVE: Job dispatched', self::createLogEntry([
            'event_type' => 'job_dispatched',
            'source' => 'queue',
            'status' => 'dispatched',
            'job_class' => $jobClass,
            'queue' => $queue,
            'ride_id' => $rideId,
            'user_id' => $userId,
            'job_data_keys' => array_keys($jobData),
        ]));
    }

    /**
     * Log queue job execution start
     */
    public static function observeJobStart(
        string $jobClass,
        ?string $rideId = null,
        ?string $userId = null
    ): float {
        $startTime = microtime(true);
        
        Log::info('OBSERVE: Job started', self::createLogEntry([
            'event_type' => 'job_started',
            'source' => 'queue',
            'status' => 'started',
            'job_class' => $jobClass,
            'ride_id' => $rideId,
            'user_id' => $userId,
        ]));
        
        return $startTime;
    }

    /**
     * Log queue job execution complete
     */
    public static function observeJobComplete(
        string $jobClass,
        float $startTime,
        string $status = 'success',
        ?string $rideId = null,
        ?string $error = null
    ): void {
        $duration = round((microtime(true) - $startTime) * 1000, 2);
        
        $logMethod = $status === 'success' ? 'info' : 'error';
        
        Log::{$logMethod}('OBSERVE: Job completed', self::createLogEntry([
            'event_type' => 'job_completed',
            'source' => 'queue',
            'status' => $status,
            'job_class' => $jobClass,
            'ride_id' => $rideId,
            'duration_ms' => $duration,
            'error' => $error,
        ]));
    }

    /**
     * Log FCM notification attempt
     */
    public static function observeFcmAttempt(
        string $action,
        string $userId,
        ?string $rideId = null,
        ?string $fcmToken = null
    ): float {
        $startTime = microtime(true);
        
        Log::info('OBSERVE: FCM send attempt', self::createLogEntry([
            'event_type' => 'fcm_attempt',
            'source' => 'fcm',
            'status' => 'sending',
            'action' => $action,
            'user_id' => $userId,
            'ride_id' => $rideId,
            'has_fcm_token' => !empty($fcmToken),
            'fcm_token_prefix' => $fcmToken ? substr($fcmToken, 0, 20) . '...' : null,
        ]));
        
        return $startTime;
    }

    /**
     * Log FCM notification result
     */
    public static function observeFcmResult(
        string $action,
        float $startTime,
        bool $success,
        string $userId,
        ?string $rideId = null,
        ?string $error = null
    ): void {
        $duration = round((microtime(true) - $startTime) * 1000, 2);
        
        $logMethod = $success ? 'info' : 'warning';
        
        Log::{$logMethod}('OBSERVE: FCM send result', self::createLogEntry([
            'event_type' => 'fcm_result',
            'source' => 'fcm',
            'status' => $success ? 'sent' : 'failed',
            'action' => $action,
            'user_id' => $userId,
            'ride_id' => $rideId,
            'duration_ms' => $duration,
            'error' => $error,
        ]));
    }

    /**
     * Log Redis event publish
     */
    public static function observeRedisPublish(
        string $channel,
        ?string $rideId = null,
        ?string $driverId = null,
        ?string $customerId = null
    ): float {
        $startTime = microtime(true);
        
        Log::info('OBSERVE: Redis publish', self::createLogEntry([
            'event_type' => 'redis_publish',
            'source' => 'redis',
            'status' => 'publishing',
            'channel' => $channel,
            'ride_id' => $rideId,
            'driver_id' => $driverId,
            'customer_id' => $customerId,
        ]));
        
        return $startTime;
    }

    /**
     * Log Redis event publish result
     */
    public static function observeRedisPublishResult(
        string $channel,
        float $startTime,
        bool $success,
        ?string $error = null
    ): void {
        $duration = round((microtime(true) - $startTime) * 1000, 2);
        
        Log::info('OBSERVE: Redis publish result', self::createLogEntry([
            'event_type' => 'redis_publish_result',
            'source' => 'redis',
            'status' => $success ? 'published' : 'failed',
            'channel' => $channel,
            'duration_ms' => $duration,
            'error' => $error,
        ]));
    }

    /**
     * Log trip state change (critical for debugging)
     */
    public static function observeTripStateChange(
        string $tripId,
        string $fromState,
        string $toState,
        ?string $driverId = null,
        ?string $customerId = null,
        ?string $trigger = null
    ): void {
        Log::info('OBSERVE: Trip state change', self::createLogEntry([
            'event_type' => 'trip_state_change',
            'source' => 'business_logic',
            'status' => 'changed',
            'ride_id' => $tripId,
            'trip_id' => $tripId,
            'driver_id' => $driverId,
            'customer_id' => $customerId,
            'state_before' => $fromState,
            'state_after' => $toState,
            'trigger' => $trigger,
        ]));
    }

    /**
     * Log OTP verification (critical flow)
     */
    public static function observeOtpVerification(
        string $tripId,
        string $phase, // 'received', 'validated', 'success', 'failed'
        ?string $driverId = null,
        ?string $error = null
    ): void {
        Log::info('OBSERVE: OTP verification', self::createLogEntry([
            'event_type' => 'otp_verification',
            'source' => 'http',
            'status' => $phase,
            'ride_id' => $tripId,
            'trip_id' => $tripId,
            'driver_id' => $driverId,
            'error' => $error,
        ]));
    }

    /**
     * Log event broadcast (Laravel Events)
     */
    public static function observeEventBroadcast(
        string $eventClass,
        ?string $rideId = null,
        ?string $channel = null
    ): void {
        Log::info('OBSERVE: Event broadcast', self::createLogEntry([
            'event_type' => 'event_broadcast',
            'source' => 'laravel_events',
            'status' => 'broadcasting',
            'event_class' => $eventClass,
            'ride_id' => $rideId,
            'channel' => $channel,
        ]));
    }

    /**
     * Log timing between two events
     */
    public static function observeTiming(
        string $fromEvent,
        string $toEvent,
        float $startTime,
        ?string $rideId = null
    ): void {
        $duration = round((microtime(true) - $startTime) * 1000, 2);
        
        Log::info('OBSERVE: Timing measurement', self::createLogEntry([
            'event_type' => 'timing',
            'source' => 'measurement',
            'from_event' => $fromEvent,
            'to_event' => $toEvent,
            'ride_id' => $rideId,
            'duration_ms' => $duration,
            'slow' => $duration > 2000,
        ]));
    }

    /**
     * Sanitize parameters for logging (remove sensitive data)
     */
    private static function sanitizeParams(array $params): array
    {
        $sensitive = ['password', 'token', 'secret', 'otp', 'fcm_token'];
        
        return array_map(function ($value, $key) use ($sensitive) {
            if (in_array(strtolower($key), $sensitive)) {
                return '[REDACTED]';
            }
            if (is_string($value) && strlen($value) > 200) {
                return substr($value, 0, 200) . '...[truncated]';
            }
            return $value;
        }, $params, array_keys($params));
    }
}
