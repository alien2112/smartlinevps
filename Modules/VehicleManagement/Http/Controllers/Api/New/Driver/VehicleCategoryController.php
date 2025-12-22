<?php

namespace Modules\VehicleManagement\Http\Controllers\Api\New\Driver;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\VehicleManagement\Service\Interface\VehicleCategoryServiceInterface;
use Modules\VehicleManagement\Transformers\VehicleCategoryResource;
use Modules\VehicleManagement\Entities\VehicleCategory;

class VehicleCategoryController extends Controller
{

    protected $vehicleCategoryService;
    public function __construct(VehicleCategoryServiceInterface $vehicleCategoryService)
    {
        $this->vehicleCategoryService = $vehicleCategoryService;
    }

    public function list(Request $request)
    {
        $categories = $this->vehicleCategoryService->getActiveCategoriesCached(
            limit: $request['limit'],
            offset: $request['offset']
        );
        $data = VehicleCategoryResource::collection($categories);

        return response()->json(responseFormatter(constant: DEFAULT_200, content: $data, limit: $request['limit'], offset: $request['offset']), 200);
    }
}
