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
        Schema::table('trip_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('trip_requests', 'recommended_fare')) {
                $table->decimal('recommended_fare', 16, 2)->nullable()->after('min_price')
                    ->comment('Recommended fare for travel mode (min_price * recommended_multiplier)');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trip_requests', function (Blueprint $table) {
            if (Schema::hasColumn('trip_requests', 'recommended_fare')) {
                $table->dropColumn('recommended_fare');
            }
        });
    }
};
