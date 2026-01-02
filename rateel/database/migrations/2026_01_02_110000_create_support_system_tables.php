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
        // FAQ Table
        Schema::create('faqs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('category', 50); // general, trips, payments, account, vehicle
            $table->string('question');
            $table->text('answer');
            $table->integer('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->string('user_type', 20)->default('driver'); // driver, customer, both
            $table->integer('view_count')->default(0);
            $table->integer('helpful_count')->default(0);
            $table->integer('not_helpful_count')->default(0);
            $table->timestamps();

            $table->index(['category', 'is_active']);
            $table->index('user_type');
        });

        // Support Tickets Table
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('ticket_number')->unique();
            $table->foreignUuid('driver_id')->constrained('users')->onDelete('cascade');
            $table->string('subject');
            $table->text('description');
            $table->string('category', 50); // technical, account, payment, trip_issue, other
            $table->string('priority', 20)->default('normal'); // low, normal, high, urgent
            $table->string('status', 20)->default('open'); // open, in_progress, resolved, closed
            $table->foreignUuid('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->text('resolution_note')->nullable();
            $table->integer('rating')->nullable(); // 1-5 stars
            $table->text('rating_comment')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['driver_id', 'status']);
            $table->index('ticket_number');
            $table->index('category');
        });

        // Support Ticket Messages Table
        Schema::create('support_ticket_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('ticket_id')->constrained('support_tickets')->onDelete('cascade');
            $table->foreignUuid('user_id')->constrained('users')->onDelete('cascade');
            $table->text('message');
            $table->json('attachments')->nullable();
            $table->boolean('is_admin_reply')->default(false);
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['ticket_id', 'created_at']);
        });

        // Feedback Table
        Schema::create('app_feedback', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('driver_id')->constrained('users')->onDelete('cascade');
            $table->string('type', 50); // feature_request, bug_report, general_feedback, complaint
            $table->string('subject');
            $table->text('message');
            $table->integer('rating')->nullable(); // 1-5 stars
            $table->string('screen_name')->nullable(); // Which screen the feedback is about
            $table->json('metadata')->nullable(); // App version, device info, etc.
            $table->string('status', 20)->default('pending'); // pending, reviewed, implemented, rejected
            $table->text('admin_response')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['driver_id', 'created_at']);
            $table->index('type');
            $table->index('status');
        });

        // Issue Reports Table
        Schema::create('issue_reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('report_number')->unique();
            $table->foreignUuid('driver_id')->constrained('users')->onDelete('cascade');
            $table->foreignUuid('trip_id')->nullable()->constrained('trip_requests')->nullOnDelete();
            $table->string('issue_type', 50); // customer_behavior, app_malfunction, payment_issue, safety_concern, other
            $table->text('description');
            $table->json('attachments')->nullable(); // Photos, screenshots
            $table->string('severity', 20)->default('medium'); // low, medium, high, critical
            $table->string('status', 20)->default('reported'); // reported, investigating, resolved, closed
            $table->timestamp('investigated_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestamps();

            $table->index(['driver_id', 'created_at']);
            $table->index('issue_type');
            $table->index('status');
            $table->index('report_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('issue_reports');
        Schema::dropIfExists('app_feedback');
        Schema::dropIfExists('support_ticket_messages');
        Schema::dropIfExists('support_tickets');
        Schema::dropIfExists('faqs');
    }
};
