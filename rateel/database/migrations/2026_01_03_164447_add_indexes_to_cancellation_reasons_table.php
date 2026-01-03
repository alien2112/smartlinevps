<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('cancellation_reasons', function (Blueprint $table) {
            // Composite index for the most common query pattern: user_type + is_active + cancellation_type
            // This will significantly speed up the cancellationReasonList() endpoint
            $table->index(['user_type', 'is_active', 'cancellation_type'], 'idx_cancellation_reasons_lookup');
            
            // Individual indexes for flexibility
            $table->index('user_type', 'idx_cancellation_reasons_user_type');
            $table->index('is_active', 'idx_cancellation_reasons_is_active');
            $table->index('cancellation_type', 'idx_cancellation_reasons_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cancellation_reasons', function (Blueprint $table) {
            $table->dropIndex('idx_cancellation_reasons_lookup');
            $table->dropIndex('idx_cancellation_reasons_user_type');
            $table->dropIndex('idx_cancellation_reasons_is_active');
            $table->dropIndex('idx_cancellation_reasons_type');
        });
    }
};
