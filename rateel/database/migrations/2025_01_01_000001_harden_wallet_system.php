<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wallet System Hardening Migration
 *
 * Adds:
 * - Idempotency keys to transactions
 * - Audit fields (created_by, reason)
 * - Webhook deduplication to payment_requests
 * - Indexes for performance
 */
return new class extends Migration
{
    public function up(): void
    {
        // Add idempotency and audit fields to transactions
        Schema::table('transactions', function (Blueprint $table) {
            // Idempotency key - prevents duplicate transactions
            if (!Schema::hasColumn('transactions', 'idempotency_key')) {
                $table->string('idempotency_key', 64)->nullable()->after('trx_ref_id');
            }

            // Audit fields - who initiated the transaction
            if (!Schema::hasColumn('transactions', 'created_by_type')) {
                $table->string('created_by_type', 20)->nullable()->after('idempotency_key');
            }
            if (!Schema::hasColumn('transactions', 'created_by_id')) {
                $table->char('created_by_id', 36)->nullable()->after('created_by_type');
            }
            if (!Schema::hasColumn('transactions', 'admin_reason')) {
                $table->string('admin_reason', 500)->nullable()->after('created_by_id');
            }

            // Transaction status
            if (!Schema::hasColumn('transactions', 'status')) {
                $table->enum('status', ['pending', 'posted', 'failed', 'reversed'])
                    ->default('posted')
                    ->after('admin_reason');
            }

            // Amount in minor units (piastres/cents) for precision
            if (!Schema::hasColumn('transactions', 'amount_minor')) {
                $table->bigInteger('amount_minor')->nullable()->after('balance');
            }
        });

        // Add indexes (check if they don't exist first)
        Schema::table('transactions', function (Blueprint $table) {
            // Unique idempotency key
            $indexes = collect(DB::select("SHOW INDEX FROM transactions"))->pluck('Key_name')->toArray();

            if (!in_array('transactions_idempotency_key_unique', $indexes)) {
                $table->unique('idempotency_key', 'transactions_idempotency_key_unique');
            }

            if (!in_array('idx_transactions_attribute_ref', $indexes)) {
                $table->index(['attribute', 'attribute_id'], 'idx_transactions_attribute_ref');
            }

            if (!in_array('idx_transactions_account_user', $indexes)) {
                $table->index(['account', 'user_id'], 'idx_transactions_account_user');
            }
        });

        // Add webhook deduplication to payment_requests
        Schema::table('payment_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('payment_requests', 'provider_event_id')) {
                $table->string('provider_event_id', 128)->nullable()->after('transaction_id');
            }
            if (!Schema::hasColumn('payment_requests', 'webhook_received_at')) {
                $table->timestamp('webhook_received_at')->nullable();
            }
            if (!Schema::hasColumn('payment_requests', 'webhook_retry_count')) {
                $table->integer('webhook_retry_count')->default(0);
            }
        });

        // Add unique index on provider_event_id
        Schema::table('payment_requests', function (Blueprint $table) {
            $indexes = collect(DB::select("SHOW INDEX FROM payment_requests"))->pluck('Key_name')->toArray();

            if (!in_array('payment_requests_provider_event_id_unique', $indexes)) {
                $table->unique('provider_event_id', 'payment_requests_provider_event_id_unique');
            }
        });

        // Add wallet_balance_minor to user_accounts
        Schema::table('user_accounts', function (Blueprint $table) {
            if (!Schema::hasColumn('user_accounts', 'wallet_balance_minor')) {
                $table->bigInteger('wallet_balance_minor')->default(0)->after('wallet_balance');
            }
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropUnique('transactions_idempotency_key_unique');
            $table->dropIndex('idx_transactions_attribute_ref');
            $table->dropIndex('idx_transactions_account_user');

            $table->dropColumn([
                'idempotency_key',
                'created_by_type',
                'created_by_id',
                'admin_reason',
                'status',
                'amount_minor',
            ]);
        });

        Schema::table('payment_requests', function (Blueprint $table) {
            $table->dropUnique('payment_requests_provider_event_id_unique');
            $table->dropColumn([
                'provider_event_id',
                'webhook_received_at',
                'webhook_retry_count',
            ]);
        });

        Schema::table('user_accounts', function (Blueprint $table) {
            $table->dropColumn('wallet_balance_minor');
        });
    }
};
