<?php

namespace App\Services\Payment;

use App\Models\PaymentTransaction;
use App\Services\Payment\Gateways\KashierGateway;
use Illuminate\Support\Facades\Log;

class PaymentReconciliationService
{
    private $gateway;

    public function __construct()
    {
        // In production, use factory pattern for multiple gateways
        $this->gateway = new KashierGateway();
    }

    /**
     * Reconcile a single payment
     */
    public function reconcile(PaymentTransaction $payment): void
    {
        Log::info('Starting payment reconciliation', [
            'payment_id' => $payment->id,
            'current_status' => $payment->status,
            'gateway_order_id' => $payment->gateway_order_id,
            'attempt' => $payment->reconciliation_attempts + 1,
        ]);

        // Acquire lock to prevent concurrent reconciliation
        if (!$payment->acquireLock(30)) {
            Log::warning('Could not acquire lock for reconciliation', [
                'payment_id' => $payment->id,
            ]);
            return;
        }

        try {
            // Fetch current status from gateway
            $gatewayStatus = $this->fetchGatewayStatus($payment);

            // Update payment based on gateway status
            $this->updatePaymentFromGatewayStatus($payment, $gatewayStatus);

            // Update reconciliation metadata
            $payment->last_reconciliation_at = now();
            $payment->reconciliation_attempts = ($payment->reconciliation_attempts ?? 0) + 1;

            // If still not final, schedule next attempt
            if (!$payment->isFinal()) {
                $payment->scheduleNextReconciliation();
            } else {
                $payment->next_reconciliation_at = null;
            }

            $payment->save();

        } finally {
            $payment->releaseLock();
        }
    }

    /**
     * Fetch payment status from gateway
     */
    private function fetchGatewayStatus(PaymentTransaction $payment): array
    {
        if (!$payment->gateway_order_id) {
            // No gateway order ID yet - order might not have been created
            Log::warning('No gateway order ID for reconciliation', [
                'payment_id' => $payment->id,
            ]);

            return [
                'status' => 'NOT_FOUND',
                'message' => 'No gateway order ID available',
            ];
        }

        // For Kashier, status comes from webhook/callback, not API
        if (config('payment.default_gateway') === 'kashier') {
            Log::info('Kashier: Skipping API status check - status from webhook/callback', [
                'payment_id' => $payment->id,
            ]);
            
            return [
                'status' => 'UNKNOWN',
                'order_id' => $payment->gateway_order_id,
                'message' => 'Status check via API not available for Kashier - check webhook/callback',
            ];
        }
        
        try {
            return $this->gateway->getOrderStatus($payment->gateway_order_id);

        } catch (\GuzzleHttp\Exception\ConnectException $e) {
            // Network error - gateway unreachable
            Log::error('Network error during reconciliation', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 'NETWORK_ERROR',
                'message' => 'Could not connect to gateway',
                'error' => $e->getMessage(),
            ];

        } catch (\GuzzleHttp\Exception\RequestException $e) {
            // HTTP error from gateway
            $statusCode = $e->getResponse() ? $e->getResponse()->getStatusCode() : 0;

            Log::error('Gateway error during reconciliation', [
                'payment_id' => $payment->id,
                'status_code' => $statusCode,
            ]);

            if ($statusCode === 404) {
                return [
                    'status' => 'NOT_FOUND',
                    'message' => 'Order not found at gateway',
                ];
            }

            return [
                'status' => 'GATEWAY_ERROR',
                'message' => 'Gateway returned error',
                'status_code' => $statusCode,
            ];

        } catch (\Exception $e) {
            Log::error('Unexpected error during reconciliation', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 'ERROR',
                'message' => 'Unexpected error',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Update payment status based on gateway response
     */
    private function updatePaymentFromGatewayStatus(PaymentTransaction $payment, array $gatewayStatus): void
    {
        $status = $gatewayStatus['status'] ?? 'UNKNOWN';

        Log::info('Updating payment from gateway status', [
            'payment_id' => $payment->id,
            'current_status' => $payment->status,
            'gateway_status' => $status,
        ]);

        switch (strtoupper($status)) {
            case 'SUCCESS':
            case 'PAID':
            case 'COMPLETED':
                // Payment was successful
                $payment->markAsPaid($gatewayStatus, 'reconciliation');
                break;

            case 'FAILED':
            case 'DECLINED':
            case 'REJECTED':
            case 'CANCELLED':
                // Payment failed
                $payment->markAsFailed($gatewayStatus, 'reconciliation');
                break;

            case 'PENDING':
            case 'PROCESSING':
                // Still processing - keep checking
                if ($payment->status !== PaymentTransaction::STATUS_PROCESSING) {
                    $payment->transitionTo(PaymentTransaction::STATUS_PROCESSING, 'reconciliation', $gatewayStatus);
                }
                $payment->gateway_response = $gatewayStatus;
                $payment->save();
                break;

            case 'NOT_FOUND':
                // Order not found at gateway
                $this->handleNotFound($payment, $gatewayStatus);
                break;

            case 'NETWORK_ERROR':
            case 'GATEWAY_ERROR':
            case 'ERROR':
                // Reconciliation failed due to error - try again later
                Log::warning('Reconciliation encountered error, will retry', [
                    'payment_id' => $payment->id,
                    'error' => $gatewayStatus['message'] ?? null,
                ]);
                // Keep current status, will retry
                break;

            default:
                // Unknown status
                Log::warning('Unknown gateway status during reconciliation', [
                    'payment_id' => $payment->id,
                    'gateway_status' => $status,
                ]);

                if ($payment->status === PaymentTransaction::STATUS_UNKNOWN) {
                    // Still unknown after reconciliation
                    $payment->gateway_response = $gatewayStatus;
                    $payment->save();
                }
                break;
        }
    }

    /**
     * Handle case where order not found at gateway
     */
    private function handleNotFound(PaymentTransaction $payment, array $gatewayStatus): void
    {
        // If payment is very recent, order might not be created yet
        $ageInMinutes = $payment->created_at->diffInMinutes(now());

        if ($ageInMinutes < 5) {
            // Payment is recent, order might still be processing
            Log::info('Recent payment not found, will check again later', [
                'payment_id' => $payment->id,
                'age_minutes' => $ageInMinutes,
            ]);
            return;
        }

        // Order not found after reasonable time - likely failed to create
        if ($payment->reconciliation_attempts >= 3) {
            Log::warning('Order not found at gateway after multiple attempts', [
                'payment_id' => $payment->id,
                'attempts' => $payment->reconciliation_attempts,
            ]);

            $payment->markAsFailed([
                'code' => 'ORDER_NOT_FOUND',
                'message' => 'Order not found at gateway',
                'gateway_response' => $gatewayStatus,
            ], 'reconciliation');
        }
    }

    /**
     * Reconcile all pending payments (batch processing)
     */
    public function reconcileAll(int $limit = null): int
    {
        $limit = $limit ?? config('payment.reconciliation.batch_size');

        $payments = PaymentTransaction::needsReconciliation()
            ->orderBy('next_reconciliation_at', 'asc')
            ->limit($limit)
            ->get();

        Log::info('Starting batch reconciliation', [
            'count' => $payments->count(),
            'limit' => $limit,
        ]);

        $reconciled = 0;

        foreach ($payments as $payment) {
            try {
                $this->reconcile($payment);
                $reconciled++;

            } catch (\Exception $e) {
                Log::error('Batch reconciliation error', [
                    'payment_id' => $payment->id,
                    'error' => $e->getMessage(),
                ]);
                // Continue with next payment
            }
        }

        Log::info('Batch reconciliation completed', [
            'reconciled' => $reconciled,
            'total' => $payments->count(),
        ]);

        return $reconciled;
    }
}
