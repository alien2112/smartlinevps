<?php

namespace App\Jobs;

use App\Models\PaymentTransaction;
use App\Services\Payment\PaymentReconciliationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ReconcilePaymentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 60;
    public $backoff = [60, 300, 900]; // 1 min, 5 min, 15 min

    private string $paymentId;

    public function __construct(string $paymentId)
    {
        $this->paymentId = $paymentId;
        $this->onQueue('payments-reconciliation');
    }

    public function handle(PaymentReconciliationService $reconciliationService): void
    {
        $payment = PaymentTransaction::find($this->paymentId);

        if (!$payment) {
            Log::warning('Payment not found for reconciliation', [
                'payment_id' => $this->paymentId,
            ]);
            return;
        }

        // Skip if already in final state
        if ($payment->isFinal()) {
            Log::info('Payment already in final state, skipping reconciliation', [
                'payment_id' => $payment->id,
                'status' => $payment->status,
            ]);
            return;
        }

        // Skip if max reconciliation attempts reached
        if ($payment->reconciliation_attempts >= config('payment.reconciliation.max_attempts')) {
            Log::warning('Max reconciliation attempts reached', [
                'payment_id' => $payment->id,
                'attempts' => $payment->reconciliation_attempts,
            ]);

            // Mark as failed after max attempts
            $payment->markAsFailed([
                'code' => 'MAX_RECONCILIATION_ATTEMPTS',
                'message' => 'Could not determine payment status after maximum reconciliation attempts',
            ], 'reconciliation_failed');

            return;
        }

        try {
            $reconciliationService->reconcile($payment);

            Log::info('Payment reconciliation completed', [
                'payment_id' => $payment->id,
                'final_status' => $payment->fresh()->status,
            ]);

        } catch (\Exception $e) {
            Log::error('Payment reconciliation failed', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);

            // Schedule next reconciliation attempt
            $payment->scheduleNextReconciliation();

            throw $e; // Let Laravel retry the job
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Payment reconciliation job failed permanently', [
            'payment_id' => $this->paymentId,
            'error' => $exception->getMessage(),
        ]);

        // Optionally alert monitoring system
    }
}
