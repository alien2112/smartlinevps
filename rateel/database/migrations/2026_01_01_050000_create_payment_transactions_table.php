<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Payment Transaction States:
     *
     * created          -> Initial state, no gateway interaction yet
     * pending_gateway  -> Request sent to gateway, awaiting response
     * processing       -> Gateway processing, confirmed received
     * paid            -> Payment successful and confirmed
     * failed          -> Payment explicitly failed
     * unknown         -> Gateway error, status unclear (needs reconciliation)
     * refunded        -> Payment was refunded
     * cancelled       -> Payment cancelled before completion
     */
    public function up(): void
    {
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Relations
            $table->uuid('trip_request_id')->index();
            $table->uuid('user_id')->index();
            $table->uuid('payment_request_id')->nullable()->index();

            // Payment Details
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('EGP');
            $table->string('gateway')->default('kashier'); // kashier, stripe, etc.

            // Idempotency & Tracking
            $table->string('idempotency_key')->unique()->index(); // UUID to prevent duplicates
            $table->string('gateway_order_id')->nullable()->unique()->index(); // Kashier order ID
            $table->string('gateway_transaction_id')->nullable()->index(); // Kashier transaction ID

            // State Machine
            $table->enum('status', [
                'created',
                'pending_gateway',
                'processing',
                'paid',
                'failed',
                'unknown',
                'refunded',
                'cancelled'
            ])->default('created')->index();

            $table->enum('previous_status', [
                'created',
                'pending_gateway',
                'processing',
                'paid',
                'failed',
                'unknown',
                'refunded',
                'cancelled'
            ])->nullable();

            // Gateway Response Data
            $table->json('gateway_request')->nullable(); // Request sent to gateway
            $table->json('gateway_response')->nullable(); // Response from gateway
            $table->json('gateway_error')->nullable(); // Error details if any
            $table->text('error_message')->nullable();
            $table->string('error_code')->nullable()->index();

            // Reconciliation
            $table->timestamp('last_reconciliation_at')->nullable()->index();
            $table->integer('reconciliation_attempts')->default(0);
            $table->timestamp('next_reconciliation_at')->nullable()->index();

            // Retry Logic
            $table->integer('retry_count')->default(0);
            $table->timestamp('last_retry_at')->nullable();

            // Timeout Tracking
            $table->timestamp('gateway_sent_at')->nullable(); // When request was sent
            $table->timestamp('gateway_responded_at')->nullable(); // When response received
            $table->integer('response_time_ms')->nullable(); // Response time in milliseconds

            // Locking for distributed systems
            $table->string('lock_token')->nullable()->index();
            $table->timestamp('locked_at')->nullable();
            $table->string('locked_by')->nullable(); // Server/worker ID

            // Webhook verification
            $table->boolean('webhook_received')->default(false);
            $table->timestamp('webhook_received_at')->nullable();
            $table->json('webhook_payload')->nullable();

            // Metadata
            $table->json('metadata')->nullable(); // Additional data (customer info, etc.)

            // Timestamps
            $table->timestamps();
            $table->softDeletes();

            // Foreign keys
            $table->foreign('trip_request_id')->references('id')->on('trip_requests')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            // Composite indexes for common queries
            $table->index(['status', 'created_at']);
            $table->index(['status', 'next_reconciliation_at']);
            $table->index(['gateway_order_id', 'status']);
            $table->index(['idempotency_key', 'status']);
        });

        // Create state transition log table
        Schema::create('payment_state_transitions', function (Blueprint $table) {
            $table->id();
            $table->uuid('payment_transaction_id');
            $table->string('from_state');
            $table->string('to_state');
            $table->string('trigger'); // api_response, reconciliation, webhook, manual
            $table->json('context')->nullable(); // Additional context about transition
            $table->string('transitioned_by')->nullable(); // User/System ID
            $table->timestamp('transitioned_at');

            $table->foreign('payment_transaction_id')
                  ->references('id')
                  ->on('payment_transactions')
                  ->onDelete('cascade');

            $table->index(['payment_transaction_id', 'transitioned_at'], 'pst_ptid_tat_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_state_transitions');
        Schema::dropIfExists('payment_transactions');
    }
};
