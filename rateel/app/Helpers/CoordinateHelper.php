<?php

namespace App\Helpers;

use Illuminate\Support\Facades\DB;
use MatanYadaev\EloquentSpatial\Objects\Point;

/**
 * CoordinateHelper - Centralized coordinate handling for the entire system
 *
 * This helper solves the coordinate order inconsistency issue throughout the application.
 * MySQL's POINT format is POINT(longitude latitude) - X Y format where X is longitude.
 *
 * Usage:
 *   // Create a Point from latitude/longitude
 *   $point = CoordinateHelper::createPoint($latitude, $longitude);
 *
 *   // Update coordinates using raw SQL (bypasses Eloquent Spatial bug)
 *   CoordinateHelper::updateDropCoordinates($tripRequestId, $latitude, $longitude);
 *
 *   // Get raw SQL expression for a point
 *   $expr = CoordinateHelper::pointExpression($latitude, $longitude);
 */
class CoordinateHelper
{
    /**
     * Create a Point object from latitude and longitude
     *
     * @param float $latitude  Latitude value (e.g., 31.102053 for Alexandria)
     * @param float $longitude Longitude value (e.g., 29.7684138 for Alexandria)
     * @param int|null $srid   Spatial Reference ID (default: 4326 for WGS84)
     * @return Point
     */
    public static function createPoint(float $latitude, float $longitude, ?int $srid = 4326): Point
    {
        return new Point($latitude, $longitude, $srid);
    }

    /**
     * Create a Point from a location object (e.g., UserLastLocation)
     *
     * @param object $location Object with latitude and longitude properties
     * @param int|null $srid   Spatial Reference ID
     * @return Point
     */
    public static function createPointFromLocation(object $location, ?int $srid = 4326): Point
    {
        return new Point($location->latitude, $location->longitude, $srid);
    }

    /**
     * Create a Point from a [lat, lng] array (common API input format)
     *
     * @param array $coordinates Array where index 0 is latitude, index 1 is longitude
     * @param int|null $srid     Spatial Reference ID
     * @return Point
     */
    public static function createPointFromArray(array $coordinates, ?int $srid = 4326): Point
    {
        return new Point($coordinates[0], $coordinates[1], $srid);
    }

    /**
     * Get a raw SQL expression for ST_GeomFromText
     * Use this when Eloquent Spatial's automatic conversion fails
     *
     * @param float $latitude
     * @param float $longitude
     * @return \Illuminate\Database\Query\Expression
     */
    public static function pointExpression(float $latitude, float $longitude): \Illuminate\Database\Query\Expression
    {
        // MySQL POINT format: POINT(longitude latitude) - X Y format
        return DB::raw("ST_GeomFromText('POINT({$longitude} {$latitude})')");
    }

    /**
     * Get a raw SQL expression for ST_GeomFromText with SRID
     *
     * @param float $latitude
     * @param float $longitude
     * @param int $srid
     * @return \Illuminate\Database\Query\Expression
     */
    public static function pointExpressionWithSrid(float $latitude, float $longitude, int $srid = 4326): \Illuminate\Database\Query\Expression
    {
        return DB::raw("ST_GeomFromText('POINT({$longitude} {$latitude})', {$srid})");
    }

    /**
     * Update drop_coordinates for a trip using raw SQL
     * This bypasses the Eloquent Spatial package bug
     *
     * @param string $tripRequestId
     * @param float $latitude
     * @param float $longitude
     * @return bool
     */
    public static function updateDropCoordinates(string $tripRequestId, float $latitude, float $longitude): bool
    {
        return DB::table('trip_request_coordinates')
            ->where('trip_request_id', $tripRequestId)
            ->update([
                'drop_coordinates' => self::pointExpression($latitude, $longitude),
                'updated_at' => now(),
            ]) > 0;
    }

    /**
     * Update any coordinate column for a trip using raw SQL
     *
     * @param string $tripRequestId
     * @param string $column Column name (e.g., 'drop_coordinates', 'start_coordinates')
     * @param float $latitude
     * @param float $longitude
     * @return bool
     */
    public static function updateCoordinate(string $tripRequestId, string $column, float $latitude, float $longitude): bool
    {
        $allowedColumns = [
            'pickup_coordinates',
            'destination_coordinates',
            'start_coordinates',
            'drop_coordinates',
            'driver_accept_coordinates',
            'customer_request_coordinates',
            'int_coordinate_1',
            'int_coordinate_2',
        ];

        if (!in_array($column, $allowedColumns)) {
            throw new \InvalidArgumentException("Invalid coordinate column: {$column}");
        }

        return DB::table('trip_request_coordinates')
            ->where('trip_request_id', $tripRequestId)
            ->update([
                $column => self::pointExpression($latitude, $longitude),
                'updated_at' => now(),
            ]) > 0;
    }

    /**
     * Extract latitude and longitude from a Point object
     *
     * IMPORTANT: This expects Points created with correct (lat, lng) order using
     * CoordinateHelper::createPoint() or CoordinateHelper::createPointFromArray()
     *
     * @param Point|null $point
     * @return array{lat: float|null, lng: float|null}
     */
    public static function extractFromPoint(?Point $point): array
    {
        if (!$point) {
            return ['lat' => null, 'lng' => null];
        }

        return [
            'lat' => $point->latitude,
            'lng' => $point->longitude,
        ];
    }

    /**
     * Format coordinates for API response (Flutter-friendly)
     *
     * @param Point|null $point
     * @return array|null
     */
    public static function formatForApi(?Point $point): ?array
    {
        if (!$point) {
            return null;
        }

        $coords = self::extractFromPoint($point);

        return [
            'type' => 'Point',
            'coordinates' => [$coords['lng'], $coords['lat']], // GeoJSON format: [lng, lat]
            'lat' => $coords['lat'],
            'lng' => $coords['lng'],
        ];
    }

    /**
     * Validate coordinate values
     *
     * @param float $latitude
     * @param float $longitude
     * @return bool
     */
    public static function isValid(float $latitude, float $longitude): bool
    {
        return $latitude >= -90 && $latitude <= 90
            && $longitude >= -180 && $longitude <= 180;
    }

    /**
     * Calculate distance between two points in meters using Haversine formula
     *
     * @param float $lat1
     * @param float $lng1
     * @param float $lat2
     * @param float $lng2
     * @return float Distance in meters
     */
    public static function calculateDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371000; // meters

        $lat1Rad = deg2rad($lat1);
        $lat2Rad = deg2rad($lat2);
        $deltaLat = deg2rad($lat2 - $lat1);
        $deltaLng = deg2rad($lng2 - $lng1);

        $a = sin($deltaLat / 2) * sin($deltaLat / 2) +
             cos($lat1Rad) * cos($lat2Rad) *
             sin($deltaLng / 2) * sin($deltaLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
