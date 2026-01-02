<?php

namespace App\Http\Controllers\Api\Payment;

use App\Http\Controllers\Controller;
use App\Models\PaymentTransaction;
use App\Services\Payment\Gateways\KashierGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    /**
     * Handle Kashier webhook
     */
    public function kashier(Request $request): JsonResponse
    {
        Log::info('Kashier webhook received', [
            'payload' => $request->all(),
            'ip' => $request->ip(),
        ]);

        // Verify webhook signature
        if (config('payment.webhook.verify_signature')) {
            if (!$this->verifyKashierSignature($request)) {
                Log::warning('Invalid webhook signature', [
                    'ip' => $request->ip(),
                ]);

                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid signature',
                ], 401);
            }
        }

        // Extract payment info
        $orderId = $request->input('merchantOrderId') ?? $request->input('order_id');
        $status = $request->input('paymentStatus') ?? $request->input('status');
        $transactionId = $request->input('transactionId') ?? $request->input('transaction_id');

        if (!$orderId) {
            Log::error('Webhook missing order ID', [
                'payload' => $request->all(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Missing order ID',
            ], 400);
        }

        // Find payment by gateway order ID or our payment ID
        $payment = PaymentTransaction::byGatewayOrderId($orderId)->first()
                ?? PaymentTransaction::find($orderId);

        if (!$payment) {
            Log::error('Payment not found for webhook', [
                'order_id' => $orderId,
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Payment not found',
            ], 404);
        }

        // Acquire lock to prevent race conditions with reconciliation
        if (!$payment->acquireLock(30)) {
            Log::warning('Could not acquire lock for webhook processing', [
                'payment_id' => $payment->id,
            ]);

            // Return success anyway - webhook will be retried
            return response()->json([
                'status' => 'accepted',
                'message' => 'Payment is being processed',
            ]);
        }

        try {
            // Store webhook data
            $payment->webhook_received = true;
            $payment->webhook_received_at = now();
            $payment->webhook_payload = $request->all();
            $payment->save();

            // Update payment status based on webhook
            $this->updatePaymentFromWebhook($payment, $request->all());

            Log::info('Webhook processed successfully', [
                'payment_id' => $payment->id,
                'status' => $payment->status,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Webhook processed',
            ]);

        } finally {
            $payment->releaseLock();
        }
    }

    /**
     * Verify Kashier webhook signature
     */
    private function verifyKashierSignature(Request $request): bool
    {
        $signature = $request->input('signature') ?? $request->header('X-Kashier-Signature');

        if (!$signature) {
            return false;
        }

        $gateway = new KashierGateway();
        return $gateway->verifyWebhookSignature($request->all(), $signature);
    }

    /**
     * Update payment from webhook data
     */
    private function updatePaymentFromWebhook(PaymentTransaction $payment, array $webhookData): void
    {
        $status = strtoupper($webhookData['paymentStatus'] ?? $webhookData['status'] ?? '');

        // Update gateway IDs if provided
        if (isset($webhookData['orderId']) && !$payment->gateway_order_id) {
            $payment->gateway_order_id = $webhookData['orderId'];
        }

        if (isset($webhookData['transactionId']) && !$payment->gateway_transaction_id) {
            $payment->gateway_transaction_id = $webhookData['transactionId'];
        }

        $payment->save();

        // Don't update if already in final state
        if ($payment->isFinal()) {
            Log::info('Payment already in final state, ignoring webhook', [
                'payment_id' => $payment->id,
                'current_status' => $payment->status,
                'webhook_status' => $status,
            ]);
            return;
        }

        switch ($status) {
            case 'SUCCESS':
            case 'PAID':
            case 'COMPLETED':
                $payment->markAsPaid($webhookData, 'webhook');
                break;

            case 'FAILED':
            case 'DECLINED':
            case 'REJECTED':
                $payment->markAsFailed($webhookData, 'webhook');
                break;

            case 'PENDING':
            case 'PROCESSING':
                if ($payment->canTransitionTo(PaymentTransaction::STATUS_PROCESSING)) {
                    $payment->transitionTo(PaymentTransaction::STATUS_PROCESSING, 'webhook', $webhookData);
                }
                $payment->gateway_response = $webhookData;
                $payment->save();
                break;

            default:
                Log::warning('Unknown webhook status', [
                    'payment_id' => $payment->id,
                    'status' => $status,
                ]);
                break;
        }
    }
}
