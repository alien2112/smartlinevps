<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Priority 2 indexes for transactions and payment_requests tables
     * Expected impact: Transaction history 10+s -> <200ms
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Transaction history by user (wallet page, statements)
            $table->index(['user_id', 'created_at'], 'idx_transactions_user_created');

            // Transaction type filtering
            $table->index(['transaction_type', 'user_id', 'created_at'], 'idx_transactions_type_user');
        });

        Schema::table('payment_requests', function (Blueprint $table) {
            // Payment tracking
            $table->index(['payer_id', 'is_paid'], 'idx_payments_payer');

            // Payment method filtering
            $table->index(['payment_method', 'is_paid', 'created_at'], 'idx_payments_method');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('idx_transactions_user_created');
            $table->dropIndex('idx_transactions_type_user');
        });

        Schema::table('payment_requests', function (Blueprint $table) {
            $table->dropIndex('idx_payments_payer');
            $table->dropIndex('idx_payments_method');
        });
    }
};
