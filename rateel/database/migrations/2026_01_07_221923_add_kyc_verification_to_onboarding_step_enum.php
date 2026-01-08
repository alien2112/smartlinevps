<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE `users` MODIFY COLUMN `onboarding_step` ENUM('phone', 'otp', 'password', 'register_info', 'vehicle_type', 'documents', 'kyc_verification', 'pending_approval', 'approved') NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE `users` MODIFY COLUMN `onboarding_step` ENUM('phone', 'otp', 'password', 'register_info', 'vehicle_type', 'documents', 'pending_approval', 'approved') NULL");
    }
};
