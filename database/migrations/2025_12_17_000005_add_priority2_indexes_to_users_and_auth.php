<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Priority 2 indexes for users table (authentication and lookups)
     * Expected impact: Login queries 200ms -> <10ms
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Phone-based login (most common auth method)
            $table->index(['phone', 'is_active'], 'idx_users_phone_active');

            // Email lookups (if used for login)
            $table->index(['email', 'is_active'], 'idx_users_email_active');

            // User type filtering
            $table->index(['user_type', 'is_active'], 'idx_users_type_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('idx_users_phone_active');
            $table->dropIndex('idx_users_email_active');
            $table->dropIndex('idx_users_type_active');
        });
    }
};
