<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * LogService - Helper class for structured event logging
 * Provides convenient methods for logging business events with consistent structure
 */
class LogService
{
    /**
     * Log a trip lifecycle event
     *
     * @param string $event Event name (e.g., 'trip_created', 'trip_accepted', 'trip_completed')
     * @param mixed $trip Trip model instance
     * @param array $extra Additional context data
     * @return void
     */
    public static function tripEvent(string $event, $trip, array $extra = []): void
    {
        $context = array_merge([
            'event' => $event,
            'trip_id' => $trip->id ?? null,
            'customer_id' => $trip->customer_id ?? null,
            'driver_id' => $trip->driver_id ?? null,
            'status' => $trip->current_status ?? null,
            'vehicle_category_id' => $trip->vehicle_category_id ?? null,
            'zone_id' => $trip->zone_id ?? null,
        ], $extra);

        Log::channel('daily_json')->info('trip_event', $context);
    }

    /**
     * Log an authentication event
     *
     * @param string $event Event name (e.g., 'login_success', 'login_failed', 'logout', 'otp_sent')
     * @param mixed $user User model instance (optional)
     * @param array $extra Additional context data
     * @return void
     */
    public static function authEvent(string $event, $user = null, array $extra = []): void
    {
        $context = array_merge([
            'event' => $event,
            'user_id' => $user->id ?? null,
            'user_type' => $user->user_type ?? null,
            'phone' => $user->phone ?? null,
            'ip' => request()->ip(),
        ], $extra);

        // Remove sensitive data
        unset($context['password'], $context['otp']);

        Log::channel('security')->info('auth_event', $context);
    }

    /**
     * Log a payment event
     *
     * @param string $event Event name (e.g., 'payment_initiated', 'payment_success', 'payment_failed')
     * @param mixed $trip Trip model instance
     * @param array $extra Additional context data
     * @return void
     */
    public static function paymentEvent(string $event, $trip, array $extra = []): void
    {
        $context = array_merge([
            'event' => $event,
            'trip_id' => $trip->id ?? null,
            'customer_id' => $trip->customer_id ?? null,
            'driver_id' => $trip->driver_id ?? null,
            'amount' => $trip->actual_fare ?? $trip->estimated_fare ?? null,
            'currency' => businessConfig('currency_code')?->value ?? 'USD',
            'payment_method' => $trip->payment_method ?? null,
        ], $extra);

        // Never log full card numbers or CVV
        if (isset($context['card_number'])) {
            $context['card_last4'] = substr($context['card_number'], -4);
            unset($context['card_number']);
        }
        unset($context['cvv'], $context['card_cvv']);

        Log::channel('finance')->info('payment_event', $context);
    }

    /**
     * Log a driver action event
     *
     * @param string $event Event name (e.g., 'driver_online', 'driver_offline', 'location_updated')
     * @param mixed $driver Driver/User model instance
     * @param array $extra Additional context data
     * @return void
     */
    public static function driverEvent(string $event, $driver, array $extra = []): void
    {
        $context = array_merge([
            'event' => $event,
            'driver_id' => $driver->id ?? null,
            'vehicle_id' => $driver->vehicle?->id ?? null,
            'zone_id' => $driver->userZone?->zone_id ?? null,
        ], $extra);

        // Sanitize coordinates if present
        if (isset($context['latitude']) && isset($context['longitude'])) {
            $context['location'] = [
                'lat' => round($context['latitude'], 6),
                'lng' => round($context['longitude'], 6),
            ];
            unset($context['latitude'], $context['longitude']);
        }

        Log::channel('daily_json')->info('driver_event', $context);
    }

    /**
     * Log a WebSocket event
     *
     * @param string $event Event name (e.g., 'connection_opened', 'message_sent', 'connection_closed')
     * @param array $extra Additional context data
     * @return void
     */
    public static function websocketEvent(string $event, array $extra = []): void
    {
        $context = array_merge([
            'event' => $event,
        ], $extra);

        Log::channel('websocket')->info('websocket_event', $context);
    }

    /**
     * Log a queue/job event
     *
     * @param string $event Event name (e.g., 'job_started', 'job_completed', 'job_failed')
     * @param string $jobName Name of the job/queue
     * @param array $extra Additional context data
     * @return void
     */
    public static function queueEvent(string $event, string $jobName, array $extra = []): void
    {
        $context = array_merge([
            'event' => $event,
            'job_name' => $jobName,
        ], $extra);

        Log::channel('queue')->info('queue_event', $context);
    }

    /**
     * Log an API error
     *
     * @param \Throwable $exception Exception instance
     * @param string|null $context Additional context string
     * @return void
     */
    public static function apiError(\Throwable $exception, ?string $context = null): void
    {
        Log::error('api_error', [
            'error_type' => get_class($exception),
            'message' => $exception->getMessage(),
            'code' => $exception->getCode(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'context' => $context,
            'trace' => $exception->getTraceAsString(),
        ]);
    }

    /**
     * Log a security event (suspicious activity, rate limit exceeded, etc.)
     *
     * @param string $event Event name (e.g., 'rate_limit_exceeded', 'suspicious_activity', 'invalid_token')
     * @param array $extra Additional context data
     * @return void
     */
    public static function securityEvent(string $event, array $extra = []): void
    {
        $context = array_merge([
            'event' => $event,
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ], $extra);

        Log::channel('security')->warning('security_event', $context);
    }

    /**
     * Log a performance metric
     *
     * @param string $metric Metric name (e.g., 'db_query_slow', 'api_response_slow')
     * @param float $value Metric value (e.g., duration in milliseconds)
     * @param array $extra Additional context data
     * @return void
     */
    public static function performanceMetric(string $metric, float $value, array $extra = []): void
    {
        $context = array_merge([
            'metric' => $metric,
            'value' => round($value, 2),
        ], $extra);

        Log::channel('performance')->info('performance_metric', $context);
    }

    /**
     * Log a business metric (for analytics)
     *
     * @param string $metric Metric name (e.g., 'trip_completed', 'driver_registered')
     * @param mixed $value Metric value
     * @param array $extra Additional context data
     * @return void
     */
    public static function businessMetric(string $metric, $value, array $extra = []): void
    {
        $context = array_merge([
            'metric' => $metric,
            'value' => $value,
            'timestamp' => now()->toIso8601String(),
        ], $extra);

        Log::channel('daily_json')->info('business_metric', $context);
    }

    /**
     * Log external API call
     *
     * @param string $service Service name (e.g., 'geolink', 'firebase', 'stripe')
     * @param string $endpoint API endpoint
     * @param array $extra Additional context data
     * @return void
     */
    public static function externalApiCall(string $service, string $endpoint, array $extra = []): void
    {
        $context = array_merge([
            'event' => 'external_api_call',
            'service' => $service,
            'endpoint' => $endpoint,
        ], $extra);

        // Remove sensitive data
        unset($context['api_key'], $context['secret'], $context['token']);

        Log::channel('daily_json')->info('external_api_call', $context);
    }
}
