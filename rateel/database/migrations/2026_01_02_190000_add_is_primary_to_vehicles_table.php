<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddIsPrimaryToVehiclesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('vehicles', function (Blueprint $table) {
            if (!Schema::hasColumn('vehicles', 'is_primary')) {
                $table->boolean('is_primary')->default(false)->after('is_active');
            }
        });

        // Add index if it doesn't exist
        $sm = Schema::getConnection()->getDoctrineSchemaManager();
        $indexesFound = $sm->listTableIndexes('vehicles');
        if (!isset($indexesFound['vehicles_driver_id_is_primary_index'])) {
            Schema::table('vehicles', function (Blueprint $table) {
                $table->index(['driver_id', 'is_primary'], 'vehicles_driver_id_is_primary_index');
            });
        }

        // Set the first vehicle for each driver as primary
        // Use a subquery approach that MySQL accepts
        $drivers = DB::table('vehicles')
            ->select('driver_id')
            ->whereNull('deleted_at')
            ->groupBy('driver_id')
            ->get();

        foreach ($drivers as $driver) {
            $firstVehicle = DB::table('vehicles')
                ->where('driver_id', $driver->driver_id)
                ->whereNull('deleted_at')
                ->orderBy('created_at', 'ASC')
                ->first();

            if ($firstVehicle) {
                DB::table('vehicles')
                    ->where('id', $firstVehicle->id)
                    ->update(['is_primary' => true]);
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropIndex(['driver_id', 'is_primary']);
            $table->dropColumn('is_primary');
        });
    }
}
