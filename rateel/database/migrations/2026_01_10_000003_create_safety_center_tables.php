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
        // Trusted Contacts Table
        if (!Schema::hasTable('trusted_contacts')) {
            Schema::create('trusted_contacts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->onDelete('cascade');
            $table->string('name');
            $table->string('phone');
            $table->string('relationship')->nullable(); // 'family', 'friend', 'colleague', 'other'
            $table->boolean('is_active')->default(true);
            $table->integer('priority')->default(1); // 1 = highest priority
            $table->timestamps();
            
                $table->index(['user_id', 'is_active']);
            });
        }

        // Trip Shares Table
        if (!Schema::hasTable('trip_shares')) {
            Schema::create('trip_shares', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('trip_id')->constrained('trip_requests')->onDelete('cascade');
            $table->foreignUuid('driver_id')->constrained('users')->onDelete('cascade');
            $table->foreignUuid('shared_with_contact_id')->nullable()->constrained('trusted_contacts')->onDelete('set null');
            $table->string('share_token')->unique(); // For public link sharing
            $table->string('share_method'); // 'sms', 'whatsapp', 'link', 'auto'
            $table->boolean('is_active')->default(true);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_accessed_at')->nullable();
            $table->integer('access_count')->default(0);
            $table->timestamps();
            
            $table->index(['trip_id', 'is_active']);
            $table->index('share_token');
                $table->index('driver_id');
            });
        }

        // Emergency Alerts Table
        if (!Schema::hasTable('emergency_alerts')) {
            Schema::create('emergency_alerts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->onDelete('cascade');
            $table->string('user_type'); // 'driver' or 'customer'
            $table->foreignUuid('trip_id')->nullable()->constrained('trip_requests')->onDelete('set null');
            $table->string('alert_type'); // 'panic', 'police', 'medical', 'accident', 'harassment'
            $table->string('status')->default('active'); // 'active', 'resolved', 'false_alarm', 'cancelled'
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->string('location_address')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignUuid('resolved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('resolution_notes')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'status']);
            $table->index(['trip_id', 'status']);
            $table->index('alert_type');
                $table->index('created_at');
            });
        }

        // Trip Monitoring Table
        if (!Schema::hasTable('trip_monitoring')) {
            Schema::create('trip_monitoring', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('trip_id')->constrained('trip_requests')->onDelete('cascade');
            $table->foreignUuid('driver_id')->constrained('users')->onDelete('cascade');
            $table->boolean('is_enabled')->default(false);
            $table->boolean('auto_alert_enabled')->default(true);
            $table->integer('alert_delay_minutes')->default(15); // Alert if trip exceeds expected time
            $table->timestamp('monitoring_started_at')->nullable();
            $table->timestamp('monitoring_ended_at')->nullable();
            $table->timestamp('last_location_update')->nullable();
            $table->decimal('last_latitude', 10, 8)->nullable();
            $table->decimal('last_longitude', 11, 8)->nullable();
            $table->boolean('alert_triggered')->default(false);
            $table->timestamp('alert_triggered_at')->nullable();
            $table->timestamps();
            
            $table->unique('trip_id');
            $table->index(['driver_id', 'is_enabled']);
                $table->index('alert_triggered');
            });
        }

        // Emergency Contacts (System-wide)
        if (!Schema::hasTable('emergency_contacts')) {
            Schema::create('emergency_contacts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('country_code')->default('EG');
            $table->string('service_type'); // 'police', 'ambulance', 'fire', 'traffic'
            $table->string('phone_number');
            $table->string('name');
            $table->string('name_ar')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('priority')->default(1);
            $table->timestamps();
            
            $table->index(['country_code', 'is_active']);
                $table->index('service_type');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trip_monitoring');
        Schema::dropIfExists('emergency_alerts');
        Schema::dropIfExists('trip_shares');
        Schema::dropIfExists('trusted_contacts');
        Schema::dropIfExists('emergency_contacts');
    }
};
