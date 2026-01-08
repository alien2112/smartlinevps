<?php

namespace Modules\VehicleManagement\Http\Controllers\Api\New\Driver;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\VehicleManagement\Http\Requests\VehicleApiStoreUpdateRequest;
use Modules\VehicleManagement\Interfaces\VehicleInterface;
use Modules\VehicleManagement\Service\Interface\VehicleServiceInterface;

class VehicleController extends Controller
{
    protected $vehicleService;


    public function __construct(VehicleServiceInterface $vehicleService)
    {
        $this->vehicleService = $vehicleService;
    }

    /**
     * Get all vehicles for the authenticated driver
     */
    public function index(Request $request): JsonResponse
    {
        $driver = auth('api')->user();

        if (!$driver || $driver->user_type !== 'driver') {
            return response()->json(responseFormatter(DEFAULT_403), 403);
        }

        $vehicles = $this->vehicleService->getBy(
            criteria: ['driver_id' => $driver->id],
            relations: ['brand', 'model', 'category'],
            orderBy: ['is_primary' => 'desc', 'created_at' => 'desc']
        );

        $formattedVehicles = $vehicles->map(function ($vehicle) {
            $images = $vehicle->documents ? getMediaUrl($vehicle->documents, 'vehicle/document') : [];
            
            return [
                'id' => $vehicle->id,
                'brand' => $vehicle->brand?->name,
                'model' => $vehicle->model?->name,
                'category' => $vehicle->category?->name,
                'licence_plate_number' => $vehicle->licence_plate_number,
                'licence_expire_date' => $vehicle->licence_expire_date?->format('Y-m-d'),
                'vin_number' => $vehicle->vin_number,
                'transmission' => $vehicle->transmission,
                'fuel_type' => $vehicle->fuel_type,
                'ownership' => $vehicle->ownership,
                'is_active' => $vehicle->is_active,
                'is_primary' => $vehicle->is_primary,
                'has_pending_primary_request' => $vehicle->has_pending_primary_request,
                'vehicle_request_status' => $vehicle->vehicle_request_status,
                'has_pending_update' => $vehicle->draft !== null,
                'images' => [
                    'car_front' => $images[0] ?? null,
                    'car_back' => $images[1] ?? null,
                ],
                'created_at' => $vehicle->created_at?->format('Y-m-d H:i:s'),
            ];
        });

        return response()->json(responseFormatter(DEFAULT_200, [
            'vehicles' => $formattedVehicles,
            'total' => $vehicles->count(),
        ]));
    }

    /**
     * Set a vehicle as primary (requires admin approval)
     */
    public function setPrimary(Request $request, $id): JsonResponse
    {
        $driver = auth('api')->user();

        if (!$driver || $driver->user_type !== 'driver') {
            return response()->json(responseFormatter(DEFAULT_403), 403);
        }

        $vehicle = $this->vehicleService->findOne($id);

        if (!$vehicle) {
            return response()->json(responseFormatter(DEFAULT_404), 404);
        }

        if ($vehicle->driver_id !== $driver->id) {
            return response()->json(responseFormatter(DEFAULT_403), 403);
        }

        if ($vehicle->vehicle_request_status !== APPROVED) {
            return response()->json(responseFormatter([
                'response_code' => 'vehicle_not_approved',
                'message' => 'Only approved vehicles can be set as primary'
            ]), 403);
        }

        if ($vehicle->is_primary) {
            return response()->json(responseFormatter([
                'response_code' => 'vehicle_already_primary',
                'message' => 'This vehicle is already your primary vehicle'
            ]), 400);
        }

        // Clear any existing pending primary requests for this driver
        $this->vehicleService->getBy(criteria: ['driver_id' => $driver->id, 'has_pending_primary_request' => true])
            ->each(function ($v) {
                $v->update(['has_pending_primary_request' => false]);
            });

        // Mark this vehicle as having a pending primary request
        $this->vehicleService->update($id, ['has_pending_primary_request' => true]);

        return response()->json(responseFormatter([
            'response_code' => 'vehicle_primary_pending',
            'message' => 'Primary vehicle change request submitted. Waiting for admin approval.'
        ]));
    }

    /**
     * Delete a vehicle (soft delete)
     */
    public function destroy($id): JsonResponse
    {
        $driver = auth('api')->user();

        if (!$driver || $driver->user_type !== 'driver') {
            return response()->json(responseFormatter(DEFAULT_403), 403);
        }

        $vehicle = $this->vehicleService->findOne($id);

        if (!$vehicle) {
            return response()->json(responseFormatter(DEFAULT_404), 404);
        }

        if ($vehicle->driver_id !== $driver->id) {
            return response()->json(responseFormatter(DEFAULT_403), 403);
        }

        // Don't allow deleting the only vehicle or primary vehicle with other vehicles
        $vehicleCount = $this->vehicleService->getBy(criteria: ['driver_id' => $driver->id])->count();

        if ($vehicle->is_primary && $vehicleCount > 1) {
            return response()->json(responseFormatter([
                'response_code' => 'cannot_delete_primary',
                'message' => 'Cannot delete primary vehicle. Please set another vehicle as primary first.'
            ]), 403);
        }

        $this->vehicleService->delete($id);

        return response()->json(responseFormatter([
            'response_code' => 'vehicle_deleted',
            'message' => 'Vehicle deleted successfully'
        ]));
    }

    public function store(VehicleApiStoreUpdateRequest $request)
    {
        // Allow multiple vehicles per driver
        // Check if this should be set as primary
        $existingVehicles = $this->vehicleService->getBy(criteria: ['driver_id' => $request->driver_id]);
        $isPrimary = $existingVehicles->isEmpty() || ($request->has('is_primary') && $request->is_primary);

        // Always set vehicle to PENDING status - requires admin approval
        $data = array_merge($request->validated(), [
            'vehicle_request_status' => PENDING,
            'is_primary' => $isPrimary
        ]);

        // If setting as primary, unset others
        if ($isPrimary) {
            $this->vehicleService->getBy(criteria: ['driver_id' => $request->driver_id])
                ->each(function ($vehicle) {
                    $vehicle->update(['is_primary' => false]);
                });
        }

        $this->vehicleService->create(data: $data);

        // Return approval pending message
        return response()->json(responseFormatter(VEHICLE_REQUEST_200), 200);
    }

    public function update(int|string $id, VehicleApiStoreUpdateRequest $request)
    {
        // Get the current vehicle
        $vehicle = $this->vehicleService->findOne(id: $id);

        if (!$vehicle) {
            return response()->json(responseFormatter(DEFAULT_404), 404);
        }

        // Check if driver owns this vehicle
        if ($vehicle->driver_id !== $request->driver_id) {
            return response()->json(responseFormatter(DEFAULT_403), 403);
        }

        // Store changes in draft for admin approval
        $vehicle = $this->vehicleService->updatedByDriver(id: $id, data: $request->validated());

        // Always return approval pending message for updates
        return response()->json(responseFormatter(VEHICLE_REQUEST_200), 200);
    }

    /**
     * Add new vehicle and request it as primary (requires admin approval)
     */
    public function storeAsPrimary(VehicleApiStoreUpdateRequest $request)
    {
        $driver = auth('api')->user();

        if (!$driver || $driver->user_type !== 'driver') {
            return response()->json(responseFormatter(DEFAULT_403), 403);
        }

        // Verify the driver_id matches authenticated user
        if ($request->driver_id !== $driver->id) {
            return response()->json(responseFormatter(DEFAULT_403), 403);
        }

        // Check if driver has any existing vehicles
        $existingVehicles = $this->vehicleService->getBy(criteria: ['driver_id' => $driver->id]);

        if ($existingVehicles->isEmpty()) {
            // First vehicle - will be primary automatically after approval
            $data = array_merge($request->validated(), [
                'vehicle_request_status' => PENDING,
                'is_primary' => false, // Will become primary after admin approval
                'has_pending_primary_request' => true // Mark as requested to be primary
            ]);
        } else {
            // Has existing vehicles - request this as new primary
            // Clear any other pending primary requests
            $existingVehicles->where('has_pending_primary_request', true)
                ->each(function ($v) {
                    $v->update(['has_pending_primary_request' => false]);
                });

            $data = array_merge($request->validated(), [
                'vehicle_request_status' => PENDING,
                'is_primary' => false, // Will become primary after admin approval
                'has_pending_primary_request' => true // Mark as requested to be primary
            ]);
        }

        $this->vehicleService->create(data: $data);

        return response()->json(responseFormatter([
            'response_code' => 'vehicle_primary_request_pending',
            'message' => 'Vehicle added successfully. Your request to set it as primary is pending admin approval.'
        ]), 200);
    }
}
