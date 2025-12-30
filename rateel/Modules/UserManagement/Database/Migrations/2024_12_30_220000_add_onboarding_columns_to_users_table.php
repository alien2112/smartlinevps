<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Adds onboarding step tracking columns for the unified driver auth/registration flow.
     * This enables Uber-style resume-from-where-you-left functionality.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Onboarding step tracking - the single source of truth for driver's progress
            $table->enum('onboarding_step', [
                'phone',           // Step 1: Phone number entry
                'otp',             // Step 2: OTP verification pending
                'password',        // Step 3: Password creation
                'register_info',   // Step 4: Personal info (name, national ID, city)
                'vehicle_type',    // Step 5: Vehicle type selection
                'documents',       // Step 6: Document uploads
                'pending_approval', // Step 7: Waiting for admin approval
                'approved'         // Step 8: Fully approved, can access dashboard
            ])->default('phone')->after('is_active');

            // Timestamp tracking for each step completion
            $table->timestamp('otp_verified_at')->nullable()->after('onboarding_step');
            $table->timestamp('password_set_at')->nullable()->after('otp_verified_at');
            $table->timestamp('register_completed_at')->nullable()->after('password_set_at');
            $table->timestamp('vehicle_selected_at')->nullable()->after('register_completed_at');
            $table->timestamp('documents_completed_at')->nullable()->after('vehicle_selected_at');

            // Driver-specific fields for registration
            $table->string('first_name_ar')->nullable()->after('first_name');
            $table->string('last_name_ar')->nullable()->after('last_name');
            $table->unsignedBigInteger('city_id')->nullable()->after('last_name_ar');
            
            // Vehicle type selection
            $table->enum('selected_vehicle_type', ['car', 'taxi', 'scooter'])->nullable()->after('city_id');
            $table->boolean('travel_enabled')->default(false)->after('selected_vehicle_type');

            // Add index for quick lookups
            $table->index(['user_type', 'onboarding_step'], 'idx_user_onboarding');
            $table->index('phone', 'idx_user_phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('idx_user_onboarding');
            $table->dropIndex('idx_user_phone');
            
            $table->dropColumn([
                'onboarding_step',
                'otp_verified_at',
                'password_set_at',
                'register_completed_at',
                'vehicle_selected_at',
                'documents_completed_at',
                'first_name_ar',
                'last_name_ar',
                'city_id',
                'selected_vehicle_type',
                'travel_enabled',
            ]);
        });
    }
};
