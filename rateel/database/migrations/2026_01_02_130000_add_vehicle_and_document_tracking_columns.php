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
        // Add vehicle tracking columns
        Schema::table('vehicles', function (Blueprint $table) {
            $table->date('insurance_expiry_date')->nullable()->after('licence_plate_number');
            $table->string('insurance_company')->nullable()->after('insurance_expiry_date');
            $table->string('insurance_policy_number')->nullable()->after('insurance_company');
            $table->date('last_inspection_date')->nullable()->after('insurance_policy_number');
            $table->date('next_inspection_due')->nullable()->after('last_inspection_date');
            $table->string('inspection_certificate_number')->nullable()->after('next_inspection_due');
            $table->boolean('insurance_reminder_sent')->default(false)->after('inspection_certificate_number');
            $table->boolean('inspection_reminder_sent')->default(false)->after('insurance_reminder_sent');
        });

        // Add document expiry tracking columns
        Schema::table('driver_documents', function (Blueprint $table) {
            $table->date('expiry_date')->nullable()->after('verified_at');
            $table->boolean('reminder_sent')->default(false)->after('expiry_date');
            $table->timestamp('reminder_sent_at')->nullable()->after('reminder_sent');
            $table->integer('days_before_expiry_to_remind')->default(30)->after('reminder_sent_at');
        });

        // Create promotional banners table
        Schema::create('driver_promotions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->text('description');
            $table->text('terms_conditions')->nullable();
            $table->string('image_url')->nullable();
            $table->string('action_type', 50)->default('link'); // link, deep_link, claim
            $table->string('action_url')->nullable();
            $table->foreignUuid('target_driver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('target_level')->nullable(); // Target specific driver levels
            $table->integer('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->integer('max_claims')->nullable();
            $table->integer('current_claims')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'expires_at']);
            $table->index('target_driver_id');
        });

        // Create promotion claims table
        Schema::create('promotion_claims', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('promotion_id')->constrained('driver_promotions')->onDelete('cascade');
            $table->foreignUuid('driver_id')->constrained('users')->onDelete('cascade');
            $table->string('status', 20)->default('claimed'); // claimed, redeemed, expired
            $table->timestamp('claimed_at');
            $table->timestamp('redeemed_at')->nullable();
            $table->text('redemption_details')->nullable();
            $table->timestamps();

            $table->index(['driver_id', 'claimed_at']);
            $table->index(['promotion_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promotion_claims');
        Schema::dropIfExists('driver_promotions');

        Schema::table('driver_documents', function (Blueprint $table) {
            $table->dropColumn([
                'expiry_date',
                'reminder_sent',
                'reminder_sent_at',
                'days_before_expiry_to_remind'
            ]);
        });

        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn([
                'insurance_expiry_date',
                'insurance_company',
                'insurance_policy_number',
                'last_inspection_date',
                'next_inspection_due',
                'inspection_certificate_number',
                'insurance_reminder_sent',
                'inspection_reminder_sent'
            ]);
        });
    }
};
