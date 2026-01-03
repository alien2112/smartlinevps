<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupon_redemptions', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('coupon_id')->constrained('coupons')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('ride_id')->constrained('trip_requests')->cascadeOnDelete();

            // Idempotency key to prevent duplicate reservations
            $table->string('idempotency_key', 100);

            // Status: RESERVED, APPLIED, CANCELLED, EXPIRED
            $table->enum('status', ['RESERVED', 'APPLIED', 'CANCELLED', 'EXPIRED'])->default('RESERVED');

            // Discount calculation
            $table->decimal('estimated_fare', 10, 2)->nullable();
            $table->decimal('estimated_discount', 10, 2)->nullable();
            $table->decimal('final_fare', 10, 2)->nullable();
            $table->decimal('final_discount', 10, 2)->nullable();

            // Context at time of reservation
            $table->string('city_id', 50)->nullable();
            $table->string('service_type', 50)->nullable();

            // Timestamps
            $table->timestamp('reserved_at')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            $table->timestamps();

            // CRITICAL: Only one active (RESERVED/APPLIED) redemption per ride
            // We use a unique index on ride_id + a partial approach via app logic
            // Since MySQL doesn't support partial unique indexes, we ensure:
            // 1. Only one RESERVED or APPLIED status per ride via app logic
            // 2. Unique index on ride_id ensures only one redemption record per ride
            $table->unique('ride_id', 'unique_ride_redemption');

            // Idempotency: same user+idempotency_key can only reserve once
            $table->unique(['user_id', 'idempotency_key'], 'unique_user_idempotency');

            // Indexes for queries
            $table->index('coupon_id');
            $table->index('user_id');
            $table->index('status');
            $table->index(['coupon_id', 'user_id', 'status']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_redemptions');
    }
};
