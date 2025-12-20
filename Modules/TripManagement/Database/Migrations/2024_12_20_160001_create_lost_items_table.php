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
        Schema::create('lost_items', function (Blueprint $table) {
            $table->uuid('id')->primary()->index();
            $table->foreignUuid('trip_request_id');
            $table->foreignUuid('customer_id');
            $table->foreignUuid('driver_id');
            $table->string('category'); // phone, wallet, bag, keys, other
            $table->text('description');
            $table->string('image_url')->nullable();
            $table->string('status')->default('pending');
            // Status values: pending, driver_contacted, found, returned, closed
            $table->string('driver_response')->nullable(); // found, not_found
            $table->text('driver_notes')->nullable();
            $table->text('admin_notes')->nullable();
            $table->string('contact_preference')->default('in_app'); // in_app, phone
            $table->timestamp('item_lost_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes for performance
            $table->index(['status', 'created_at']);
            $table->index('driver_id');
            $table->index('customer_id');
            $table->index('trip_request_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lost_items');
    }
};
