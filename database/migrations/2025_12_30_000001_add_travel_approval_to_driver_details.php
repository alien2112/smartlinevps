<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Travel Approval System Migration
 * 
 * Enterprise-grade design:
 * - Travel mode is permission-based, not self-activated
 * - Drivers must request travel privilege and be approved by admin
 * - Only VIP drivers with travel_status = 'approved' can receive travel bookings
 * 
 * Flow: VIP ➜ Request Travel ➜ Admin Approval ➜ Travel Enabled
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('driver_details', function (Blueprint $table) {
            // Travel approval status
            $table->enum('travel_status', ['none', 'requested', 'approved', 'rejected'])
                ->default('none')
                ->after('low_category_trips_date')
                ->comment('Travel privilege status: none=not requested, requested=pending approval, approved=can receive travel bookings, rejected=denied');
            
            // Timestamps for travel status changes
            $table->timestamp('travel_requested_at')->nullable()->after('travel_status')
                ->comment('When driver submitted travel application');
            $table->timestamp('travel_approved_at')->nullable()->after('travel_requested_at')
                ->comment('When admin approved travel privilege');
            $table->timestamp('travel_rejected_at')->nullable()->after('travel_approved_at')
                ->comment('When admin rejected travel application');
            
            // Admin who processed the request
            $table->unsignedBigInteger('travel_processed_by')->nullable()->after('travel_rejected_at')
                ->comment('Admin user ID who approved/rejected');
            
            // Rejection reason (for driver feedback)
            $table->string('travel_rejection_reason')->nullable()->after('travel_processed_by')
                ->comment('Reason for rejection, shown to driver');
            
            // Index for efficient travel driver queries
            $table->index(['travel_status', 'travel_approved_at'], 'idx_travel_approval');
        });
    }

    public function down(): void
    {
        Schema::table('driver_details', function (Blueprint $table) {
            $table->dropIndex('idx_travel_approval');
            $table->dropColumn([
                'travel_status',
                'travel_requested_at',
                'travel_approved_at',
                'travel_rejected_at',
                'travel_processed_by',
                'travel_rejection_reason',
            ]);
        });
    }
};
