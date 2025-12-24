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
