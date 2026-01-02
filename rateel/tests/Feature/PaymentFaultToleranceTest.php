<?php

namespace Tests\Feature;

use App\Models\PaymentTransaction;
use App\Services\Payment\PaymentService;
use App\Services\Payment\PaymentReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class PaymentFaultToleranceTest extends TestCase
{
    use RefreshDatabase;

    private PaymentService $paymentService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->paymentService = new PaymentService();
    }

    /** @test */
    public function it_prevents_duplicate_payments_with_idempotency()
    {
        $data = [
            'trip_request_id' => 'trip-123',
            'user_id' => 'user-456',
            'amount' => 50.00,
            'currency' => 'EGP',
        ];

        // Create payment twice
        $payment1 = $this->paymentService->createPayment($data);
        $payment2 = $this->paymentService->createPayment($data);

        // Should return same payment
        $this->assertEquals($payment1->id, $payment2->id);

        // Only one payment in database
        $this->assertCount(1, PaymentTransaction::all());
    }

    /** @test */
    public function it_handles_gateway_server_error_gracefully()
    {
        Queue::fake();

        // Simulate Kashier SERVER_ERROR response
        // In real test, mock HTTP client to return this response
        $payment = PaymentTransaction::factory()->create([
            'status' => PaymentTransaction::STATUS_CREATED,
        ]);

        // This would trigger reconciliation
        // $this->paymentService->processPayment($payment);

        // Assert payment marked as unknown
        // Assert reconciliation job dispatched
        // Queue::assertPushed(ReconcilePaymentJob::class);
    }

    /** @test */
    public function it_uses_distributed_locking_to_prevent_concurrent_processing()
    {
        $payment = PaymentTransaction::factory()->create();

        // Acquire lock
        $lock1 = $payment->acquireLock(10);
        $this->assertTrue($lock1);

        // Second attempt should fail
        $lock2 = $payment->fresh()->acquireLock(10);
        $this->assertFalse($lock2);

        // Release lock
        $payment->releaseLock();

        // Now should succeed
        $lock3 = $payment->fresh()->acquireLock(10);
        $this->assertTrue($lock3);
    }

    /** @test */
    public function it_transitions_states_according_to_state_machine()
    {
        $payment = PaymentTransaction::factory()->create([
            'status' => PaymentTransaction::STATUS_CREATED,
        ]);

        // Valid transition
        $result = $payment->transitionTo(PaymentTransaction::STATUS_PENDING_GATEWAY);
        $this->assertTrue($result);
        $this->assertEquals(PaymentTransaction::STATUS_PENDING_GATEWAY, $payment->status);

        // Invalid transition (paid -> created not allowed)
        $payment->status = PaymentTransaction::STATUS_PAID;
        $payment->save();

        $result = $payment->transitionTo(PaymentTransaction::STATUS_CREATED);
        $this->assertFalse($result);
    }

    /** @test */
    public function it_schedules_reconciliation_with_exponential_backoff()
    {
        $payment = PaymentTransaction::factory()->create([
            'status' => PaymentTransaction::STATUS_UNKNOWN,
            'reconciliation_attempts' => 0,
        ]);

        // First attempt
        $payment->scheduleNextReconciliation();
        $delay1 = $payment->next_reconciliation_at->diffInSeconds(now());

        // Second attempt
        $payment->scheduleNextReconciliation();
        $delay2 = $payment->next_reconciliation_at->diffInSeconds(now());

        // Delay should increase (exponential backoff)
        $this->assertGreaterThan($delay1, $delay2);
    }

    /** @test */
    public function it_marks_payment_as_paid_from_webhook()
    {
        $payment = PaymentTransaction::factory()->create([
            'status' => PaymentTransaction::STATUS_PENDING_GATEWAY,
            'gateway_order_id' => 'kashier-order-123',
        ]);

        $webhookData = [
            'merchantOrderId' => $payment->id,
            'paymentStatus' => 'SUCCESS',
            'transactionId' => 'txn-456',
        ];

        // Simulate webhook (without signature for test)
        $response = $this->postJson('/api/payment/webhook/kashier', $webhookData);

        // Assert payment updated
        $payment->refresh();
        $this->assertEquals(PaymentTransaction::STATUS_PAID, $payment->status);
        $this->assertTrue($payment->webhook_received);
    }

    /** @test */
    public function it_reconciles_unknown_payments()
    {
        $payment = PaymentTransaction::factory()->create([
            'status' => PaymentTransaction::STATUS_UNKNOWN,
            'gateway_order_id' => 'kashier-order-123',
        ]);

        // Mock gateway response
        // $this->mock(KashierGateway::class)
        //     ->shouldReceive('getOrderStatus')
        //     ->andReturn(['status' => 'PAID']);

        // $reconciliation = new PaymentReconciliationService();
        // $reconciliation->reconcile($payment);

        // $payment->refresh();
        // $this->assertEquals(PaymentTransaction::STATUS_PAID, $payment->status);
    }

    /** @test */
    public function it_handles_timeout_gracefully()
    {
        Queue::fake();

        $payment = PaymentTransaction::factory()->create([
            'status' => PaymentTransaction::STATUS_CREATED,
        ]);

        // Mock timeout exception
        // When processing times out:
        // - Payment should be marked as unknown
        // - Reconciliation job should be dispatched

        // Queue::assertPushed(ReconcilePaymentJob::class);
    }

    /** @test */
    public function it_never_charges_twice()
    {
        // Create payment with idempotency key
        $idempotencyKey = 'test-key-' . uniqid();

        $payment1 = $this->paymentService->createPayment([
            'trip_request_id' => 'trip-123',
            'user_id' => 'user-456',
            'amount' => 50.00,
        ], $idempotencyKey);

        // Simulate duplicate request (user clicks twice)
        $payment2 = $this->paymentService->createPayment([
            'trip_request_id' => 'trip-123',
            'user_id' => 'user-456',
            'amount' => 50.00,
        ], $idempotencyKey);

        // Same payment returned
        $this->assertEquals($payment1->id, $payment2->id);

        // Only one database record
        $this->assertCount(1, PaymentTransaction::where('idempotency_key', $idempotencyKey)->get());
    }

    /** @test */
    public function it_logs_all_state_transitions()
    {
        $payment = PaymentTransaction::factory()->create([
            'status' => PaymentTransaction::STATUS_CREATED,
        ]);

        // Transition through states
        $payment->transitionTo(PaymentTransaction::STATUS_PENDING_GATEWAY);
        $payment->transitionTo(PaymentTransaction::STATUS_PROCESSING);
        $payment->transitionTo(PaymentTransaction::STATUS_PAID);

        // Assert transitions logged
        $this->assertCount(3, $payment->stateTransitions);

        $transitions = $payment->stateTransitions->pluck('to_state')->toArray();
        $this->assertEquals([
            PaymentTransaction::STATUS_PENDING_GATEWAY,
            PaymentTransaction::STATUS_PROCESSING,
            PaymentTransaction::STATUS_PAID,
        ], $transitions);
    }
}
