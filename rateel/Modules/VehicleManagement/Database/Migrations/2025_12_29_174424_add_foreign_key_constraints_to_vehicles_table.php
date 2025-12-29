<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This migration adds proper foreign key constraints to prevent orphaned records
     * when brands, models, or categories are deleted. Using nullOnDelete ensures
     * that vehicles are not deleted but instead have their references set to null.
     */
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            // First, make the foreign key columns nullable if they aren't already
            $table->foreignUuid('brand_id')->nullable()->change();
            $table->foreignUuid('model_id')->nullable()->change();
            $table->foreignUuid('category_id')->nullable()->change();

            // Add proper foreign key constraints with nullOnDelete behavior
            // This ensures that if a brand/model/category is deleted,
            // the vehicle's reference is set to null instead of causing errors
            $table->foreign('brand_id')
                ->references('id')
                ->on('vehicle_brands')
                ->nullOnDelete();

            $table->foreign('model_id')
                ->references('id')
                ->on('vehicle_models')
                ->nullOnDelete();

            $table->foreign('category_id')
                ->references('id')
                ->on('vehicle_categories')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            // Drop the foreign key constraints
            $table->dropForeign(['brand_id']);
            $table->dropForeign(['model_id']);
            $table->dropForeign(['category_id']);

            // Revert the columns to not nullable (optional, depends on your needs)
            // Note: This may fail if there are null values in the database
            // $table->foreignUuid('brand_id')->nullable(false)->change();
            // $table->foreignUuid('model_id')->nullable(false)->change();
            // $table->foreignUuid('category_id')->nullable(false)->change();
        });
    }
};
