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
        if (!Schema::hasTable('driver_notifications')) {
            Schema::create('driver_notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('driver_id')->constrained('users')->onDelete('cascade');
            $table->string('type', 50); // trip_update, payment_received, system_announcement, etc.
            $table->string('title');
            $table->text('message');
            $table->json('data')->nullable(); // Additional metadata
            $table->string('action_type')->nullable(); // deep_link, external_url, none
            $table->string('action_url')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->string('priority', 20)->default('normal'); // low, normal, high, urgent
            $table->string('category', 50)->nullable(); // trips, earnings, promotions, system
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

                $table->index(['driver_id', 'is_read']);
                $table->index(['driver_id', 'created_at']);
                $table->index('type');
            });
        }

        if (!Schema::hasTable('notification_settings')) {
            Schema::create('notification_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('driver_id')->constrained('users')->onDelete('cascade');
            $table->boolean('trip_requests_enabled')->default(true);
            $table->boolean('trip_updates_enabled')->default(true);
            $table->boolean('payment_notifications_enabled')->default(true);
            $table->boolean('promotional_notifications_enabled')->default(true);
            $table->boolean('system_notifications_enabled')->default(true);
            $table->boolean('email_notifications_enabled')->default(false);
            $table->boolean('sms_notifications_enabled')->default(false);
            $table->boolean('push_notifications_enabled')->default(true);
            $table->string('quiet_hours_start')->nullable(); // e.g., "22:00"
            $table->string('quiet_hours_end')->nullable(); // e.g., "07:00"
            $table->boolean('quiet_hours_enabled')->default(false);
            $table->timestamps();

                $table->unique('driver_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_settings');
        Schema::dropIfExists('driver_notifications');
    }
};
