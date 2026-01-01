<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offer_usages', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('offer_id')->constrained('offers')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('trip_id')->nullable()->constrained('trip_requests')->nullOnDelete();

            // Discount applied
            $table->decimal('original_fare', 10, 2);
            $table->decimal('discount_amount', 10, 2);
            $table->decimal('final_fare', 10, 2);

            // Status
            $table->enum('status', ['applied', 'cancelled', 'refunded'])->default('applied');

            $table->timestamps();

            // Indexes
            $table->index(['offer_id', 'user_id']);
            $table->index(['user_id', 'status']);
            $table->index('trip_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offer_usages');
    }
};
