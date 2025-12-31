<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Core coupon info
            $table->string('code', 50)->unique();
            $table->string('name', 100);
            $table->text('description')->nullable();

            // Type: PERCENT, FIXED, FREE_RIDE_CAP
            $table->enum('type', ['PERCENT', 'FIXED', 'FREE_RIDE_CAP']);

            // Value based on type
            $table->decimal('value', 10, 2); // percentage (0-100) or fixed amount
            $table->decimal('max_discount', 10, 2)->nullable(); // cap for PERCENT and FREE_RIDE_CAP
            $table->decimal('min_fare', 10, 2)->default(0); // minimum fare to apply

            // Limits
            $table->unsignedInteger('global_limit')->nullable(); // null = unlimited
            $table->unsignedInteger('per_user_limit')->default(1);
            $table->unsignedInteger('global_used_count')->default(0);

            // Validity period
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');

            // Scope rules (JSON arrays)
            $table->json('allowed_city_ids')->nullable(); // null = all cities
            $table->json('allowed_service_types')->nullable(); // null = all service types

            // Eligibility: ALL, TARGETED, SEGMENT
            $table->enum('eligibility_type', ['ALL', 'TARGETED', 'SEGMENT'])->default('ALL');
            $table->string('segment_key', 50)->nullable(); // e.g., INACTIVE_30_DAYS

            // Status
            $table->boolean('is_active')->default(true);

            // Audit
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('code');
            $table->index('is_active');
            $table->index(['starts_at', 'ends_at']);
            $table->index('eligibility_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
