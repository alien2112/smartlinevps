<?php

namespace Modules\TripManagement\Service\Interface;

use App\Service\BaseServiceInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface LostItemServiceInterface extends BaseServiceInterface
{
    /**
     * Create a lost item report
     */
    public function create(array $data): ?Model;

    /**
     * Update lost item status with logging
     */
    public function updateStatus(string $id, string $status, ?string $notes = null, ?string $userId = null): ?Model;

    /**
     * Get lost items by customer ID
     */
    public function getByCustomer(string $customerId, int $limit = 10, int $offset = 1): Collection|LengthAwarePaginator;

    /**
     * Get lost items by driver ID
     */
    public function getByDriver(string $driverId, int $limit = 10, int $offset = 1): Collection|LengthAwarePaginator;

    /**
     * Get pending lost items for a driver (items that still need driver attention)
     */
    public function getPendingByDriver(string $driverId, int $limit = 10, int $offset = 1): Collection|LengthAwarePaginator;

    /**
     * Update driver response
     */
    public function updateDriverResponse(string $id, string $response, ?string $notes = null): ?Model;

    /**
     * Export lost items for admin
     */
    public function export(array $criteria = [], array $relations = []): Collection;

    /**
     * Check if duplicate report exists
     */
    public function hasDuplicateReport(string $tripRequestId, string $category): bool;
}
