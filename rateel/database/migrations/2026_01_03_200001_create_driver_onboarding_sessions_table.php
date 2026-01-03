<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * V2 Driver Onboarding - Sessions table for secure OTP handling
 *
 * This table tracks onboarding sessions independently from the user record
 * to support pre-registration OTP verification without exposing phone numbers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_onboarding_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Public-facing session identifier (onb_xxxx format)
            $table->string('onboarding_id', 24)->unique();

            // Phone tracking (hashed for lookup, encrypted for display)
            $table->string('phone', 20);
            $table->string('phone_hash', 64)->index(); // SHA-256 for secure lookup

            // Link to user once created
            $table->uuid('driver_id')->nullable();
            $table->foreign('driver_id')->references('id')->on('users')->nullOnDelete();

            // Device and IP tracking for rate limiting
            $table->string('device_id', 100)->nullable();
            $table->string('ip_address', 45);
            $table->string('user_agent', 500)->nullable();

            // OTP management
            $table->string('otp_hash', 255)->nullable();
            $table->timestamp('otp_expires_at')->nullable();
            $table->unsignedTinyInteger('otp_attempts')->default(0);
            $table->unsignedTinyInteger('resend_count')->default(0);
            $table->timestamp('last_resend_at')->nullable();

            // Session state
            $table->enum('status', ['pending', 'verified', 'expired', 'locked', 'completed'])->default('pending');
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('locked_until')->nullable();

            // Session expiry
            $table->timestamp('expires_at');

            $table->timestamps();

            // Indexes for rate limiting queries
            $table->index(['ip_address', 'created_at'], 'idx_session_ip_rate');
            $table->index(['device_id', 'created_at'], 'idx_session_device_rate');
            $table->index(['phone_hash', 'created_at'], 'idx_session_phone_rate');
            $table->index('expires_at', 'idx_session_expiry');
            $table->index('status', 'idx_session_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_onboarding_sessions');
    }
};
