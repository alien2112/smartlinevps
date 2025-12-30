<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('trip_requests', function (Blueprint $table) {
            if (!$this->hasIndex('trip_requests', 'idx_trips_driver_status_date')) {
                $table->index(['driver_id', 'current_status', 'created_at'], 'idx_trips_driver_status_date');
            }
            if (!$this->hasIndex('trip_requests', 'idx_trips_customer_status_date')) {
                $table->index(['customer_id', 'current_status', 'created_at'], 'idx_trips_customer_status_date');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trip_requests', function (Blueprint $table) {
            if ($this->hasIndex('trip_requests', 'idx_trips_driver_status_date')) {
                $table->dropIndex('idx_trips_driver_status_date');
            }
            if ($this->hasIndex('trip_requests', 'idx_trips_customer_status_date')) {
                $table->dropIndex('idx_trips_customer_status_date');
            }
        });
    }

    private function hasIndex($table, $index)
    {
        $results = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = '{$index}'");
        return count($results) > 0;
    }
};