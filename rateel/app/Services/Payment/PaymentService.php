<?php

namespace App\Services\Payment;

use App\Models\PaymentTransaction;
use App\Services\Payment\Gateways\KashierGateway;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class PaymentService
{
    private $gateway;

    public function __construct()
    {
        // Factory pattern for multiple gateways
        $this->gateway = $this->resolveGateway(config('payment.default_gateway'));
    }

    /**
     * Create a payment with idempotency protection
     *
     * @param array $data [trip_request_id, user_id, amount, currency, metadata]
     * @param string|null $idempotencyKey Optional idempotency key
     * @return PaymentTransaction
     */
    public function createPayment(array $data, ?string $idempotencyKey = null): PaymentTransaction
    {
        // Generate idempotency key if not provided
        $idempotencyKey = $idempotencyKey ?? $this->generateIdempotencyKey($data);

        // Check for existing payment with this idempotency key
        $existing = PaymentTransaction::byIdempotencyKey($idempotencyKey)->first();

        if ($existing) {
            Log::info('Payment already exists for idempotency key', [
                'idempotency_key' => $idempotencyKey,
                'payment_id' => $existing->id,
                'status' => $existing->status,
            ]);

            return $existing;
        }

        // Create new payment transaction
        return DB::transaction(function () use ($data, $idempotencyKey) {
            $payment = PaymentTransaction::create([
                'trip_request_id' => $data['trip_request_id'],
                'user_id' => $data['user_id'],
                'amount' => $data['amount'],
                'currency' => $data['currency'] ?? 'EGP',
                'gateway' => config('payment.default_gateway'),
                'idempotency_key' => $idempotencyKey,
                'status' => PaymentTransaction::STATUS_CREATED,
                'metadata' => $data['metadata'] ?? null,
            ]);

            Log::info('Payment transaction created', [
                'payment_id' => $payment->id,
                'idempotency_key' => $idempotencyKey,
                'amount' => $payment->amount,
            ]);

            return $payment;
        });
    }

    /**
     * Process payment through gateway with fault tolerance
     *
     * @param PaymentTransaction $payment
     * @return PaymentTransaction
     */
    public function processPayment(PaymentTransaction $payment): PaymentTransaction
    {
        // Prevent processing if already in final state
        if ($payment->isFinal()) {
            Log::warning('Attempted to process payment in final state', [
                'payment_id' => $payment->id,
                'status' => $payment->status,
            ]);

            return $payment;
        }

        // Acquire distributed lock to prevent concurrent processing
        if (!$this->acquirePaymentLock($payment)) {
            Log::warning('Could not acquire lock for payment', [
                'payment_id' => $payment->id,
            ]);

            throw new \RuntimeException('Payment is already being processed');
        }

        try {
            // Prepare gateway request
            $gatewayRequest = $this->prepareGatewayRequest($payment);

            // Mark as pending gateway
            $payment->markAsPendingGateway($gatewayRequest);

            // Send request to gateway with timeout handling
            $response = $this->sendToGatewayWithTimeout($payment, $gatewayRequest);

            // Handle gateway response
            $this->handleGatewayResponse($payment, $response);

        } catch (\GuzzleHttp\Exception\ConnectException $e) {
            // Network error - gateway unreachable
            $this->handleNetworkError($payment, $e);

        } catch (\GuzzleHttp\Exception\RequestException $e) {
            // HTTP error from gateway
            $this->handleGatewayError($payment, $e);

        } catch (\Exception $e) {
            // Unexpected error
            $this->handleUnexpectedError($payment, $e);

        } finally {
            // Always release the lock
            $this->releasePaymentLock($payment);
        }

        return $payment->fresh();
    }

    /**
     * Send request to gateway with timeout handling
     */
    private function sendToGatewayWithTimeout(PaymentTransaction $payment, array $request): array
    {
        // For Kashier, we use hosted payment page - no direct API calls needed
        if (config('payment.default_gateway') === 'kashier') {
            Log::info('Kashier: Skipping direct API call - using hosted payment page', [
                'payment_id' => $payment->id,
            ]);
            
            // Return response indicating hosted payment page should be used
            $payment->gateway_responded_at = now();
            $payment->save();
            
            return [
                'status' => 'PENDING',
                'order_id' => $payment->id,
                'payment_url_required' => true,
                'message' => 'Use hosted payment page URL',
            ];
        }
        
        $timeout = config('payment.timeout.gateway_request');

        try {
            $response = $this->gateway->createOrder($request, $timeout);

            $payment->gateway_responded_at = now();
            $payment->save();

            return $response;

        } catch (\GuzzleHttp\Exception\TimeoutException $e) {
            // Gateway timeout - status unknown
            Log::error('Gateway request timeout', [
                'payment_id' => $payment->id,
                'timeout' => $timeout,
            ]);

            $payment->markAsUnknown([
                'code' => 'GATEWAY_TIMEOUT',
                'message' => 'Gateway did not respond within ' . $timeout . ' seconds',
                'exception' => $e->getMessage(),
            ]);

            // Schedule reconciliation
            dispatch(new \App\Jobs\ReconcilePaymentJob($payment->id))->delay(now()->addMinutes(1));

            throw $e;
        }
    }

    /**
     * Handle gateway response based on status
     */
    private function handleGatewayResponse(PaymentTransaction $payment, array $response): void
    {
        $status = $response['status'] ?? null;

        // Update gateway IDs
        if (isset($response['order_id'])) {
            $payment->gateway_order_id = $response['order_id'];
        }

        if (isset($response['transaction_id'])) {
            $payment->gateway_transaction_id = $response['transaction_id'];
        }

        $payment->save();

        switch (strtoupper($status)) {
            case 'SUCCESS':
            case 'PAID':
            case 'COMPLETED':
                $payment->markAsPaid($response);
                break;

            case 'PENDING':
            case 'PROCESSING':
                $payment->transitionTo(PaymentTransaction::STATUS_PROCESSING, 'gateway_response', $response);
                $payment->scheduleNextReconciliation();
                break;

            case 'FAILED':
            case 'DECLINED':
            case 'REJECTED':
                $payment->markAsFailed($response);
                break;

            case 'SERVER_ERROR':
            case 'GATEWAY_ERROR':
                // Gateway error after receiving request - status unknown
                $this->handleServerError($payment, $response);
                break;

            default:
                // Unknown status
                Log::warning('Unknown gateway response status', [
                    'payment_id' => $payment->id,
                    'status' => $status,
                    'response' => $response,
                ]);

                $payment->markAsUnknown([
                    'code' => 'UNKNOWN_STATUS',
                    'message' => 'Gateway returned unknown status: ' . $status,
                    'response' => $response,
                ]);
                break;
        }
    }

    /**
     * Handle server error from gateway (e.g., getaddrinfo EAI_AGAIN)
     */
    private function handleServerError(PaymentTransaction $payment, array $response): void
    {
        Log::error('Gateway server error - payment status unknown', [
            'payment_id' => $payment->id,
            'response' => $response,
        ]);

        // Mark as unknown - payment might have been processed
        $payment->markAsUnknown([
            'code' => $response['cause'] ?? 'SERVER_ERROR',
            'message' => $response['messages']['en'] ?? 'Gateway server error',
            'response' => $response,
        ]);

        // Schedule immediate reconciliation
        dispatch(new \App\Jobs\ReconcilePaymentJob($payment->id))->delay(now()->addSeconds(30));

        // Alert monitoring system
        $this->sendAlert('Gateway Server Error', $payment, $response);
    }

    /**
     * Handle network error (connection failed, DNS failed, etc.)
     */
    private function handleNetworkError(PaymentTransaction $payment, \Exception $e): void
    {
        Log::error('Network error connecting to gateway', [
            'payment_id' => $payment->id,
            'error' => $e->getMessage(),
        ]);

        // Network failed BEFORE request was sent - safe to retry
        if ($payment->status === PaymentTransaction::STATUS_CREATED) {
            $payment->retry_count = ($payment->retry_count ?? 0) + 1;
            $payment->last_retry_at = now();
            $payment->save();

            // Retry with exponential backoff
            if ($payment->retry_count < config('payment.retry.max_attempts')) {
                $delay = config('payment.retry.delay') * pow(2, $payment->retry_count - 1);
                dispatch(new \App\Jobs\RetryPaymentJob($payment->id))->delay(now()->addSeconds($delay));
            } else {
                $payment->markAsFailed([
                    'code' => 'MAX_RETRIES_EXCEEDED',
                    'message' => 'Maximum retry attempts exceeded',
                ]);
            }
        } else {
            // Request might have been sent - mark as unknown
            $payment->markAsUnknown([
                'code' => 'NETWORK_ERROR',
                'message' => 'Network error during gateway communication',
                'exception' => $e->getMessage(),
            ]);

            dispatch(new \App\Jobs\ReconcilePaymentJob($payment->id))->delay(now()->addMinutes(1));
        }
    }

    /**
     * Handle gateway HTTP error
     */
    private function handleGatewayError(PaymentTransaction $payment, \GuzzleHttp\Exception\RequestException $e): void
    {
        $statusCode = $e->getResponse() ? $e->getResponse()->getStatusCode() : null;
        $responseBody = $e->getResponse() ? (string) $e->getResponse()->getBody() : null;

        Log::error('Gateway HTTP error', [
            'payment_id' => $payment->id,
            'status_code' => $statusCode,
            'response' => $responseBody,
        ]);

        // 4xx errors are usually client errors (invalid request, auth failed)
        if ($statusCode >= 400 && $statusCode < 500) {
            $payment->markAsFailed([
                'code' => 'GATEWAY_CLIENT_ERROR',
                'message' => 'Gateway rejected the request',
                'status_code' => $statusCode,
                'response' => $responseBody,
            ]);
        }
        // 5xx errors are server errors - status unknown
        elseif ($statusCode >= 500) {
            $payment->markAsUnknown([
                'code' => 'GATEWAY_SERVER_ERROR',
                'message' => 'Gateway server error',
                'status_code' => $statusCode,
                'response' => $responseBody,
            ]);

            dispatch(new \App\Jobs\ReconcilePaymentJob($payment->id))->delay(now()->addMinutes(1));
        } else {
            $payment->markAsUnknown([
                'code' => 'GATEWAY_ERROR',
                'message' => 'Unknown gateway error',
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle unexpected error
     */
    private function handleUnexpectedError(PaymentTransaction $payment, \Exception $e): void
    {
        Log::error('Unexpected error processing payment', [
            'payment_id' => $payment->id,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        $payment->markAsUnknown([
            'code' => 'UNEXPECTED_ERROR',
            'message' => 'Unexpected error during payment processing',
            'exception' => $e->getMessage(),
        ]);

        // Alert critical error
        $this->sendAlert('Critical Payment Error', $payment, [
            'exception' => get_class($e),
            'message' => $e->getMessage(),
        ]);
    }

    /**
     * Prepare gateway request payload
     */
    private function prepareGatewayRequest(PaymentTransaction $payment): array
    {
        return [
            'merchant_order_id' => $payment->id,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'customer_id' => $payment->user_id,
            'description' => 'Trip payment for ' . $payment->trip_request_id,
            'metadata' => $payment->metadata,
        ];
    }

    /**
     * Generate idempotency key
     */
    private function generateIdempotencyKey(array $data): string
    {
        // Generate deterministic key based on trip + user + amount
        return hash('sha256', implode('|', [
            $data['trip_request_id'],
            $data['user_id'],
            $data['amount'],
            $data['currency'] ?? 'EGP',
        ]));
    }

    /**
     * Acquire distributed lock for payment
     */
    private function acquirePaymentLock(PaymentTransaction $payment): bool
    {
        if (config('payment.locking.driver') === 'redis') {
            return $this->acquireRedisLock($payment);
        }

        return $payment->acquireLock(config('payment.locking.timeout'));
    }

    /**
     * Acquire Redis lock
     */
    private function acquireRedisLock(PaymentTransaction $payment): bool
    {
        $lockKey = "payment:lock:{$payment->id}";
        $lockToken = Str::uuid()->toString();
        $timeout = config('payment.locking.timeout');

        $acquired = Redis::set($lockKey, $lockToken, 'EX', $timeout, 'NX');

        if ($acquired) {
            $payment->lock_token = $lockToken;
            return true;
        }

        return false;
    }

    /**
     * Release payment lock
     */
    private function releasePaymentLock(PaymentTransaction $payment): void
    {
        if (config('payment.locking.driver') === 'redis') {
            $this->releaseRedisLock($payment);
        } else {
            $payment->releaseLock();
        }
    }

    /**
     * Release Redis lock
     */
    private function releaseRedisLock(PaymentTransaction $payment): void
    {
        if ($payment->lock_token) {
            $lockKey = "payment:lock:{$payment->id}";

            // Only delete if we own the lock (atomic operation)
            $script = "
                if redis.call('get', KEYS[1]) == ARGV[1] then
                    return redis.call('del', KEYS[1])
                else
                    return 0
                end
            ";

            Redis::eval($script, 1, $lockKey, $payment->lock_token);
            $payment->lock_token = null;
        }
    }

    /**
     * Resolve gateway instance
     */
    private function resolveGateway(string $gateway)
    {
        return match($gateway) {
            'kashier' => new KashierGateway(),
            default => throw new \InvalidArgumentException("Unsupported gateway: {$gateway}"),
        };
    }

    /**
     * Send monitoring alert
     */
    private function sendAlert(string $title, PaymentTransaction $payment, array $data): void
    {
        // Implement your alerting logic (Slack, email, etc.)
        Log::critical($title, [
            'payment_id' => $payment->id,
            'status' => $payment->status,
            'data' => $data,
        ]);

        // Example: Slack webhook
        if ($webhook = config('payment.monitoring.slack_webhook')) {
            // Send Slack notification
            // ... implementation ...
        }
    }
}
