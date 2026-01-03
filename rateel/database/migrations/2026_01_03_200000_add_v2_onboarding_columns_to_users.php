<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * V2 Driver Onboarding - Enhanced user columns for state machine
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Onboarding state machine (if not exists)
            if (!Schema::hasColumn('users', 'onboarding_state')) {
                $table->string('onboarding_state', 30)->default('otp_pending')->after('onboarding_step');
            }

            if (!Schema::hasColumn('users', 'onboarding_state_version')) {
                $table->unsignedInteger('onboarding_state_version')->default(1)->after('onboarding_state');
            }

            // OTP tracking columns
            if (!Schema::hasColumn('users', 'otp_hash')) {
                $table->string('otp_hash', 255)->nullable()->after('password');
            }

            if (!Schema::hasColumn('users', 'otp_sent_at')) {
                $table->timestamp('otp_sent_at')->nullable()->after('otp_hash');
            }

            if (!Schema::hasColumn('users', 'otp_attempts')) {
                $table->unsignedTinyInteger('otp_attempts')->default(0)->after('otp_sent_at');
            }

            if (!Schema::hasColumn('users', 'otp_locked_until')) {
                $table->timestamp('otp_locked_until')->nullable()->after('otp_attempts');
            }

            if (!Schema::hasColumn('users', 'otp_resend_count')) {
                $table->unsignedTinyInteger('otp_resend_count')->default(0)->after('otp_locked_until');
            }

            // Approval tracking
            if (!Schema::hasColumn('users', 'is_approved')) {
                $table->boolean('is_approved')->default(false)->after('is_active');
            }

            if (!Schema::hasColumn('users', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('is_approved');
            }

            if (!Schema::hasColumn('users', 'approved_by')) {
                $table->uuid('approved_by')->nullable()->after('approved_at');
            }

            // Profile completion flags
            if (!Schema::hasColumn('users', 'is_phone_verified')) {
                $table->boolean('is_phone_verified')->default(false)->after('phone_verified_at');
            }

            if (!Schema::hasColumn('users', 'is_profile_complete')) {
                $table->boolean('is_profile_complete')->default(false)->after('is_phone_verified');
            }

            // Rejection handling
            if (!Schema::hasColumn('users', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('is_approved');
            }

            if (!Schema::hasColumn('users', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable()->after('rejection_reason');
            }

            // Terms acceptance
            if (!Schema::hasColumn('users', 'terms_accepted_at')) {
                $table->timestamp('terms_accepted_at')->nullable();
            }

            if (!Schema::hasColumn('users', 'privacy_accepted_at')) {
                $table->timestamp('privacy_accepted_at')->nullable();
            }
        });

        // Add indexes for performance
        Schema::table('users', function (Blueprint $table) {
            // Check if index exists before creating
            $sm = Schema::getConnection()->getDoctrineSchemaManager();
            $indexes = $sm->listTableIndexes('users');

            if (!isset($indexes['idx_driver_onboarding_state'])) {
                $table->index(['user_type', 'onboarding_state'], 'idx_driver_onboarding_state');
            }

            if (!isset($indexes['idx_driver_approval'])) {
                $table->index(['user_type', 'is_approved'], 'idx_driver_approval');
            }

            if (!isset($indexes['idx_otp_lock'])) {
                $table->index('otp_locked_until', 'idx_otp_lock');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $columns = [
                'onboarding_state', 'onboarding_state_version',
                'otp_hash', 'otp_sent_at', 'otp_attempts', 'otp_locked_until', 'otp_resend_count',
                'is_approved', 'approved_at', 'approved_by',
                'is_phone_verified', 'is_profile_complete',
                'rejection_reason', 'rejected_at',
                'terms_accepted_at', 'privacy_accepted_at'
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('users', function (Blueprint $table) {
            $sm = Schema::getConnection()->getDoctrineSchemaManager();
            $indexes = $sm->listTableIndexes('users');

            if (isset($indexes['idx_driver_onboarding_state'])) {
                $table->dropIndex('idx_driver_onboarding_state');
            }
            if (isset($indexes['idx_driver_approval'])) {
                $table->dropIndex('idx_driver_approval');
            }
            if (isset($indexes['idx_otp_lock'])) {
                $table->dropIndex('idx_otp_lock');
            }
        });
    }
};
