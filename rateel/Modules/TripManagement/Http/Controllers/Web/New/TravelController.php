<?php

namespace Modules\TripManagement\Http\Controllers\Web\New;

use App\Http\Controllers\BaseController;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Modules\TripManagement\Service\Interface\TravelRideServiceInterface;
use Modules\TripManagement\Service\Interface\TripRequestServiceInterface;
use Modules\UserManagement\Service\Interface\DriverServiceInterface;
use Modules\VehicleManagement\Entities\VehicleCategory;
use Brian2694\Toastr\Facades\Toastr;

class TravelController extends BaseController
{
    use AuthorizesRequests;

    protected TripRequestServiceInterface $tripRequestService;
    protected TravelRideServiceInterface $travelRideService;
    protected DriverServiceInterface $driverService;

    public function __construct(
        TripRequestServiceInterface $tripRequestService,
        TravelRideServiceInterface $travelRideService,
        DriverServiceInterface $driverService
    ) {
        parent::__construct($tripRequestService);
        $this->tripRequestService = $tripRequestService;
        $this->travelRideService = $travelRideService;
        $this->driverService = $driverService;
    }

    /**
     * List pending travel requests (UNIFIED APPROACH)
     */
    public function index(?Request $request, string $type = null): View|Collection|LengthAwarePaginator|null|callable|RedirectResponse
    {
        $this->authorize('trip_view');

        // Use unified trip query - filter by trip_type='travel' or is_travel=true
        $pendingTravels = $this->tripRequestService->getBy(
            criteria: ['current_status' => 'pending'],
            relations: ['customer', 'driver', 'vehicleCategory', 'coordinate', 'fee', 'time', 'tripStatus', 'zone'],
            orderBy: ['scheduled_at' => 'asc', 'offer_price' => 'desc']
        )->filter(function ($trip) {
            // Filter for travel trips only (supports both old and new fields)
            return $trip->trip_type === 'travel' || $trip->is_travel === true;
        });

        // Get VIP drivers for assignment dropdown
        $vipDrivers = $this->driverService->getBy(
            criteria: ['user_type' => 'driver', 'is_active' => true],
            relations: ['vehicle.category', 'driverDetails']
        )->filter(function ($driver) {
            return $driver->vehicle?->category?->category_level === VehicleCategory::LEVEL_VIP
                && $driver->driverDetails?->is_online
                && $driver->driverDetails?->availability_status === 'available';
        });

        return view('tripmanagement::admin.travel.index', [
            'pendingTravels' => $pendingTravels,
            'vipDrivers' => $vipDrivers,
        ]);
    }

    /**
     * Show travel request details
     */
    public function show(string $tripId): View
    {
        $this->authorize('trip_view');

        $trip = $this->tripRequestService->findOne(
            id: $tripId,
            relations: ['customer', 'driver', 'vehicleCategory', 'coordinate', 'fee', 'time', 'tripStatus', 'zone']
        );

        if (!$trip || !$trip->isTravel()) {
            abort(404, 'Travel request not found');
        }

        // Get VIP drivers for assignment
        $vipDrivers = $this->driverService->getBy(
            criteria: ['user_type' => 'driver', 'is_active' => true],
            relations: ['vehicle.category', 'driverDetails', 'lastLocations']
        )->filter(function ($driver) {
            return $driver->vehicle?->category?->category_level === VehicleCategory::LEVEL_VIP;
        });

        return view('tripmanagement::admin.travel.show', [
            'trip' => $trip,
            'vipDrivers' => $vipDrivers,
        ]);
    }

    /**
     * Assign a VIP driver to a travel request (UNIFIED APPROACH)
     */
    public function assignDriver(Request $request, string $tripId): JsonResponse
    {
        $this->authorize('trip_edit');

        $request->validate([
            'driver_id' => 'required|uuid|exists:users,id',
        ]);

        try {
            $trip = $this->tripRequestService->findOne(id: $tripId);

            if (!$trip || !$trip->isTravel()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Travel request not found',
                ], 404);
            }

            // Check if trip is pending
            if ($trip->current_status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Trip is no longer pending',
                ], 400);
            }

            // Update trip with assigned driver
            $this->tripRequestService->update(
                id: $tripId,
                data: [
                    'driver_id' => $request->driver_id,
                    'current_status' => 'accepted',
                    'travel_status' => 'accepted', // Backward compatibility
                    'travel_dispatched_at' => now(),
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Driver assigned successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Cancel a travel request (UNIFIED APPROACH)
     */
    public function cancel(Request $request, string $tripId): JsonResponse
    {
        $this->authorize('trip_edit');

        $reason = $request->input('reason', 'Cancelled by admin');

        try {
            $trip = $this->tripRequestService->findOne(id: $tripId);

            if (!$trip || !$trip->isTravel()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Travel request not found',
                ], 404);
            }

            // Update trip status to cancelled
            $this->tripRequestService->update(
                id: $tripId,
                data: [
                    'current_status' => 'cancelled',
                    'travel_status' => 'cancelled', // Backward compatibility
                    'trip_cancellation_reason' => $reason,
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Travel request cancelled',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get available VIP drivers for AJAX dropdown (UNIFIED APPROACH)
     */
    public function getAvailableDrivers(Request $request): JsonResponse
    {
        $this->authorize('trip_view');

        $lat = $request->input('lat');
        $lng = $request->input('lng');
        $radiusKm = $request->input('radius', 50); // Default travel radius

        // Get all online VIP drivers
        $drivers = $this->driverService->getBy(
            criteria: ['user_type' => 'driver', 'is_active' => true],
            relations: ['vehicle.category', 'driverDetails', 'lastLocations']
        )->filter(function ($driver) use ($lat, $lng, $radiusKm) {
            // Must be VIP
            $isVip = $driver->vehicle?->category?->category_level === VehicleCategory::LEVEL_VIP;
            if (!$isVip) return false;

            // Must be online and available
            $isAvailable = $driver->driverDetails?->is_online
                && $driver->driverDetails?->availability_status === 'available';
            if (!$isAvailable) return false;

            // If location provided, filter by radius
            if ($lat && $lng && $driver->lastLocations) {
                $driverLat = $driver->lastLocations->latitude;
                $driverLng = $driver->lastLocations->longitude;

                // Calculate distance in km using Haversine formula
                $earthRadius = 6371; // km
                $latDiff = deg2rad($driverLat - $lat);
                $lngDiff = deg2rad($driverLng - $lng);

                $a = sin($latDiff / 2) * sin($latDiff / 2) +
                     cos(deg2rad($lat)) * cos(deg2rad($driverLat)) *
                     sin($lngDiff / 2) * sin($lngDiff / 2);
                $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
                $distance = $earthRadius * $c;

                $driver->distance_km = round($distance, 2);

                return $distance <= $radiusKm;
            }

            return true;
        });

        $driverData = $drivers->map(function ($driver) {
            $user = $driver->user ?? $driver;
            return [
                'id' => $user->id,
                'name' => $user->first_name . ' ' . $user->last_name,
                'phone' => $user->phone,
                'distance_km' => $driver->distance_km ?? null,
            ];
        })->sortBy('distance_km');

        return response()->json([
            'success' => true,
            'drivers' => $driverData->values(),
        ]);
    }
}
