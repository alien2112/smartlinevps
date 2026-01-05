<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Creates the driver_documents table for storing driver document uploads
     * during the onboarding process (ID front/back, license, car photo, selfie).
     */
    public function up(): void
    {
        Schema::create('driver_documents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            
            // Foreign key to users table (driver)
            $table->foreignUuid('driver_id')
                ->constrained('users')
                ->onDelete('cascade');
            
            // Document type enum
            $table->enum('type', [
                'id_front',      // National ID front
                'id_back',       // National ID back
                'license',       // Driving license
                'car_photo',     // Vehicle photo
                'selfie',        // Driver selfie for verification
            ]);
            
            // File storage
            $table->string('file_path');
            $table->string('original_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedInteger('file_size')->nullable();
            
            // Verification status
            $table->boolean('verified')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->foreignUuid('verified_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('rejection_reason')->nullable();
            
            // Metadata
            $table->json('metadata')->nullable(); // For storing extracted data (OCR, face match, etc.)
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->unique(['driver_id', 'type'], 'idx_driver_document_unique');
            $table->index('verified', 'idx_driver_document_verified');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('driver_documents');
    }
};
