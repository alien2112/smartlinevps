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
        // Content Pages (Terms, Privacy, About, etc.)
        Schema::create('content_pages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug')->unique();
            $table->string('title');
            $table->text('content');
            $table->string('page_type', 50); // terms, privacy, about, help
            $table->string('user_type', 20)->default('both'); // driver, customer, both
            $table->boolean('is_active')->default(true);
            $table->integer('version')->default(1);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['slug', 'is_active']);
            $table->index('page_type');
        });

        // Driver Privacy Settings
        Schema::create('driver_privacy_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('driver_id')->constrained('users')->onDelete('cascade');
            $table->boolean('show_profile_photo')->default(true);
            $table->boolean('show_phone_number')->default(false);
            $table->boolean('show_in_leaderboard')->default(true);
            $table->boolean('share_trip_data_for_improvement')->default(true);
            $table->boolean('allow_promotional_contacts')->default(true);
            $table->boolean('data_sharing_with_partners')->default(false);
            $table->timestamps();

            $table->unique('driver_id');
        });

        // Emergency Contacts
        Schema::create('emergency_contacts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('driver_id')->constrained('users')->onDelete('cascade');
            $table->string('name');
            $table->string('relationship', 50); // spouse, parent, sibling, friend, other
            $table->string('phone');
            $table->string('alternate_phone')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->boolean('notify_on_emergency')->default(true);
            $table->boolean('share_live_location')->default(false);
            $table->timestamps();

            $table->index(['driver_id', 'is_primary']);
        });

        // Account Deletion Requests
        Schema::create('account_deletion_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('driver_id')->constrained('users')->onDelete('cascade');
            $table->string('reason', 100); // dissatisfied, privacy_concerns, switching_service, temporary_break, other
            $table->text('additional_comments')->nullable();
            $table->string('status', 20)->default('pending'); // pending, approved, rejected, completed
            $table->timestamp('requested_at');
            $table->timestamp('scheduled_deletion_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignUuid('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('admin_notes')->nullable();
            $table->timestamps();

            $table->index(['driver_id', 'status']);
        });

        // Phone Number Change Requests
        Schema::create('phone_change_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('driver_id')->constrained('users')->onDelete('cascade');
            $table->string('old_phone');
            $table->string('new_phone');
            $table->string('otp_code', 10);
            $table->boolean('old_phone_verified')->default(false);
            $table->boolean('new_phone_verified')->default(false);
            $table->timestamp('old_phone_verified_at')->nullable();
            $table->timestamp('new_phone_verified_at')->nullable();
            $table->string('status', 20)->default('pending'); // pending, verified, approved, rejected, completed
            $table->timestamp('expires_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['driver_id', 'status']);
            $table->index('new_phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('phone_change_requests');
        Schema::dropIfExists('account_deletion_requests');
        Schema::dropIfExists('emergency_contacts');
        Schema::dropIfExists('driver_privacy_settings');
        Schema::dropIfExists('content_pages');
    }
};
