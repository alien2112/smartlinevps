<?php

namespace Modules\TripManagement\Http\Controllers\Api\New\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\TripManagement\Http\Requests\LostItemStoreRequest;
use Modules\TripManagement\Service\Interface\LostItemServiceInterface;
use Modules\TripManagement\Service\Interface\TripRequestServiceInterface;
use Modules\TripManagement\Transformers\LostItemResource;

class LostItemController extends Controller
{
    protected $lostItemService;
    protected $tripRequestService;

    public function __construct(
        LostItemServiceInterface $lostItemService,
        TripRequestServiceInterface $tripRequestService
    ) {
        $this->lostItemService = $lostItemService;
        $this->tripRequestService = $tripRequestService;
    }

    /**
     * Create a lost item report
     * POST /api/customer/lost-items
     */
    public function store(LostItemStoreRequest $request): JsonResponse
    {
        // Verify trip exists and belongs to customer
        $trip = $this->tripRequestService->findOne(
            id: $request->trip_request_id,
            relations: ['customer']
        );

        if (!$trip) {
            return response()->json(responseFormatter(TRIP_REQUEST_404), 404);
        }

        // Verify trip belongs to current customer
        if ($trip->customer_id !== auth('api')->id()) {
            return response()->json(responseFormatter(ACCESS_DENIED_403), 403);
        }

        // Verify trip is completed
        if ($trip->current_status !== 'completed') {
            return response()->json(responseFormatter(constant: LOST_ITEM_TRIP_NOT_COMPLETED_403), 403);
        }

        // Check for duplicate report
        if ($this->lostItemService->hasDuplicateReport($request->trip_request_id, $request->category)) {
            return response()->json(responseFormatter(constant: LOST_ITEM_DUPLICATE_403), 403);
        }

        try {
            $lostItem = $this->lostItemService->create($request->all());

            if (!$lostItem) {
                return response()->json(responseFormatter(DEFAULT_400), 400);
            }

            // Send notification to driver
            $this->notifyDriver($trip);

            $lostItem->load(['trip.coordinate', 'driver', 'statusLogs']);

            return response()->json(responseFormatter(
                constant: LOST_ITEM_STORE_200,
                content: new LostItemResource($lostItem)
            ));
        } catch (\Exception $e) {
            return response()->json(responseFormatter(DEFAULT_400, [], ['error' => $e->getMessage()]), 400);
        }
    }

    /**
     * Get customer's lost item reports
     * GET /api/customer/lost-items
     */
    public function index(Request $request): JsonResponse
    {
        $limit = $request->get('limit', 10);
        $offset = $request->get('offset', 1);

        $lostItems = $this->lostItemService->getByCustomer(
            customerId: auth('api')->id(),
            limit: $limit,
            offset: $offset
        );

        return response()->json(responseFormatter(
            constant: DEFAULT_200,
            content: LostItemResource::collection($lostItems),
            limit: $limit,
            offset: $offset
        ));
    }

    /**
     * Get single lost item details
     * GET /api/customer/lost-items/{id}
     */
    public function show(string $id): JsonResponse
    {
        $lostItem = $this->lostItemService->findOne(
            id: $id,
            relations: ['trip.coordinate', 'driver', 'statusLogs']
        );

        if (!$lostItem) {
            return response()->json(responseFormatter(LOST_ITEM_NOT_FOUND_404), 404);
        }

        // Verify belongs to current customer
        if ($lostItem->customer_id !== auth('api')->id()) {
            return response()->json(responseFormatter(ACCESS_DENIED_403), 403);
        }

        return response()->json(responseFormatter(
            constant: DEFAULT_200,
            content: new LostItemResource($lostItem)
        ));
    }

    /**
     * Notify driver about lost item report
     */
    protected function notifyDriver($trip): void
    {
        try {
            $push = getNotification('lost_item_reported');
            if ($push && $trip->driver) {
                sendDeviceNotification(
                    fcm_token: $trip->driver->fcm_token,
                    title: translate($push['title'] ?? 'Lost Item Reported'),
                    description: translate($push['description'] ?? 'A passenger reported a lost item from your recent trip. Please check your vehicle.'),
                    status: $push['status'] ?? 1,
                    ride_request_id: $trip->id,
                    type: 'lost_item',
                    action: 'lost_item_reported',
                    user_id: $trip->driver->id
                );
            }
        } catch (\Exception $e) {
            \Log::warning('Failed to send lost item notification to driver: ' . $e->getMessage());
        }
    }
}
