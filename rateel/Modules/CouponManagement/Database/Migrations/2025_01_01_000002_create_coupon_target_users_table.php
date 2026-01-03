<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // For TARGETED eligibility - stores which users can use the coupon
        Schema::create('coupon_target_users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('coupon_id')->constrained('coupons')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();

            // Track if user was notified
            $table->boolean('notified')->default(false);
            $table->timestamp('notified_at')->nullable();

            $table->timestamps();

            // Unique constraint: user can only be targeted once per coupon
            $table->unique(['coupon_id', 'user_id']);

            // Index for lookups
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_target_users');
    }
};
