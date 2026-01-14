<?php

namespace App\Helpers;

use Illuminate\Support\Facades\DB;
use MatanYadaev\EloquentSpatial\Objects\Point;

/**
 * CoordinateHelper - THE SINGLE SOURCE OF TRUTH for coordinate handling
 *
 * COORDINATE HANDLING STANDARD:
 * =============================
 * This helper provides consistent coordinate handling across the entire application.
 *
 * DATABASE STORAGE FORMAT:
 * - MySQL POINT(X, Y) stores X=longitude, Y=latitude
 * - All coordinates MUST be stored with SRID 0 (not 4326) for consistent reading
 * - Use ST_GeomFromText('POINT(lng lat)', 0) when inserting raw SQL
 *
 * How Eloquent Spatial Works:
 * - new Point($latitude, $longitude) - Constructor takes lat first, then lng
 * - $point->latitude returns the latitude value (Y coordinate from DB)
 * - $point->longitude returns the longitude value (X coordinate from DB)
 * - When saving to MySQL, it outputs WKT as POINT(lng lat) which is CORRECT
 *
 * IMPORTANT FOR EGYPT COORDINATES:
 * - Alexandria: latitude ~31°N, longitude ~29-30°E
 * - Cairo: latitude ~30°N, longitude ~31°E
 * - For most Egyptian locations: latitude > longitude (Y > X in database)
 *
 * API Input Format:
 * - Customer apps send coordinates as [latitude, longitude] arrays
 * - Use createPointFromArray() for this format
 *
 * API Output Format (GeoJSON):
 * - GeoJSON standard is [longitude, latitude] - note the reversed order!
 * - Use formatForApi() which returns both GeoJSON format and explicit lat/lng keys
 *
 * Usage:
 *   // Create a Point from latitude/longitude
 *   $point = CoordinateHelper::createPoint($latitude, $longitude);
 *
 *   // Create from array [lat, lng] (common API input format)
 *   $point = CoordinateHelper::createPointFromArray($coordinates);
 *
 *   // Extract coordinates from a Point
 *   $coords = CoordinateHelper::extractFromPoint($point); // Returns ['lat' => ..., 'lng' => ...]
 *
 *   // Format for API response
 *   $formatted = CoordinateHelper::formatForApi($point); // Returns with GeoJSON structure
 *
 *   // Get raw SQL expression for a point (uses SRID 0)
 *   $expr = CoordinateHelper::pointExpression($latitude, $longitude);
 */
class CoordinateHelper
{
    /**
     * Create a Point object from latitude and longitude
     *
     * IMPORTANT: Uses SRID 0 by default to ensure consistent reading by Eloquent Spatial.
     * Do NOT use SRID 4326 as it causes coordinate swapping issues when reading from DB.
     *
     * @param float $latitude  Latitude value (e.g., 31.102053 for Alexandria)
     * @param float $longitude Longitude value (e.g., 29.7684138 for Alexandria)
     * @param int|null $srid   Spatial Reference ID (default: 0 - DO NOT use 4326)
     * @return Point
     */
    public static function createPoint(float $latitude, float $longitude, ?int $srid = 0): Point
    {
        return new Point($latitude, $longitude, $srid);
    }

    /**
     * Create a Point from a location object (e.g., UserLastLocation)
     *
     * @param object $location Object with latitude and longitude properties
     * @param int|null $srid   Spatial Reference ID (default: 0)
     * @return Point
     */
    public static function createPointFromLocation(object $location, ?int $srid = 0): Point
    {
        return new Point($location->latitude, $location->longitude, $srid);
    }

    /**
     * Create a Point from a [lat, lng] array (common API input format)
     *
     * @param array $coordinates Array where index 0 is latitude, index 1 is longitude
     * @param int|null $srid     Spatial Reference ID (default: 0)
     * @return Point
     */
    public static function createPointFromArray(array $coordinates, ?int $srid = 0): Point
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
     * Eloquent Spatial correctly handles coordinates:
     * - new Point($latitude, $longitude) stores as WKT POINT(lng lat)
     * - When reading: $point->latitude returns actual latitude
     * - When reading: $point->longitude returns actual longitude
     *
     * For Egypt/Alexandria: latitude ~31°N, longitude ~29-30°E
     *
     * @param Point|null $point
     * @return array{lat: float|null, lng: float|null}
     */
    public static function extractFromPoint(?Point $point): array
    {
        if (!$point) {
            return ['lat' => null, 'lng' => null];
        }

        // Direct mapping - Eloquent Spatial returns correct values
        return [
            'lat' => $point->latitude,
            'lng' => $point->longitude,
        ];
    }

    /**
     * Format coordinates for API response (Flutter/Mobile app friendly)
     *
     * Returns a standardized structure with:
     * - 'type': 'Point' (GeoJSON type)
     * - 'coordinates': [lng, lat] (GeoJSON format - note: longitude first!)
     * - 'lat': float (explicit latitude for easy access)
     * - 'lng': float (explicit longitude for easy access)
     *
     * @param Point|null $point
     * @return array|null Returns null if point is null
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
