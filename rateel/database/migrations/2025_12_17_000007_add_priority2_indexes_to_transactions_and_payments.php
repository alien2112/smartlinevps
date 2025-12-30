<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (!$this->hasIndex('transactions', 'idx_transactions_user_created')) {
                $table->index(['user_id', 'created_at'], 'idx_transactions_user_created');
            }
            if (!$this->hasIndex('transactions', 'idx_transactions_type_user')) {
                $table->index(['transaction_type', 'user_id', 'created_at'], 'idx_transactions_type_user');
            }
        });

        Schema::table('payment_requests', function (Blueprint $table) {
            if (!$this->hasIndex('payment_requests', 'idx_payments_payer')) {
                $table->index(['payer_id', 'is_paid'], 'idx_payments_payer');
            }
            if (!$this->hasIndex('payment_requests', 'idx_payments_method')) {
                $table->index(['payment_method', 'is_paid', 'created_at'], 'idx_payments_method');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            if ($this->hasIndex('transactions', 'idx_transactions_user_created')) {
                $table->dropIndex('idx_transactions_user_created');
            }
            if ($this->hasIndex('transactions', 'idx_transactions_type_user')) {
                $table->dropIndex('idx_transactions_type_user');
            }
        });

        Schema::table('payment_requests', function (Blueprint $table) {
            if ($this->hasIndex('payment_requests', 'idx_payments_payer')) {
                $table->dropIndex('idx_payments_payer');
            }
            if ($this->hasIndex('payment_requests', 'idx_payments_method')) {
                $table->dropIndex('idx_payments_method');
            }
        });
    }

    private function hasIndex($table, $index)
    {
        $results = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = '{$index}'");
        return count($results) > 0;
    }
};