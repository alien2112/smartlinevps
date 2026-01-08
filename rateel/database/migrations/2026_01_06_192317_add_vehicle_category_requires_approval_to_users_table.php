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
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'vehicle_category_requires_approval')) {
                $table->boolean('vehicle_category_requires_approval')->default(false)->after('vehicle_category_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'vehicle_category_requires_approval')) {
                $table->dropColumn('vehicle_category_requires_approval');
            }
        });
    }
};
