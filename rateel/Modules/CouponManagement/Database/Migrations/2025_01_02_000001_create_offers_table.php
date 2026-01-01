<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offers', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Basic info
            $table->string('title', 150);
            $table->string('short_description', 500)->nullable();
            $table->text('terms_conditions')->nullable();
            $table->string('image')->nullable();
            $table->string('banner_image')->nullable();

            // Discount configuration
            $table->enum('discount_type', ['percentage', 'fixed', 'free_ride'])->default('percentage');
            $table->decimal('discount_amount', 10, 2);
            $table->decimal('max_discount', 10, 2)->nullable(); // Cap for percentage discounts
            $table->decimal('min_trip_amount', 10, 2)->default(0);

            // Limits
            $table->unsignedInteger('limit_per_user')->default(1);
            $table->unsignedInteger('global_limit')->nullable(); // null = unlimited
            $table->unsignedInteger('total_used')->default(0);
            $table->decimal('total_discount_given', 15, 2)->default(0);

            // Validity period
            $table->timestamp('start_date');
            $table->timestamp('end_date');

            // Targeting: Zone
            $table->enum('zone_type', ['all', 'selected'])->default('all');
            $table->json('zone_ids')->nullable();

            // Targeting: Customer Level
            $table->enum('customer_level_type', ['all', 'selected'])->default('all');
            $table->json('customer_level_ids')->nullable();

            // Targeting: Specific Customers
            $table->enum('customer_type', ['all', 'selected'])->default('all');
            $table->json('customer_ids')->nullable();

            // Targeting: Service Type
            $table->enum('service_type', ['all', 'ride', 'parcel', 'selected'])->default('all');
            $table->json('vehicle_category_ids')->nullable();

            // Priority (higher = applied first when multiple offers match)
            $table->unsignedTinyInteger('priority')->default(0);

            // Status
            $table->boolean('is_active')->default(true);
            $table->boolean('show_in_app')->default(true); // Show in offers list

            // Audit
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('is_active');
            $table->index(['start_date', 'end_date']);
            $table->index('priority');
            $table->index('zone_type');
            $table->index('service_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offers');
    }
};
