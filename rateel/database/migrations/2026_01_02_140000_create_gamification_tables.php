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
        // Achievements table
        Schema::create('achievements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('key')->unique(); // first_trip, hundred_trips, perfect_rating, etc.
            $table->string('title');
            $table->text('description');
            $table->string('icon')->nullable();
            $table->string('category', 50); // trips, earnings, ratings, milestones
            $table->integer('points')->default(0);
            $table->json('requirements')->nullable(); // Criteria to unlock
            $table->integer('tier')->default(1); // 1=Bronze, 2=Silver, 3=Gold, 4=Platinum
            $table->boolean('is_active')->default(true);
            $table->integer('order')->default(0);
            $table->timestamps();

            $table->index('category');
            $table->index(['is_active', 'tier']);
        });

        // Driver achievements (unlocked achievements)
        Schema::create('driver_achievements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('driver_id')->constrained('users')->onDelete('cascade');
            $table->foreignUuid('achievement_id')->constrained('achievements')->onDelete('cascade');
            $table->timestamp('unlocked_at');
            $table->json('unlock_data')->nullable(); // Context about how it was unlocked
            $table->boolean('is_featured')->default(false);
            $table->timestamps();

            $table->unique(['driver_id', 'achievement_id']);
            $table->index(['driver_id', 'unlocked_at']);
        });

        // Badges table
        Schema::create('badges', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('key')->unique(); // top_earner, safety_star, customer_favorite
            $table->string('title');
            $table->text('description');
            $table->string('icon')->nullable();
            $table->string('color', 20)->nullable(); // For UI theming
            $table->json('criteria')->nullable(); // Requirements
            $table->string('rarity', 20)->default('common'); // common, rare, epic, legendary
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('rarity');
        });

        // Driver badges
        Schema::create('driver_badges', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('driver_id')->constrained('users')->onDelete('cascade');
            $table->foreignUuid('badge_id')->constrained('badges')->onDelete('cascade');
            $table->timestamp('earned_at');
            $table->timestamp('expires_at')->nullable(); // Some badges expire
            $table->boolean('is_active')->default(true);
            $table->json('earning_data')->nullable();
            $table->timestamps();

            $table->unique(['driver_id', 'badge_id']);
            $table->index(['driver_id', 'earned_at']);
            $table->index(['driver_id', 'is_active']);
        });

        // Driver gamification progress
        Schema::create('driver_gamification_progress', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('driver_id')->unique()->constrained('users')->onDelete('cascade');
            $table->integer('total_points')->default(0);
            $table->integer('achievements_unlocked')->default(0);
            $table->integer('badges_earned')->default(0);
            $table->integer('current_streak_days')->default(0);
            $table->integer('longest_streak_days')->default(0);
            $table->timestamp('last_activity_date')->nullable();
            $table->json('statistics')->nullable(); // Additional stats
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('driver_gamification_progress');
        Schema::dropIfExists('driver_badges');
        Schema::dropIfExists('badges');
        Schema::dropIfExists('driver_achievements');
        Schema::dropIfExists('achievements');
    }
};
