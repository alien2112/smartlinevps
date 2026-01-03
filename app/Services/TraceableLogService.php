<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * TraceableLogService - Deep observability logging with trace correlation
 * 
 * Provides structured logging for debugging delays, duplicates, and missing updates.
 * All logs include trace_id for correlation across HTTP, Socket, Queue, and FCM.
 * 
 * Log Format:
 * {
 *   "trace_id": "abc123",
 *   "user_id": 1,
 *   "driver_id": 2,
 *   "ride_id": 100,
 *   "event_name": "ride_accept",
 *   "source": "http|socket|queue|fcm",
 *   "timestamp": "2024-01-01T12:00:00.000Z",
 *   "duration_ms": 150,
 *   "status": "started|success|failed|timeout"
 * }
 */
class TraceableLogService
{
    private static ?string $currentTraceId = null;

    /**
     * Get or create trace ID for current request/job
     */
    public static function getTraceId(): string
    {
        if (self::$currentTraceId === null) {
            self::$currentTraceId = request()->header('X-Correlation-ID') 
                ?? request()->header('X-Trace-ID')
                ?? (string) Str::uuid();
        }
        return self::$currentTraceId;
    }

    /**
     * Set trace ID (useful for queue jobs receiving trace from dispatch)
     */
    public static function setTraceId(string $traceId): void
    {
        self::$currentTraceId = $traceId;
    }

    /**
     * Generate a new trace ID
     */
    public static function newTraceId(): string
    {
        self::$currentTraceId = (string) Str::uuid();
        return self::$currentTraceId;
    }

    /**
     * Build base context for all logs
     */
    private static function baseContext(string $eventName, string $source, string $status, array $extra = []): array
    {
        return array_merge([
            'trace_id' => self::getTraceId(),
            'event_name' => $eventName,
            'source' => $source,
            'status' => $status,
            'timestamp' => now()->toIso8601String(),
            'timestamp_ms' => (int)(microtime(true) * 1000),
        ], $extra);
    }

    // ========================================
    // HTTP REQUEST LIFECYCLE
    // ========================================

    /**
     * Log HTTP request started
     */
    public static function httpRequestStarted(string $endpoint, array $extra = []): float
    {
        $startTime = microtime(true);
        
        $context = self::baseContext('http_request', 'http', 'started', array_merge([
            'endpoint' => $endpoint,
            'method' => request()->method(),
            'user_id' => auth('api')->id(),
            'ip' => request()->ip(),
        ], $extra));

        Log::channel('daily_json')->info('http_request_started', $context);
        
        return $startTime;
    }

    /**
     * Log HTTP request completed
     */
    public static function httpRequestCompleted(string $endpoint, float $startTime, int $statusCode, array $extra = []): void
    {
        $durationMs = round((microtime(true) - $startTime) * 1000, 2);
        
        $context = self::baseContext('http_request', 'http', $statusCode >= 400 ? 'failed' : 'success', array_merge([
            'endpoint' => $endpoint,
            'duration_ms' => $durationMs,
            'status_code' => $statusCode,
            'user_id' => auth('api')->id(),
        ], $extra));

        // Flag slow requests
        if ($durationMs > 1000) {
            $context['slow_request'] = true;
        }

        Log::channel('daily_json')->info('http_request_completed', $context);
    }

    // ========================================
    // RIDE/TRIP LIFECYCLE
    // ========================================

    /**
     * Log ride event (creation, acceptance, start, completion, cancellation)
     */
    public static function rideEvent(
        string $eventName,
        string $status,
        ?int $rideId = null,
        ?int $customerId = null,
        ?int $driverId = null,
        ?float $startTime = null,
        array $extra = []
    ): void {
        $context = self::baseContext($eventName, $extra['source'] ?? 'http', $status, [
            'ride_id' => $rideId,
            'customer_id' => $customerId,
            'driver_id' => $driverId,
        ]);

        if ($startTime !== null) {
            $context['duration_ms'] = round((microtime(true) - $startTime) * 1000, 2);
        }

        $context = array_merge($context, $extra);

        Log::channel('daily_json')->info('ride_event', $context);
    }

    /**
     * Log ride acceptance flow with detailed timing
     */
    public static function rideAcceptanceStarted(int $rideId, int $driverId): float
    {
        $startTime = microtime(true);
        
        self::rideEvent('ride_accept', 'started', $rideId, null, $driverId, null, [
            'step' => 'driver_initiated_accept',
        ]);

        return $startTime;
    }

    public static function rideAcceptanceLockAcquired(int $rideId, int $driverId, float $startTime): void
    {
        self::rideEvent('ride_accept_lock', 'success', $rideId, null, $driverId, $startTime, [
            'step' => 'lock_acquired',
        ]);
    }

    public static function rideAcceptanceLockFailed(int $rideId, int $driverId, string $reason, float $startTime): void
    {
        self::rideEvent('ride_accept_lock', 'failed', $rideId, null, $driverId, $startTime, [
            'step' => 'lock_failed',
            'reason' => $reason,
        ]);
    }

    public static function rideAcceptanceCompleted(int $rideId, int $driverId, int $customerId, float $startTime): void
    {
        self::rideEvent('ride_accept', 'success', $rideId, $customerId, $driverId, $startTime, [
            'step' => 'acceptance_complete',
        ]);
    }

    // ========================================
    // OTP VERIFICATION LIFECYCLE
    // ========================================

    /**
     * Log OTP verification started
     */
    public static function otpVerificationStarted(int $rideId, int $driverId): float
    {
        $startTime = microtime(true);
        
        $context = self::baseContext('otp_verification', 'http', 'started', [
            'ride_id' => $rideId,
            'driver_id' => $driverId,
            'step' => 'otp_submitted',
        ]);

        Log::channel('daily_json')->info('otp_verification_started', $context);
        
        return $startTime;
    }

    /**
     * Log OTP verification result
     */
    public static function otpVerificationCompleted(
        int $rideId,
        int $driverId,
        bool $success,
        float $startTime,
        ?string $failureReason = null
    ): void {
        $durationMs = round((microtime(true) - $startTime) * 1000, 2);
        
        $context = self::baseContext('otp_verification', 'http', $success ? 'success' : 'failed', [
            'ride_id' => $rideId,
            'driver_id' => $driverId,
            'duration_ms' => $durationMs,
            'step' => 'otp_verified',
        ]);

        if (!$success && $failureReason) {
            $context['failure_reason'] = $failureReason;
        }

        Log::channel('daily_json')->info('otp_verification_completed', $context);
    }

    // ========================================
    // FCM/PUSH NOTIFICATION LIFECYCLE
    // ========================================

    /**
     * Log FCM notification dispatch started
     */
    public static function fcmDispatchStarted(
        string $notificationType,
        ?int $userId = null,
        ?int $rideId = null,
        array $extra = []
    ): float {
        $startTime = microtime(true);
        
        $context = self::baseContext('fcm_dispatch', 'fcm', 'started', array_merge([
            'notification_type' => $notificationType,
            'target_user_id' => $userId,
            'ride_id' => $rideId,
        ], $extra));

        Log::channel('daily_json')->info('fcm_dispatch_started', $context);
        
        return $startTime;
    }

    /**
     * Log FCM notification send result
     */
    public static function fcmDispatchCompleted(
        string $notificationType,
        bool $success,
        float $startTime,
        ?int $userId = null,
        ?int $rideId = null,
        array $extra = []
    ): void {
        $durationMs = round((microtime(true) - $startTime) * 1000, 2);
        
        $context = self::baseContext('fcm_dispatch', 'fcm', $success ? 'success' : 'failed', array_merge([
            'notification_type' => $notificationType,
            'target_user_id' => $userId,
            'ride_id' => $rideId,
            'duration_ms' => $durationMs,
        ], $extra));

        Log::channel('daily_json')->info('fcm_dispatch_completed', $context);
    }

    // ========================================
    // QUEUE JOB LIFECYCLE
    // ========================================

    /**
     * Log queue job dispatched
     */
    public static function queueJobDispatched(string $jobName, array $payload = []): void
    {
        $context = self::baseContext('queue_job_dispatch', 'queue', 'started', [
            'job_name' => $jobName,
            'payload_keys' => array_keys($payload),
            'dispatched_at' => now()->toIso8601String(),
        ]);

        // Include safe payload data
        if (isset($payload['ride_id'])) $context['ride_id'] = $payload['ride_id'];
        if (isset($payload['trip_request_id'])) $context['ride_id'] = $payload['trip_request_id'];
        if (isset($payload['user_id'])) $context['target_user_id'] = $payload['user_id'];
        if (isset($payload['driver_id'])) $context['driver_id'] = $payload['driver_id'];

        Log::channel('queue')->info('queue_job_dispatched', $context);
    }

    /**
     * Log queue job execution started
     */
    public static function queueJobStarted(string $jobName, array $extra = []): float
    {
        $startTime = microtime(true);
        
        $context = self::baseContext('queue_job_execute', 'queue', 'started', array_merge([
            'job_name' => $jobName,
            'worker_id' => gethostname() . ':' . getmypid(),
        ], $extra));

        Log::channel('queue')->info('queue_job_started', $context);
        
        return $startTime;
    }

    /**
     * Log queue job execution completed
     */
    public static function queueJobCompleted(string $jobName, bool $success, float $startTime, array $extra = []): void
    {
        $durationMs = round((microtime(true) - $startTime) * 1000, 2);
        
        $context = self::baseContext('queue_job_execute', 'queue', $success ? 'success' : 'failed', array_merge([
            'job_name' => $jobName,
            'duration_ms' => $durationMs,
            'worker_id' => gethostname() . ':' . getmypid(),
        ], $extra));

        Log::channel('queue')->info('queue_job_completed', $context);
    }

    // ========================================
    // DATABASE TRANSACTION LIFECYCLE
    // ========================================

    /**
     * Log database transaction started
     */
    public static function dbTransactionStarted(string $operation, ?int $rideId = null): float
    {
        $startTime = microtime(true);
        
        $context = self::baseContext('db_transaction', 'http', 'started', [
            'operation' => $operation,
            'ride_id' => $rideId,
        ]);

        Log::channel('daily_json')->debug('db_transaction_started', $context);
        
        return $startTime;
    }

    /**
     * Log database transaction committed
     */
    public static function dbTransactionCommitted(string $operation, float $startTime, ?int $rideId = null): void
    {
        $durationMs = round((microtime(true) - $startTime) * 1000, 2);
        
        $context = self::baseContext('db_transaction', 'http', 'success', [
            'operation' => $operation,
            'ride_id' => $rideId,
            'duration_ms' => $durationMs,
            'action' => 'committed',
        ]);

        Log::channel('daily_json')->debug('db_transaction_committed', $context);
    }

    /**
     * Log database transaction rolled back
     */
    public static function dbTransactionRolledBack(string $operation, float $startTime, ?string $reason = null, ?int $rideId = null): void
    {
        $durationMs = round((microtime(true) - $startTime) * 1000, 2);
        
        $context = self::baseContext('db_transaction', 'http', 'failed', [
            'operation' => $operation,
            'ride_id' => $rideId,
            'duration_ms' => $durationMs,
            'action' => 'rolled_back',
            'reason' => $reason,
        ]);

        Log::channel('daily_json')->warning('db_transaction_rolled_back', $context);
    }

    // ========================================
    // SOCKET/REALTIME EVENT LIFECYCLE
    // ========================================

    /**
     * Log socket event received (from Laravel side)
     */
    public static function socketEventReceived(string $eventName, array $data = []): void
    {
        $context = self::baseContext($eventName, 'socket', 'started', [
            'data_keys' => array_keys($data),
        ]);

        if (isset($data['ride_id'])) $context['ride_id'] = $data['ride_id'];
        if (isset($data['driver_id'])) $context['driver_id'] = $data['driver_id'];
        if (isset($data['customer_id'])) $context['customer_id'] = $data['customer_id'];

        Log::channel('websocket')->info('socket_event_received', $context);
    }

    /**
     * Log socket event emitted (from Laravel to Node via Redis)
     */
    public static function socketEventEmitted(string $eventName, string $channel, array $data = []): void
    {
        $context = self::baseContext($eventName, 'socket', 'success', [
            'channel' => $channel,
            'data_keys' => array_keys($data),
        ]);

        if (isset($data['ride_id'])) $context['ride_id'] = $data['ride_id'];
        if (isset($data['driver_id'])) $context['driver_id'] = $data['driver_id'];
        if (isset($data['customer_id'])) $context['customer_id'] = $data['customer_id'];

        Log::channel('websocket')->info('socket_event_emitted', $context);
    }

    // ========================================
    // TIMING HELPERS
    // ========================================

    /**
     * Measure and log time between two events
     */
    public static function measureTimeBetween(
        string $fromEvent,
        string $toEvent,
        float $startTime,
        ?int $rideId = null,
        array $extra = []
    ): void {
        $durationMs = round((microtime(true) - $startTime) * 1000, 2);
        
        $context = self::baseContext('timing_measurement', 'http', 'success', array_merge([
            'from_event' => $fromEvent,
            'to_event' => $toEvent,
            'duration_ms' => $durationMs,
            'ride_id' => $rideId,
        ], $extra));

        // Flag if delay is concerning
        if ($durationMs > 2000) {
            $context['delay_warning'] = true;
            Log::channel('performance')->warning('timing_delay_detected', $context);
        }

        Log::channel('daily_json')->info('timing_measurement', $context);
    }

    // ========================================
    // VALIDATION LIFECYCLE
    // ========================================

    /**
     * Log validation started
     */
    public static function validationStarted(string $context): float
    {
        $startTime = microtime(true);
        
        Log::channel('daily_json')->debug('validation_started', self::baseContext('validation', 'http', 'started', [
            'validation_context' => $context,
        ]));
        
        return $startTime;
    }

    /**
     * Log validation completed
     */
    public static function validationCompleted(string $context, bool $passed, float $startTime, array $errors = []): void
    {
        $durationMs = round((microtime(true) - $startTime) * 1000, 2);
        
        $logContext = self::baseContext('validation', 'http', $passed ? 'success' : 'failed', [
            'validation_context' => $context,
            'duration_ms' => $durationMs,
        ]);

        if (!$passed && !empty($errors)) {
            $logContext['validation_errors'] = array_keys($errors);
        }

        Log::channel('daily_json')->debug('validation_completed', $logContext);
    }

    // ========================================
    // DUPLICATE DETECTION
    // ========================================

    /**
     * Log potential duplicate action detection
     */
    public static function duplicateActionDetected(
        string $actionType,
        ?int $rideId = null,
        ?int $userId = null,
        array $extra = []
    ): void {
        $context = self::baseContext('duplicate_action', 'http', 'warning', array_merge([
            'action_type' => $actionType,
            'ride_id' => $rideId,
            'user_id' => $userId,
        ], $extra));

        Log::channel('security')->warning('duplicate_action_detected', $context);
    }

    /**
     * Log out-of-order event detection
     */
    public static function outOfOrderEventDetected(
        string $expectedStatus,
        string $actualStatus,
        ?int $rideId = null,
        array $extra = []
    ): void {
        $context = self::baseContext('out_of_order_event', 'http', 'warning', array_merge([
            'expected_status' => $expectedStatus,
            'actual_status' => $actualStatus,
            'ride_id' => $rideId,
        ], $extra));

        Log::channel('security')->warning('out_of_order_event_detected', $context);
    }
}
