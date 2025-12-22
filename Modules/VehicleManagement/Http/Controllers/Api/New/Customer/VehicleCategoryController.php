<?php

namespace Modules\VehicleManagement\Http\Controllers\Api\New\Customer;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\VehicleManagement\Service\Interface\VehicleCategoryServiceInterface;
use Modules\VehicleManagement\Transformers\VehicleCategoryResource;


class VehicleCategoryController extends Controller
{
    protected $vehicleCategoryService;
    public function __construct(VehicleCategoryServiceInterface $category)
    {
        $this->vehicleCategoryService = $category;
    }


    public function categoryFareList(Request $request): JsonResponse
    {


        if (empty($request->header('zoneId'))) {

            return response()->json(responseFormatter(ZONE_404), 200);
        }

        $relations = [
            'tripFares' => [
                ['zone_id', '=', $request->header('zoneId')]
            ]
        ];
        $whereHasRelations = [
            'tripFares' => ['zone_id' => $request->header('zoneId')]
        ];
        $orderBy = [
    'created_at' => 'Asc'
];
        $categories = $this->vehicleCategoryService->getActiveCategoriesCached(
            zoneId: (int)$request->header('zoneId'),
            limit: $request['limit'],
            offset: $request['offset'],
            relations: $relations,
            whereHasRelations: $whereHasRelations,
            orderBy: $orderBy
        );

        $data = VehicleCategoryResource::collection($categories);


        return response()->json(responseFormatter(constant: DEFAULT_200, content: $data, limit: $request['limit'], offset: $request['offset']), 200);
    }
}
