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
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->onDelete('cascade');
            $table->string('user_type'); // 'driver' or 'customer'
            $table->string('subject');
            $table->text('message');
            $table->string('category')->nullable(); // 'payment', 'trip', 'account', 'other'
            $table->string('priority')->default('normal'); // 'low', 'normal', 'high', 'urgent'
            $table->string('status')->default('open'); // 'open', 'in_progress', 'resolved', 'closed'
            $table->uuid('trip_id')->nullable();
            $table->text('admin_response')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->uuid('responded_by')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'status']);
            $table->index(['user_type', 'status']);
            $table->index('created_at');
            
            // Foreign keys
            $table->foreign('trip_id')->references('id')->on('trip_requests')->onDelete('set null');
            $table->foreign('responded_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('support_tickets');
    }
};
