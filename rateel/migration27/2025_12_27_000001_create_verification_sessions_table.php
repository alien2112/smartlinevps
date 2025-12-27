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
        Schema::create('verification_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->onDelete('cascade');
            $table->string('type', 50)->default('driver_kyc'); // driver_kyc, customer_kyc
            $table->enum('status', [
                'unverified', 'pending', 'processing', 'verified', 
                'rejected', 'manual_review', 'expired'
            ])->default('unverified');
            $table->string('provider', 100)->nullable(); // client liveness provider name
            $table->decimal('liveness_score', 5, 2)->nullable();
            $table->decimal('face_match_score', 5, 2)->nullable();
            $table->decimal('doc_auth_score', 5, 2)->nullable();
            $table->json('extracted_fields')->nullable(); // {name, dob, id_number, expiry, governorate, gender}
            $table->enum('decision', ['pending', 'approved', 'rejected', 'manual_review'])->default('pending');
            $table->json('decision_reason_codes')->nullable(); // [{code, message}]
            $table->foreignUuid('reviewed_by_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('admin_notes')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            
            // Indexes for admin queries
            $table->index(['status', 'created_at']);
            $table->index(['user_id', 'type']);
            $table->index(['decision', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('verification_sessions');
    }
};
