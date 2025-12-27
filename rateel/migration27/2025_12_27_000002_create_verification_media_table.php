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
        Schema::create('verification_media', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('session_id')->constrained('verification_sessions')->onDelete('cascade');
            $table->enum('kind', ['selfie', 'liveness_video', 'id_front', 'id_back']);
            $table->string('storage_disk', 50)->default('local'); // r2, s3, local
            $table->string('path', 500);
            $table->string('mime', 100);
            $table->unsignedBigInteger('size'); // bytes
            $table->string('checksum', 64)->nullable(); // SHA256
            $table->timestamps();
            
            $table->index('session_id');
            $table->index(['session_id', 'kind']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('verification_media');
    }
};
