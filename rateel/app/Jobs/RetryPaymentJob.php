<?php

namespace App\Jobs;

use App\Models\PaymentTransaction;
use App\Services\Payment\PaymentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RetryPaymentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1; // We handle retries manually
    public $timeout = 60;

    private string $paymentId;

    public function __construct(string $paymentId)
    {
        $this->paymentId = $paymentId;
        $this->onQueue('payments-retry');
    }

    public function handle(PaymentService $paymentService): void
    {
        $payment = PaymentTransaction::find($this->paymentId);

        if (!$payment) {
            Log::warning('Payment not found for retry', [
                'payment_id' => $this->paymentId,
            ]);
            return;
        }

        // Don't retry if already in final state
        if ($payment->isFinal()) {
            Log::info('Payment already in final state, skipping retry', [
                'payment_id' => $payment->id,
                'status' => $payment->status,
            ]);
            return;
        }

        Log::info('Retrying payment', [
            'payment_id' => $payment->id,
            'retry_count' => $payment->retry_count,
        ]);

        try {
            $paymentService->processPayment($payment);

        } catch (\Exception $e) {
            Log::error('Payment retry failed', [
                'payment_id' => $payment->id,
                'retry_count' => $payment->retry_count,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
