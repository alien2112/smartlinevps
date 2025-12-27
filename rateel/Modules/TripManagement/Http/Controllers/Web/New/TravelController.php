<?php

namespace Modules\TripManagement\Http\Controllers\Web\New;

use App\Http\Controllers\BaseController;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
     * List pending travel requests
     */
    public function index(Request $request): View
    {
        $this->authorize('trip_view');

        $pendingTravels = $this->travelRideService->getPendingTravelRequests();

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
     * Assign a VIP driver to a travel request
     */
    public function assignDriver(Request $request, string $tripId): JsonResponse
    {
        $this->authorize('trip_edit');

        $request->validate([
            'driver_id' => 'required|uuid|exists:users,id',
        ]);

        try {
            $success = $this->travelRideService->assignDriverToTravel(
                $tripId,
                $request->driver_id
            );

            if ($success) {
                return response()->json([
                    'success' => true,
                    'message' => 'Driver assigned successfully',
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to assign driver',
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Cancel a travel request
     */
    public function cancel(Request $request, string $tripId): JsonResponse
    {
        $this->authorize('trip_edit');

        $reason = $request->input('reason', 'Cancelled by admin');

        try {
            $success = $this->travelRideService->cancelTravelRequest($tripId, $reason);

            if ($success) {
                return response()->json([
                    'success' => true,
                    'message' => 'Travel request cancelled',
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel travel request',
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get available VIP drivers for AJAX dropdown
     */
    public function getAvailableDrivers(Request $request): JsonResponse
    {
        $this->authorize('trip_view');

        $lat = $request->input('lat');
        $lng = $request->input('lng');
        $radiusKm = $request->input('radius', 30);

        if ($lat && $lng) {
            $drivers = $this->travelRideService->getAvailableVipDrivers(
                (float) $lat,
                (float) $lng,
                (float) $radiusKm
            );
        } else {
            // Get all online VIP drivers
            $drivers = $this->driverService->getBy(
                criteria: ['user_type' => 'driver', 'is_active' => true],
                relations: ['vehicle.category', 'driverDetails', 'lastLocations']
            )->filter(function ($driver) {
                return $driver->vehicle?->category?->category_level === VehicleCategory::LEVEL_VIP
                    && $driver->driverDetails?->is_online
                    && $driver->driverDetails?->availability_status === 'available';
            });
        }

        $driverData = $drivers->map(function ($driver) {
            $user = $driver->user ?? $driver;
            return [
                'id' => $user->id,
                'name' => $user->first_name . ' ' . $user->last_name,
                'phone' => $user->phone,
                'distance_km' => $driver->distance_km ?? null,
            ];
        });

        return response()->json([
            'success' => true,
            'drivers' => $driverData->values(),
        ]);
    }
}
