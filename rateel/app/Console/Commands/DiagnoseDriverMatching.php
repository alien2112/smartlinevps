<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\UserManagement\Entities\User;
use Modules\UserManagement\Entities\UserLastLocation;
use Modules\UserManagement\Entities\DriverDetail;
use Modules\VehicleManagement\Entities\Vehicle;
use Modules\TripManagement\Entities\TripRequest;

class DiagnoseDriverMatching extends Command
{
    protected $signature = 'diagnose:driver-matching 
                            {--zone-id= : Specific zone to check}
                            {--driver-id= : Specific driver to check}';
    
    protected $description = 'Diagnose why drivers cannot find customers/trips';

    public function handle(): int
    {
        $this->info('🔍 Diagnosing Driver-Customer Matching...');
        $this->newLine();

        // Step 1: Check Pending Trips
        $this->info('📋 Step 1: Checking Pending Trips...');
        $pendingTrips = TripRequest::where('current_status', 'pending')
            ->whereNull('driver_id')
            ->count();
        $this->table(['Metric', 'Value'], [
            ['Total Pending Trips (no driver)', $pendingTrips],
        ]);

        if ($pendingTrips === 0) {
            $this->warn('⚠️ No pending trips found! This is why drivers see no rides.');
            $this->newLine();
        }

        // Step 2: Check Online Drivers
        $this->info('👨‍✈️ Step 2: Checking Online Drivers...');
        $onlineDrivers = DriverDetail::where('is_online', true)
            ->where('availability_status', 'available')
            ->count();
        $offlineDrivers = DriverDetail::where('is_online', false)->count();
        $busyDrivers = DriverDetail::where('is_online', true)
            ->whereIn('availability_status', ['unavailable', 'on_trip'])
            ->count();

        $this->table(['Status', 'Count'], [
            ['Online & Available', $onlineDrivers],
            ['Offline', $offlineDrivers],
            ['Online but Busy/On Trip', $busyDrivers],
        ]);

        if ($onlineDrivers === 0) {
            $this->error('❌ No online & available drivers! Trips cannot be assigned.');
        }

        // Step 3: Check Driver Locations
        $this->info('📍 Step 3: Checking Driver Locations...');
        $driversWithLocation = UserLastLocation::where('type', 'driver')->count();
        $driversWithRecentLocation = UserLastLocation::where('type', 'driver')
            ->where('updated_at', '>=', now()->subMinutes(30))
            ->count();
        $driversWithOldLocation = UserLastLocation::where('type', 'driver')
            ->where('updated_at', '<', now()->subMinutes(30))
            ->count();

        $this->table(['Metric', 'Count'], [
            ['Drivers with any location', $driversWithLocation],
            ['Drivers with recent location (< 30 min)', $driversWithRecentLocation],
            ['Drivers with stale location (> 30 min)', $driversWithOldLocation],
        ]);

        if ($driversWithRecentLocation === 0) {
            $this->error('❌ No drivers have recent location data! Location updates not working.');
        }

        // Step 4: Check Vehicles
        $this->info('🚗 Step 4: Checking Driver Vehicles...');
        $activeVehicles = Vehicle::where('is_active', true)->count();
        $inactiveVehicles = Vehicle::where('is_active', false)->count();
        $vehiclesWithCategory = Vehicle::whereNotNull('category_id')
            ->where('category_id', '!=', '[]')
            ->where('category_id', '!=', '')
            ->count();

        $this->table(['Metric', 'Count'], [
            ['Active Vehicles', $activeVehicles],
            ['Inactive Vehicles', $inactiveVehicles],
            ['Vehicles with Category', $vehiclesWithCategory],
        ]);

        // Step 5: Check Zones
        $this->info('🗺️ Step 5: Checking Zone Distribution...');
        $tripsByZone = TripRequest::where('current_status', 'pending')
            ->whereNull('driver_id')
            ->selectRaw('zone_id, COUNT(*) as count')
            ->groupBy('zone_id')
            ->get();

        $driversByZone = UserLastLocation::where('type', 'driver')
            ->selectRaw('zone_id, COUNT(*) as count')
            ->groupBy('zone_id')
            ->get();

        $this->info('Pending Trips by Zone:');
        if ($tripsByZone->isEmpty()) {
            $this->warn('  No pending trips.');
        } else {
            foreach ($tripsByZone as $zone) {
                $this->line("  Zone {$zone->zone_id}: {$zone->count} trips");
            }
        }

        $this->info('Drivers by Zone:');
        if ($driversByZone->isEmpty()) {
            $this->warn('  No drivers with locations.');
        } else {
            foreach ($driversByZone as $zone) {
                $this->line("  Zone {$zone->zone_id}: {$zone->count} drivers");
            }
        }

        // Step 6: Check for zone mismatches
        $this->info('⚠️ Step 6: Checking Zone Mismatches...');
        $tripZoneIds = $tripsByZone->pluck('zone_id')->toArray();
        $driverZoneIds = $driversByZone->pluck('zone_id')->toArray();
        
        $tripsWithoutDriverZone = array_diff($tripZoneIds, $driverZoneIds);
        $driversWithoutTripZone = array_diff($driverZoneIds, $tripZoneIds);

        if (!empty($tripsWithoutDriverZone)) {
            $this->error('❌ ZONE MISMATCH: Trips exist in zones without any drivers:');
            foreach ($tripsWithoutDriverZone as $zoneId) {
                $this->line("  Zone {$zoneId}");
            }
        }

        // Step 7: Check Search Radius
        $this->info('📐 Step 7: Checking Search Radius Settings...');
        $searchRadius = get_cache('search_radius');
        $travelSearchRadius = get_cache('travel_search_radius');
        
        $this->table(['Setting', 'Value'], [
            ['search_radius (km)', $searchRadius ?? '5 (default)'],
            ['travel_search_radius (km)', $travelSearchRadius ?? '50 (default)'],
        ]);

        if ($searchRadius && $searchRadius < 5) {
            $this->warn("⚠️ Search radius is very small ({$searchRadius}km). Consider increasing it.");
        }

        // Step 8: Category Matching Analysis
        $this->info('🏷️ Step 8: Checking Vehicle Category Matching...');
        $tripCategories = TripRequest::where('current_status', 'pending')
            ->whereNull('driver_id')
            ->selectRaw('vehicle_category_id, COUNT(*) as count')
            ->groupBy('vehicle_category_id')
            ->get();

        $vehicleCategories = Vehicle::where('is_active', true)
            ->selectRaw('category_id, COUNT(*) as count')
            ->groupBy('category_id')
            ->get();

        $this->info('Pending Trips by Category:');
        foreach ($tripCategories as $cat) {
            $catId = $cat->vehicle_category_id ?? 'NULL (any)';
            $this->line("  Category {$catId}: {$cat->count} trips");
        }

        $this->info('Active Vehicles by Category:');
        foreach ($vehicleCategories as $cat) {
            $catId = $cat->category_id ?? 'NULL';
            $this->line("  Category {$catId}: {$cat->count} vehicles");
        }

        // Step 9: Specific driver diagnosis if provided
        if ($driverId = $this->option('driver-id')) {
            $this->newLine();
            $this->info("🔎 Diagnosing Specific Driver: {$driverId}");
            $this->diagnoseDriver($driverId);
        }

        // Summary
        $this->newLine();
        $this->info('📊 Summary:');
        
        $issues = [];
        if ($pendingTrips === 0) $issues[] = 'No pending trips';
        if ($onlineDrivers === 0) $issues[] = 'No online drivers';
        if ($driversWithRecentLocation === 0) $issues[] = 'No recent driver locations';
        if (!empty($tripsWithoutDriverZone)) $issues[] = 'Zone mismatch between trips and drivers';

        if (empty($issues)) {
            $this->info('✅ No critical issues found. Check the coordinate distance calculation.');
            $this->info('   The coordinate swap bug has been fixed - retest the system.');
        } else {
            $this->error('❌ Issues found:');
            foreach ($issues as $issue) {
                $this->line("   - {$issue}");
            }
        }

        $this->newLine();
        $this->info('💡 Tips:');
        $this->line('1. Ensure drivers are ONLINE in the app');
        $this->line('2. Ensure drivers have ACTIVE and APPROVED vehicles');
        $this->line('3. Ensure driver location is being updated (check app GPS)');
        $this->line('4. Ensure driver is in the SAME ZONE as the trip');
        $this->line('5. Ensure driver vehicle CATEGORY matches trip category');
        $this->line('6. Check if search_radius is too small (default: 5km)');

        return 0;
    }

    protected function diagnoseDriver(string $driverId): void
    {
        $driver = User::find($driverId);
        if (!$driver) {
            $this->error("Driver {$driverId} not found!");
            return;
        }

        $this->table(['Field', 'Value'], [
            ['Driver ID', $driver->id],
            ['Name', $driver->first_name . ' ' . $driver->last_name],
            ['Is Active', $driver->is_active ? '✅ Yes' : '❌ No'],
            ['Phone', $driver->phone],
        ]);

        $details = DriverDetail::where('user_id', $driverId)->first();
        if ($details) {
            $this->table(['Driver Details', 'Value'], [
                ['Is Online', $details->is_online ? '✅ Yes' : '❌ No'],
                ['Availability', $details->availability_status ?? 'NULL'],
                ['Service', is_array($details->service) ? implode(', ', $details->service) : ($details->service ?? 'NULL')],
                ['Ride Count', $details->ride_count ?? 0],
                ['Parcel Count', $details->parcel_count ?? 0],
            ]);
        } else {
            $this->error("No driver details found for driver {$driverId}");
        }

        $vehicle = Vehicle::where('driver_id', $driverId)->first();
        if ($vehicle) {
            $categoryIds = is_array($vehicle->category_id) ? $vehicle->category_id : [$vehicle->category_id];
            $this->table(['Vehicle', 'Value'], [
                ['Vehicle ID', $vehicle->id],
                ['Is Active', $vehicle->is_active ? '✅ Yes' : '❌ No'],
                ['Category IDs', implode(', ', array_filter($categoryIds))],
                ['Status', $vehicle->vehicle_request_status ?? 'NULL'],
            ]);
        } else {
            $this->error("No vehicle found for driver {$driverId}");
        }

        $location = UserLastLocation::where('user_id', $driverId)->first();
        if ($location) {
            $this->table(['Location', 'Value'], [
                ['Zone ID', $location->zone_id ?? 'NULL'],
                ['Latitude', $location->latitude ?? 'NULL'],
                ['Longitude', $location->longitude ?? 'NULL'],
                ['Last Updated', $location->updated_at ?? 'NULL'],
                ['Age', $location->updated_at ? $location->updated_at->diffForHumans() : 'Unknown'],
            ]);
        } else {
            $this->error("No location found for driver {$driverId}");
        }
    }
}
