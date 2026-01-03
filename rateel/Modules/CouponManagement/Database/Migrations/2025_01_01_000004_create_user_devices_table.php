<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_devices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();

            // FCM token
            $table->string('fcm_token', 500);

            // Device info
            $table->enum('platform', ['android', 'ios', 'web'])->default('android');
            $table->string('device_id', 100)->nullable(); // unique device identifier
            $table->string('device_model', 100)->nullable();
            $table->string('app_version', 20)->nullable();

            // Status
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();

            // Track invalid tokens
            $table->unsignedTinyInteger('failure_count')->default(0);
            $table->timestamp('deactivated_at')->nullable();
            $table->string('deactivation_reason', 100)->nullable();

            $table->timestamps();

            // Unique FCM token (same token can't belong to multiple users)
            $table->unique('fcm_token');

            // Indexes
            $table->index('user_id');
            $table->index(['user_id', 'is_active']);
            $table->index('platform');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_devices');
    }
};
