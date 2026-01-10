<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * This migration documents the business rule change:
     * Driver wallet_balance can now go negative when cash trip commissions are deducted.
     */
    public function up(): void
    {
        // Add index for quickly finding drivers with negative balances
        Schema::table('user_accounts', function (Blueprint $table) {
            $table->index('wallet_balance', 'idx_wallet_balance_negative');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_accounts', function (Blueprint $table) {
            $table->dropIndex('idx_wallet_balance_negative');
        });
    }
};
