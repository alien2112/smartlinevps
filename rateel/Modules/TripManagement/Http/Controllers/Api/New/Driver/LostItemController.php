<?php

namespace Modules\TripManagement\Http\Controllers\Api\New\Driver;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\TripManagement\Http\Requests\LostItemUpdateRequest;
use Modules\TripManagement\Service\Interface\LostItemServiceInterface;
use Modules\TripManagement\Transformers\LostItemResource;

class LostItemController extends Controller
{
    protected $lostItemService;

    public function __construct(LostItemServiceInterface $lostItemService)
    {
        $this->lostItemService = $lostItemService;
    }

    /**
     * Get lost items reported for driver's trips
     * GET /api/driver/lost-items
     */
    public function index(Request $request): JsonResponse
    {
        $limit = $request->get('limit', 10);
        $offset = $request->get('offset', 1);

        $lostItems = $this->lostItemService->getByDriver(
            driverId: auth('api')->id(),
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
     * Get pending lost items reported for driver's trips
     * GET /api/driver/lost-items/pending
     */
    public function pending(Request $request): JsonResponse
    {
        $limit = $request->get('limit', 10);
        $offset = $request->get('offset', 1);

        $lostItems = $this->lostItemService->getPendingByDriver(
            driverId: auth('api')->id(),
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
     * Get unread lost items from the last 24 hours
     * Unread = items where driver hasn't responded (driver_response is null)
     * GET /api/driver/lost-items/unread
     */
    public function unread(Request $request): JsonResponse
    {
        $limit = $request->get('limit', 10);
        $offset = $request->get('offset', 1);

        $lostItems = $this->lostItemService->getUnreadByDriver(
            driverId: auth('api')->id(),
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
     * GET /api/driver/lost-items/{id}
     */
    public function show(string $id): JsonResponse
    {
        $lostItem = $this->lostItemService->findOne(
            id: $id,
            relations: ['trip.coordinate', 'customer', 'statusLogs']
        );

        if (!$lostItem) {
            return response()->json(responseFormatter(LOST_ITEM_NOT_FOUND_404), 404);
        }

        // Verify belongs to current driver
        if ($lostItem->driver_id !== auth('api')->id()) {
            return response()->json(responseFormatter(ACCESS_DENIED_403), 403);
        }

        return response()->json(responseFormatter(
            constant: DEFAULT_200,
            content: new LostItemResource($lostItem)
        ));
    }

    /**
     * Update driver response for lost item
     * PATCH /api/driver/lost-items/{id}
     */
    public function update(string $id, LostItemUpdateRequest $request): JsonResponse
    {
        $lostItem = $this->lostItemService->findOne(id: $id);

        if (!$lostItem) {
            return response()->json(responseFormatter(LOST_ITEM_NOT_FOUND_404), 404);
        }

        // Verify belongs to current driver
        if ($lostItem->driver_id !== auth('api')->id()) {
            return response()->json(responseFormatter(ACCESS_DENIED_403), 403);
        }

        // Update driver response
        $lostItem = $this->lostItemService->updateDriverResponse(
            id: $id,
            response: $request->driver_response,
            notes: $request->driver_notes
        );

        if (!$lostItem) {
            return response()->json(responseFormatter(DEFAULT_400), 400);
        }

        // Notify customer about driver response
        $this->notifyCustomer($lostItem, $request->driver_response);

        $lostItem->load(['trip.coordinate', 'customer', 'statusLogs']);

        return response()->json(responseFormatter(
            constant: DEFAULT_UPDATE_200,
            content: new LostItemResource($lostItem)
        ));
    }

    /**
     * Update lost item status (for driver actions like marking as found/returned)
     * POST /api/driver/lost-items/{id}/status
     */
    public function updateStatus(string $id, Request $request): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:found,not_found,returned,closed',
        ]);

        $lostItem = $this->lostItemService->findOne(id: $id);

        if (!$lostItem) {
            return response()->json(responseFormatter(LOST_ITEM_NOT_FOUND_404), 404);
        }

        // Verify belongs to current driver
        if ($lostItem->driver_id !== auth('api')->id()) {
            return response()->json(responseFormatter(ACCESS_DENIED_403), 403);
        }

        $newStatus = $request->status;

        // Update the status using the service method which handles logging
        $lostItem = $this->lostItemService->updateStatus(
            id: $id,
            status: $newStatus,
            notes: 'Driver changed status to ' . $newStatus,
            userId: auth('api')->id()
        );

        if (!$lostItem) {
            return response()->json(responseFormatter(DEFAULT_400), 400);
        }

        // Load customer relationship before notifying
        $lostItem->load(['customer', 'trip.coordinate', 'statusLogs']);

        // Notify customer about status change
        $this->notifyCustomerStatusChange($lostItem, $newStatus);

        return response()->json(responseFormatter(
            constant: DEFAULT_UPDATE_200,
            content: new LostItemResource($lostItem)
        ));
    }

    /**
     * Notify customer about status change
     */
    protected function notifyCustomerStatusChange($lostItem, string $status): void
    {
        try {
            $titleMap = [
                'found' => 'Lost Item Found!',
                'not_found' => 'Item Not Found',
                'returned' => 'Item Returned',
                'closed' => 'Report Closed',
            ];

            $descriptionMap = [
                'found' => 'Great news! The driver has found your lost item. Please arrange pickup.',
                'not_found' => 'The driver has checked but could not find your lost item.',
                'returned' => 'Your lost item has been returned successfully.',
                'closed' => 'Your lost item report has been closed.',
            ];
            
            $title = $titleMap[$status] ?? 'Lost Item Update';
            $description = $descriptionMap[$status] ?? 'Your lost item status has been updated.';

            if ($lostItem->customer && $lostItem->customer->fcm_token) {
                sendDeviceNotification(
                    fcm_token: $lostItem->customer->fcm_token,
                    title: translate($title),
                    description: translate($description),
                    status: 1,
                    ride_request_id: $lostItem->trip_request_id,
                    type: 'lost_item',
                    action: 'lost_item_status_' . $status,
                    user_id: $lostItem->customer->id
                );
            }
        } catch (\Exception $e) {
            \Log::warning('Failed to send lost item status notification: ' . $e->getMessage());
        }
    }


    /**
     * Notify customer about driver's response
     */
    protected function notifyCustomer($lostItem, string $response): void
    {
        try {
            $notificationKey = $response === 'found' ? 'lost_item_found' : 'lost_item_not_found';
            $push = getNotification($notificationKey);

            if ($push && $lostItem->customer) {
                sendDeviceNotification(
                    fcm_token: $lostItem->customer->fcm_token,
                    title: translate($push['title'] ?? 'Lost Item Update'),
                    description: translate($push['description'] ?? 'The driver has responded to your lost item report.'),
                    status: $push['status'] ?? 1,
                    ride_request_id: $lostItem->trip_request_id,
                    type: 'lost_item',
                    action: 'lost_item_' . $response,
                    user_id: $lostItem->customer->id
                );
            }
        } catch (\Exception $e) {
            \Log::warning('Failed to send lost item notification to customer: ' . $e->getMessage());
        }
    }
}
