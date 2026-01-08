<?php

namespace Modules\VehicleManagement\Http\Controllers\Api\New\Driver;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\VehicleManagement\Service\Interface\VehicleModelServiceInterface;
use Modules\VehicleManagement\Transformers\VehicleModelResource;

class VehicleModelController extends Controller
{

    protected $vehicleModelService;

    public function __construct(VehicleModelServiceInterface $vehicleModelService)
    {
        $this->vehicleModelService = $vehicleModelService;
    }

    public function modelList(Request $request): JsonResponse
    {
        // ZoneId is optional - if not provided, return all active models
        // This allows unauthenticated access during driver onboarding
        $relations = ['vehicles'];
        $criteria['is_active'] =  1;

        // Optionally filter by brand_id if provided
        if ($request->has('brand_id')) {
            $criteria['brand_id'] = $request->brand_id;
        }

        $models = $this->vehicleModelService->index(criteria: $criteria, relations: $relations, limit: $request['limit'], offset: $request['offset']);
        $modelList = VehicleModelResource::collection($models);
        return response()->json(responseFormatter(constant: DEFAULT_200, content: $modelList, limit: $request['limit'], offset: $request['offset']), 200);
    }
}
