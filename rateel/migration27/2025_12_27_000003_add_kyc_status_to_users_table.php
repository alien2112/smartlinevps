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
        Schema::table('users', function (Blueprint $table) {
            $table->enum('kyc_status', [
                'not_required', 'unverified', 'pending', 'verified', 'rejected'
            ])->default('not_required')->after('is_active');
            $table->timestamp('kyc_verified_at')->nullable()->after('kyc_status');
            
            $table->index('kyc_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['kyc_status']);
            $table->dropColumn(['kyc_status', 'kyc_verified_at']);
        });
    }
};
