<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds Travel Mode fields for VIP-only scheduled rides
     */
    public function up(): void
    {
        Schema::table('trip_requests', function (Blueprint $table) {
            $table->boolean('is_travel')->default(false)->after('type')
                ->comment('True for Travel Mode rides (VIP only, fixed price)');
            $table->decimal('fixed_price', 10, 2)->nullable()->after('is_travel')
                ->comment('Locked price for travel rides - no surge');
            $table->dateTime('travel_date')->nullable()->after('fixed_price')
                ->comment('Scheduled date/time for travel pickup');
            $table->tinyInteger('travel_passengers')->nullable()->after('travel_date')
                ->comment('Number of passengers for travel ride');
            $table->tinyInteger('travel_luggage')->nullable()->after('travel_passengers')
                ->comment('Number of luggage pieces for travel ride');
            $table->text('travel_notes')->nullable()->after('travel_luggage')
                ->comment('Additional notes from customer for travel ride');
            $table->enum('travel_status', ['pending', 'accepted', 'expired', 'cancelled'])
                ->nullable()->after('travel_notes')
                ->comment('Status of travel request dispatch');
            $table->timestamp('travel_dispatched_at')->nullable()->after('travel_status')
                ->comment('When the travel request was dispatched to drivers');
        });

        // Add index for efficient travel queries
        Schema::table('trip_requests', function (Blueprint $table) {
            $table->index(['is_travel', 'travel_status'], 'idx_travel_requests');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trip_requests', function (Blueprint $table) {
            $table->dropIndex('idx_travel_requests');
            $table->dropColumn([
                'is_travel',
                'fixed_price',
                'travel_date',
                'travel_passengers',
                'travel_luggage',
                'travel_notes',
                'travel_status',
                'travel_dispatched_at'
            ]);
        });
    }
};
