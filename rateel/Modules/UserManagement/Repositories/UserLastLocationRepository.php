<?php

namespace Modules\UserManagement\Repositories;

use Modules\UserManagement\Entities\UserLastLocation;
use Modules\UserManagement\Interfaces\UserLastLocationInterface;

class UserLastLocationRepository implements UserLastLocationInterface
{
    public function __construct(private UserLastLocation $last_location)
    {
    }

    public function get(int $limit, bool $dynamic_page = false, array $except = [], array $attributes = [], array $relations = []): mixed
    {
        return $this->last_location->selectRaw("* ,
        ( 6371 * acos( cos( radians(?) ) *
          cos( radians( latitude ) )
          * cos( radians( longitude ) - radians(?)
          ) + sin( radians(?) ) *
          sin( radians( latitude ) ) )
        ) AS distance", [$attributes['latitude'], $attributes['longitude'], $attributes['latitude']])
            ->where('type', '=', 'driver')
            ->having("distance", "<", $attributes['radius'])
            ->orderBy("distance")
            ->limit($limit)
            ->get();
    }

    public function getBy(string $column, int|string $value, array $attributes = []): mixed
    {
        return $this->last_location
            ->query()
            ->where($column, $value)
            ->first();
    }

    public function updateOrCreate($attributes): mixed
    {
        $lat = $attributes['latitude'];
        $lng = $attributes['longitude'];

        $location = $this->last_location->query()
            ->updateOrInsert(['user_id' => $attributes['user_id']], [
                'type' => $attributes['type'],
                'latitude' => $lat,
                'longitude' => $lng,
                'location_point' => \DB::raw("ST_SRID(POINT($lng, $lat), 4326)"),
                'zone_id' => $attributes['zone_id'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        return $location;
    }

    public function getNearestDrivers($attributes): mixed
    {
        // Get max drivers to notify from config, default 50
        $maxDrivers = (int) ($attributes['limit'] ?? config('business.max_drivers_to_notify', 50));

        return $this->last_location
            ->selectRaw("user_last_locations.id, user_last_locations.user_id, user_last_locations.latitude, user_last_locations.longitude, user_last_locations.zone_id, user_last_locations.type,
                ( 6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude)
                - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) AS distance",
                [$attributes['latitude'], $attributes['longitude'], $attributes['latitude']])
            ->where('type', 'driver')
            ->where('zone_id', $attributes['zone_id'])
            ->having('distance', '<', $attributes['radius'])
            // Eager load ALL relations accessed in TripRequestController loops
            ->with([
                'user:id,first_name,last_name,phone,fcm_token,is_active,profile_image',
                'user.vehicle:id,driver_id,category_id,brand_id,model_id,is_active,licence_plate_number,parcel_weight_capacity',
                'user.vehicle.category:id,name,type,category_level',
                'user.vehicle.model:id,name',
                'driverDetails:id,user_id,is_online,availability_status,service,ride_count,parcel_count'
            ])
            ->whereHas('user', fn($query) => $query->where('is_active', true))
            ->whereHas('driverDetails', fn($query) => $query->where('is_online', true)
                ->whereNotIn('availability_status', ['unavailable', 'on_trip'])
            )
            ->whereHas('user.vehicle', fn($query) => $query->where('is_active', true))
            ->when(array_key_exists('vehicle_category_id', $attributes), function ($query) use ($attributes) {
                // Support both exact category match and category level matching
                // Higher level drivers can accept lower level requests (e.g., VIP can accept Budget)
                if (array_key_exists('category_level', $attributes) && $attributes['category_level']) {
                    // Use category level: drivers with equal or higher level can accept
                    $query->whereHas('user.vehicle.category', fn($q) => 
                        $q->where('category_level', '>=', $attributes['category_level'])
                    )->whereHas('user.vehicle', fn($q) => $q->ofStatus(1));
                } else {
                    // Fallback to exact category matching
                    $query->whereHas('user.vehicle', fn($query) => $query->ofStatus(1)->where('category_id', $attributes['vehicle_category_id']));
                }
            })
            ->when(array_key_exists('service', $attributes),
                fn($query) => $query->whereHas('driverDetails',
                    fn($query) => $query->where(fn($query) => $query->whereNull('service')
                        ->orWhere(fn($query) => $query->whereNotNull('service')
                            ->whereJsonContains('service', $attributes['service'])
                        )
                    )
                )
            )
            ->when($attributes['parcel_weight_capacity'] ?? null,
                fn($query) => $query->whereHas('driverDetails',
                    fn($query) => $query->where(fn($query) => $query->whereNull('service')
                        ->orWhere(fn($query) => $query->whereNotNull('service')
                            ->whereJsonContains('service', 'parcel')
                        )
                    )
                )->whereHas('user.vehicle',
                    fn($query) => $query->whereNull('parcel_weight_capacity')
                        ->orWhere('parcel_weight_capacity', '>=', $attributes['parcel_weight_capacity'])
                )
            )
            ->orderBy('distance')
            ->limit($maxDrivers)
            ->get();
    }
}
