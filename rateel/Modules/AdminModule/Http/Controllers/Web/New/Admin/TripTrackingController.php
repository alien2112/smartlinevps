<?php

namespace Modules\AdminModule\Http\Controllers\Web\New\Admin;

use App\Http\Controllers\BaseController;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Modules\TripManagement\Entities\TripRequest;
use Modules\ZoneManagement\Service\Interface\ZoneServiceInterface;

class TripTrackingController extends BaseController
{
    protected $zoneService;

    public function __construct(ZoneServiceInterface $zoneService)
    {
        parent::__construct($zoneService);
        $this->zoneService = $zoneService;
    }

    /**
     * Display the trip tracking page
     */
    public function index(?Request $request = null, ?string $type = null): View|Collection|LengthAwarePaginator|RedirectResponse|callable|null
    {
        $zones = $this->zoneService->getAll();

        return view('adminmodule::trip-tracking', compact('zones'));
    }

    /**
     * AJAX endpoint for fetching trip tracking data
     */
    public function tripTrackingData(Request $request): JsonResponse
    {
        // Validate inputs
        $request->validate([
            'zone_id' => 'nullable|exists:zones,id',
            'driver_id' => 'nullable|exists:users,id',
            'status' => 'nullable|in:pending,accepted,ongoing,completed,cancelled',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ]);

        // Default to today if no dates provided
        $dateFrom = $request->date_from ?? now()->startOfDay();
        $dateTo = $request->date_to ?? now()->endOfDay();

        // Optimized query with eager loading
        $trips = TripRequest::query()
            ->with([
                'coordinate',              // Trip path coordinates
                'driver.lastLocations',    // Live driver position
                'driver.vehicle.model',    // Vehicle info
                'customer',                // Customer info
                'vehicleCategory'          // Category for icons
            ])
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->when($request->zone_id, fn($q) => $q->where('zone_id', $request->zone_id))
            ->when($request->driver_id, fn($q) => $q->where('driver_id', $request->driver_id))
            ->when($request->status, fn($q) => $q->where('current_status', $request->status))
            ->orderBy('created_at', 'desc')
            ->limit(500)  // Performance cap
            ->get();

        // Build trip data
        $tripData = $trips->map(function ($trip) {
            return [
                'id' => $trip->id,
                'ref_id' => $trip->ref_id,
                'status' => $trip->current_status,
                'driver' => $trip->driver ? [
                    'id' => $trip->driver->id,
                    'name' => $trip->driver->first_name . ' ' . $trip->driver->last_name,
                    'phone' => $trip->driver->phone,
                    'vehicle' => $trip->driver->vehicle ?
                        $trip->driver->vehicle->model->name ?? 'N/A' : 'N/A'
                ] : null,
                'customer' => $trip->customer ? [
                    'name' => $trip->customer->first_name . ' ' . $trip->customer->last_name,
                    'phone' => $trip->customer->phone,
                ] : null,
                'path' => $this->buildTripPath($trip->coordinate),
                'current_location' => $this->getDriverCurrentLocation($trip->driver),
                'created_at' => $trip->created_at->format('Y-m-d H:i:s'),
            ];
        });

        // Get zone polygons
        $polygons = $request->zone_id
            ? $this->getZonePolygons($request->zone_id)
            : [];

        return response()->json([
            'trips' => $tripData,
            'polygons' => $polygons,
        ]);
    }

    /**
     * Build trip path from coordinates
     */
    private function buildTripPath($coordinate): array
    {
        if (!$coordinate) return [];

        $path = [];

        // Pickup coordinates
        if ($coordinate->pickup_coordinates) {
            $path[] = [
                'lat' => $coordinate->pickup_coordinates->latitude,
                'lng' => $coordinate->pickup_coordinates->longitude,
                'type' => 'pickup'
            ];
        }

        // Intermediate coordinates
        if ($coordinate->int_coordinate_1) {
            $path[] = [
                'lat' => $coordinate->int_coordinate_1->latitude,
                'lng' => $coordinate->int_coordinate_1->longitude,
                'type' => 'intermediate'
            ];
        }
        if ($coordinate->int_coordinate_2) {
            $path[] = [
                'lat' => $coordinate->int_coordinate_2->latitude,
                'lng' => $coordinate->int_coordinate_2->longitude,
                'type' => 'intermediate'
            ];
        }

        // Destination coordinates
        if ($coordinate->destination_coordinates) {
            $path[] = [
                'lat' => $coordinate->destination_coordinates->latitude,
                'lng' => $coordinate->destination_coordinates->longitude,
                'type' => 'destination'
            ];
        }

        return $path;
    }

    /**
     * Get driver's current location from last_locations table
     */
    private function getDriverCurrentLocation($driver): ?array
    {
        if (!$driver || !$driver->lastLocations) {
            return null;
        }

        return [
            'lat' => $driver->lastLocations->latitude,
            'lng' => $driver->lastLocations->longitude,
            'updated_at' => $driver->lastLocations->updated_at->diffForHumans(),
        ];
    }

    /**
     * Get zone polygons for map display
     */
    private function getZonePolygons($zoneId): array
    {
        $zone = $this->zoneService->findOne(id: $zoneId);
        if (!$zone || !$zone->coordinates) {
            return [];
        }

        return [formatCoordinates(json_decode($zone->coordinates[0]->toJson(), true)['coordinates'])];
    }
}
