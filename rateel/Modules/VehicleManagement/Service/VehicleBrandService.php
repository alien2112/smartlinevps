<?php

namespace Modules\VehicleManagement\Service;

use App\Service\BaseService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\VehicleManagement\Repository\VehicleBrandRepositoryInterface;
use Modules\VehicleManagement\Service\Interface\VehicleBrandServiceInterface;

class VehicleBrandService extends BaseService implements VehicleBrandServiceInterface
{
    protected $vehicleBrandRepository;

    public function __construct(VehicleBrandRepositoryInterface $vehicleBrandRepository)
    {
        parent::__construct($vehicleBrandRepository);
        $this->vehicleBrandRepository = $vehicleBrandRepository;
    }

    public function create(array $data): ?Model
    {
        $storeData = [
            'name' => $data['brand_name'],
            'description' => $data['short_desc'],
            'image' => fileUploader('vehicle/brand/', 'png', $data['brand_logo']),
        ];
        return $this->vehicleBrandRepository->create($storeData);
    }

    public function update(int|string $id, array $data = []): ?Model
    {
        $model = $this->findOne(id: $id);
        $updateData = [
            'name' => $data['brand_name'],
            'description' => $data['short_desc'],
        ];
        if (array_key_exists('brand_logo', $data)) {
            $updateData = array_merge($updateData, [
                'image' => fileUploader('vehicle/brand/', 'png', $data['brand_logo'], $model?->image)
            ]);
        }
        return $this->vehicleBrandRepository->update($id, $updateData);
    }

    /**
     * Export vehicle brands
     * Updated: 2026-01-14 - Fixed N+1 query by using withCount instead of loading relation
     */
    public function export(array $criteria = [], array $relations = [], array $orderBy = [], int $limit = null, int $offset = null, array $withCountQuery = []): Collection|LengthAwarePaginator|\Illuminate\Support\Collection
    {
        // Updated 2026-01-14: Use withCount to prevent N+1 queries
        return $this->index(
            criteria: $criteria,
            relations: $relations,
            orderBy: $orderBy,
            withCountQuery: ['vehicles'] // Add vehicle count to query
        )->map(function ($item) {
            return [
                'Id' => $item['id'],
                'Brand Name' => $item['name'],
                'Description' => $item['description'],
                // Updated 2026-01-14: Use pre-loaded count instead of lazy loading relation
                // OLD CODE: 'Total Vehicles' => $item->vehicles->count(), // Commented 2026-01-14 - N+1 query
                'Total Vehicles' => $item->vehicles_count ?? 0,
                'Status' => $item['is_active'] ? 'Active' : 'Inactive',
            ];
        });
    }

}
