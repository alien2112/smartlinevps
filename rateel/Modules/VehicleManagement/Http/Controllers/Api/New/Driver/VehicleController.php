<?php

namespace Modules\VehicleManagement\Http\Controllers\Api\New\Driver;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\JsonResponse;
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

    public function store(VehicleApiStoreUpdateRequest $request)
    {
        // Allow multiple vehicles per driver
        // Check if this should be set as primary
        $existingVehicles = $this->vehicleService->getBy(criteria: ['driver_id' => $request->driver_id]);
        $isPrimary = $existingVehicles->isEmpty() || ($request->has('is_primary') && $request->is_primary);
        
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
        return response()->json(responseFormatter(VEHICLE_CREATE_200), 200);
    }

    public function update(int|string $id, VehicleApiStoreUpdateRequest $request)
    {
        $vehicle = $this->vehicleService->updatedByDriver(id:$id, data: $request->validated());
        if ($vehicle?->vehicle_request_status == APPROVED && $vehicle?->draft) {
            return response()->json(responseFormatter(VEHICLE_REQUEST_200), 200);
        }
        return response()->json(responseFormatter(VEHICLE_UPDATE_200), 200);
    }
}
