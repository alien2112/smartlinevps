<?php

namespace Modules\TripManagement\Service;

use App\Service\BaseService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Modules\TripManagement\Entities\LostItem;
use Modules\TripManagement\Repository\LostItemRepositoryInterface;
use Modules\TripManagement\Repository\LostItemStatusLogRepositoryInterface;
use Modules\TripManagement\Service\Interface\LostItemServiceInterface;
use Modules\TripManagement\Service\Interface\TripRequestServiceInterface;

class LostItemService extends BaseService implements LostItemServiceInterface
{
    protected $statusLogRepository;
    protected $tripRequestService;

    public function __construct(
        LostItemRepositoryInterface $lostItemRepository,
        LostItemStatusLogRepositoryInterface $statusLogRepository,
        TripRequestServiceInterface $tripRequestService
    ) {
        parent::__construct($lostItemRepository);
        $this->statusLogRepository = $statusLogRepository;
        $this->tripRequestService = $tripRequestService;
    }

    /**
     * Create a lost item report
     */
    public function create(array $data): ?Model
    {
        // Get trip details
        $trip = $this->tripRequestService->findOne(id: $data['trip_request_id'], relations: ['driver', 'customer']);

        if (!$trip) {
            return null;
        }

        // Check for duplicate
        if ($this->hasDuplicateReport($data['trip_request_id'], $data['category'])) {
            return null;
        }

        $attributes = [
            'trip_request_id' => $data['trip_request_id'],
            'customer_id' => $trip->customer_id,
            'driver_id' => $trip->driver_id,
            'category' => $data['category'],
            'description' => $data['description'],
            'contact_preference' => $data['contact_preference'] ?? 'in_app',
            'item_lost_at' => $data['item_lost_at'] ?? $trip->time?->completed_at ?? now(),
            'status' => LostItem::STATUS_PENDING,
        ];

        // Handle image upload
        if (isset($data['image']) && $data['image']) {
            $imageName = time() . '_' . uniqid() . '.' . $data['image']->getClientOriginalExtension();
            $data['image']->storeAs('public/lost-items', $imageName);
            $attributes['image_url'] = $imageName;
        }

        DB::beginTransaction();
        try {
            $lostItem = $this->baseRepository->create(data: $attributes);

            // Create initial status log
            $this->statusLogRepository->create(data: [
                'lost_item_id' => $lostItem->id,
                'changed_by' => auth('api')->user()?->id ?? $trip->customer_id,
                'from_status' => '',
                'to_status' => LostItem::STATUS_PENDING,
                'notes' => 'Report created',
            ]);

            // Publish event for realtime updates
            $this->publishLostItemEvent('lost_item:created', $lostItem);

            DB::commit();
            return $lostItem;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Update lost item status with logging
     */
    public function updateStatus(string $id, string $status, ?string $notes = null, ?string $userId = null): ?Model
    {
        $lostItem = $this->baseRepository->findOne(id: $id);

        if (!$lostItem) {
            return null;
        }

        $fromStatus = $lostItem->status;

        DB::beginTransaction();
        try {
            $lostItem = $this->baseRepository->update(id: $id, data: [
                'status' => $status,
                'admin_notes' => $notes ?? $lostItem->admin_notes,
            ]);

            // Log status change
            $this->statusLogRepository->create(data: [
                'lost_item_id' => $id,
                'changed_by' => $userId ?? auth('api')->user()?->id ?? auth()->user()?->id,
                'from_status' => $fromStatus,
                'to_status' => $status,
                'notes' => $notes,
            ]);

            // Publish event for realtime updates
            $this->publishLostItemEvent('lost_item:updated', $lostItem);

            DB::commit();
            return $lostItem;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Get lost items by customer ID
     */
    public function getByCustomer(string $customerId, int $limit = 10, int $offset = 1): Collection|LengthAwarePaginator
    {
        return $this->baseRepository->getBy(
            criteria: ['customer_id' => $customerId],
            relations: ['trip', 'driver', 'statusLogs'],
            orderBy: ['created_at' => 'desc'],
            limit: $limit,
            offset: $offset
        );
    }

    /**
     * Get lost items by driver ID
     */
    public function getByDriver(string $driverId, int $limit = 10, int $offset = 1): Collection|LengthAwarePaginator
    {
        return $this->baseRepository->getBy(
            criteria: ['driver_id' => $driverId],
            relations: ['trip', 'customer'],
            orderBy: ['created_at' => 'desc'],
            limit: $limit,
            offset: $offset
        );
    }

    /**
     * Update driver response
     */
    public function updateDriverResponse(string $id, string $response, ?string $notes = null): ?Model
    {
        $lostItem = $this->baseRepository->findOne(id: $id);

        if (!$lostItem) {
            return null;
        }

        // Reflect driver decision in status so the customer can see progress
        $newStatus = $response === 'found' ? LostItem::STATUS_FOUND : LostItem::STATUS_DRIVER_CONTACTED;
        $fromStatus = $lostItem->status;

        DB::beginTransaction();
        try {
            $lostItem = $this->baseRepository->update(id: $id, data: [
                'driver_response' => $response,
                'driver_notes' => $notes,
                'status' => $newStatus,
            ]);

            // Log status change if status changed
            if ($fromStatus !== $newStatus) {
                $this->statusLogRepository->create(data: [
                    'lost_item_id' => $id,
                    'changed_by' => auth('api')->user()?->id,
                    'from_status' => $fromStatus,
                    'to_status' => $newStatus,
                    'notes' => 'Driver marked item as ' . $response,
                ]);
            }

            // Publish event for realtime updates
            $this->publishLostItemEvent('lost_item:updated', $lostItem);

            DB::commit();
            return $lostItem;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Export lost items for admin
     */
    public function export(array $criteria = [], array $relations = []): Collection
    {
        $items = $this->baseRepository->getBy(
            criteria: $criteria,
            relations: array_merge(['trip', 'customer', 'driver'], $relations),
            orderBy: ['created_at' => 'desc'],
            limit: 10000,
            offset: 1
        );

        return $items->map(function ($item) {
            return [
                'Report ID' => $item->id,
                'Trip Reference' => $item->trip?->ref_id,
                'Date' => $item->created_at->format('d F Y, h:i A'),
                'Category' => ucfirst($item->category),
                'Description' => $item->description,
                'Customer' => $item->customer?->first_name . ' ' . $item->customer?->last_name,
                'Driver' => $item->driver?->first_name . ' ' . $item->driver?->last_name,
                'Status' => ucfirst(str_replace('_', ' ', $item->status)),
                'Driver Response' => $item->driver_response ? ucfirst(str_replace('_', ' ', $item->driver_response)) : 'Pending',
            ];
        });
    }

    /**
     * Check if duplicate report exists
     */
    public function hasDuplicateReport(string $tripRequestId, string $category): bool
    {
        $existing = $this->baseRepository->findOneBy(criteria: [
            'trip_request_id' => $tripRequestId,
            'category' => $category,
        ]);

        return $existing !== null;
    }

    /**
     * Publish lost item event to Redis for realtime updates
     * Uses raw Redis connection to avoid Laravel's default prefix
     */
    protected function publishLostItemEvent(string $event, Model $lostItem): void
    {
        try {
            $payload = json_encode([
                'id' => $lostItem->id,
                'trip_request_id' => $lostItem->trip_request_id,
                'customer_id' => $lostItem->customer_id,
                'driver_id' => $lostItem->driver_id,
                'category' => $lostItem->category,
                'status' => $lostItem->status,
                'driver_response' => $lostItem->driver_response,
                'created_at' => $lostItem->created_at->toIso8601String(),
            ]);

            // Use 'pubsub' connection which has no prefix
            // This ensures Node.js receives events on expected channel names
            Redis::connection('pubsub')->publish($event, $payload);
            
            \Log::info('Published lost item event', ['event' => $event, 'lost_item_id' => $lostItem->id]);
        } catch (\Exception $e) {
            // Log but don't fail the main operation
            \Log::warning('Failed to publish lost item event: ' . $e->getMessage());
        }
    }
}
