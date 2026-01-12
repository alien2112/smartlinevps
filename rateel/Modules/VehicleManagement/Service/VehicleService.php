<?php

namespace Modules\VehicleManagement\Service;

use App\Service\BaseService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\VehicleManagement\Repository\VehicleRepositoryInterface;
use Modules\VehicleManagement\Service\Interface\VehicleServiceInterface;

class VehicleService extends BaseService implements VehicleServiceInterface
{
    protected $vehicleRepository;

    public function __construct(VehicleRepositoryInterface $vehicleRepository)
    {
        parent::__construct($vehicleRepository);
        $this->vehicleRepository = $vehicleRepository;
    }


    public function index(array $criteria = [], array $relations = [], array $whereHasRelations = [], array $orderBy = [], int $limit = null, int $offset = null, array $withCountQuery = [], array $appends = [], array $groupBy = []): Collection|LengthAwarePaginator
    {
        $data = [];
        if (array_key_exists('status', $criteria) && $criteria['status'] !== 'all') {
            $data['is_active'] = $criteria['status'] == 'active' ? 1 : 0;
        }
        if (array_key_exists('vehicle_request_status', $criteria)) {
            $data['vehicle_request_status'] = $criteria['vehicle_request_status'];
        }
        if (array_key_exists('draft', $criteria) && $criteria['draft']) {
            $data[] = ['draft', '!=', null];
        }
        $searchData = [];
        if (array_key_exists('search', $criteria) && $criteria['search'] != '') {
            $searchData['fields'] = ['licence_plate_number', 'vin_number'];
            $searchData['relations'] = [
                'driver' => ['full_name', 'first_name', 'last_name', 'email', 'phone'],
            ];
            $searchData['value'] = $criteria['search'];
        }
        $whereInCriteria = [];
        $whereBetweenCriteria = [];
        $whereHasRelations = [];
        return $this->baseRepository->getBy(criteria: $data, searchCriteria: $searchData, whereInCriteria: $whereInCriteria, whereBetweenCriteria: $whereBetweenCriteria, whereHasRelations: $whereHasRelations, relations: $relations, orderBy: $orderBy, limit: $limit, offset: $offset, withCountQuery: $withCountQuery);
    }

    public function create(array $data): ?Model
    {
        $documents = [];

        try {
            // Handle car_front and car_back images
            if (isset($data['car_front'])) {
                $extension = $data['car_front']->getClientOriginalExtension();
                $uploadedFile = fileUploader('vehicle/document/', $extension, $data['car_front']);
                if ($uploadedFile) {
                    $documents[] = $uploadedFile;
                }
            }

            if (isset($data['car_back'])) {
                $extension = $data['car_back']->getClientOriginalExtension();
                $uploadedFile = fileUploader('vehicle/document/', $extension, $data['car_back']);
                if ($uploadedFile) {
                    $documents[] = $uploadedFile;
                }
            }

            // Handle car license image
            if (isset($data['car_license_image'])) {
                $extension = $data['car_license_image']->getClientOriginalExtension();
                $uploadedFile = fileUploader('vehicle/document/', $extension, $data['car_license_image']);
                if ($uploadedFile) {
                    $documents[] = $uploadedFile;
                }
            }

            // Handle other documents
            if (array_key_exists('other_documents', $data)) {
                foreach ($data['other_documents'] as $doc) {
                    $extension = $doc->getClientOriginalExtension();
                    $uploadedFile = fileUploader('vehicle/document/', $extension, $doc);
                    if ($uploadedFile) {
                        $documents[] = $uploadedFile;
                    }
                }
            }
        } catch (\Exception $e) {
            \Log::error('Vehicle document upload failed', [
                'error' => $e->getMessage(),
                'driver_id' => $data['driver_id'] ?? 'unknown',
            ]);
            // Continue without documents if upload fails
        }
        
        $storeData = [
            'brand_id' => $data['brand_id'],
            'model_id' => $data['model_id'],
            'category_id' => $data['category_id'],
            'licence_plate_number' => $data['licence_plate_number'],
            'licence_expire_date' => $data['licence_expire_date'],
            'vin_number' => $data['vin_number'] ?? null,
            'transmission' => $data['transmission'] ?? null,
            'parcel_weight_capacity' => $data['parcel_weight_capacity'] ?? null,
            'fuel_type' => $data['fuel_type'] ?? null,
            'ownership' => $data['ownership'],
            'driver_id' => $data['driver_id'],
            'vehicle_request_status' => $data['vehicle_request_status'] ?? PENDING,
            'is_primary' => $data['is_primary'] ?? false,
            'is_active' => $data['is_active'] ?? false,
            'has_pending_primary_request' => $data['has_pending_primary_request'] ?? false,
            'documents' => $documents,
        ];
        return $this->vehicleRepository->create($storeData);
    }

    public function updatedByAdmin(int|string $id, array $data = []): ?Model
    {
        $updateData = [
            'brand_id' => $data['brand_id'],
            'model_id' => $data['model_id'],
            'category_id' => $data['category_id'],
            'licence_plate_number' => $data['licence_plate_number'],
            'licence_expire_date' => $data['licence_expire_date'],
            'vin_number' => $data['vin_number'],
            'transmission' => $data['transmission'],
            'parcel_weight_capacity' => $data['parcel_weight_capacity'],
            'fuel_type' => $data['fuel_type'],
            'ownership' => $data['ownership'],
            'driver_id' => $data['driver_id'],
        ];

        if (array_key_exists('type', $data) && $data['type'] == 'update_and_approve') {
            $updateData['vehicle_request_status'] = APPROVED;
            $updateData['is_active'] = 1;
        }

        if (array_key_exists('type', $data) && $data['type'] == 'draft') {
            $updateData['draft'] = NULL;
        }

        $existingDocuments = array_key_exists('existing_documents', $data) ? $data['existing_documents'] : [];
        $deletedDocuments = array_key_exists('deleted_documents', $data) ? explode(',', $data['deleted_documents']) : [];

        // Remove deleted documents from the existing list
        $documents = array_diff($existingDocuments, $deletedDocuments);

        // Handle new uploads
        if ($data['other_documents'] ?? null) {
            foreach ($data['other_documents'] as $doc) {
                $extension = $doc->getClientOriginalExtension();
                $documents[] = fileUploader('vehicle/document/', $extension, $doc);
            }
        }
        $updateData['documents'] = $documents;
        return $this->vehicleRepository->update($id, $updateData);
    }

    public function export(array $criteria = [], array $relations = [], array $orderBy = [], int $limit = null, int $offset = null, array $withCountQuery = []): Collection|LengthAwarePaginator|\Illuminate\Support\Collection
    {
        return $this->index(criteria: $criteria, relations: $relations, orderBy: $orderBy)->map(function ($item) {
            return [
                'Id' => $item['id'],
                'Driver Name' => $item?->driver?->full_name ?? $item?->driver?->first_name . ' ' . $item?->driver?->last_name,
                'Type' => ucwords(str_replace('_', ' ', $item?->category?->type ?? 'N/A')),
                'Brand' => $item?->brand?->name ?? 'N/A',
                'Model' => $item?->model?->name ?? 'N/A',
                'License' => $item['licence_plate_number'],
                'Owner' => ucwords($item['ownership']),
                'Seat Capacity' => $item?->model?->seat_capacity ?? 'N/A',
                "Hatch Bag Capacity" => $item?->model?->hatch_bag_capacity ?? 'N/A',
                "Fuel" => ucwords($item['fuel_type']),
                "Mileage" => $item?->model?->engine ?? 'N/A',
                'Status' => $item['is_active'] == 1 ? "Active" : "Inactive",
            ];
        });
    }


    public function updatedByDriver(int|string $id, array $data): ?Model
    {
        // Find vehicle by ID (not driver_id)
        $vehicle = $this->vehicleRepository->findOne($id);

        if (!$vehicle) {
            return null;
        }

        // Store NEW values in draft for admin approval
        // Keep CURRENT values in the vehicle record until admin approves
        $draftData = [];
        $hasChanges = false;

        // Always store new values in draft - they will be applied only after admin approval
        if (isset($data['brand_id']) && $vehicle->brand_id != $data['brand_id']) {
            $draftData['brand_id'] = $data['brand_id'];
            $draftData['model_id'] = $data['model_id'] ?? $vehicle->model_id;
            $hasChanges = true;
        }

        if (isset($data['category_id']) && $vehicle->category_id != $data['category_id']) {
            $draftData['category_id'] = $data['category_id'];
            $hasChanges = true;
        }

        if (isset($data['licence_plate_number']) && $vehicle->licence_plate_number != $data['licence_plate_number']) {
            $draftData['licence_plate_number'] = $data['licence_plate_number'];
            $hasChanges = true;
        }

        if (isset($data['licence_expire_date']) && $vehicle->licence_expire_date != $data['licence_expire_date']) {
            $draftData['licence_expire_date'] = $data['licence_expire_date'];
            $hasChanges = true;
        }

        if (isset($data['vin_number']) && $vehicle->vin_number != $data['vin_number']) {
            $draftData['vin_number'] = $data['vin_number'];
            $hasChanges = true;
        }

        if (isset($data['transmission']) && $vehicle->transmission != $data['transmission']) {
            $draftData['transmission'] = $data['transmission'];
            $hasChanges = true;
        }

        if (isset($data['parcel_weight_capacity']) && $vehicle->parcel_weight_capacity != $data['parcel_weight_capacity']) {
            $draftData['parcel_weight_capacity'] = $data['parcel_weight_capacity'];
            $hasChanges = true;
        }

        if (isset($data['fuel_type']) && $vehicle->fuel_type != $data['fuel_type']) {
            $draftData['fuel_type'] = $data['fuel_type'];
            $hasChanges = true;
        }

        if (isset($data['ownership']) && $vehicle->ownership != $data['ownership']) {
            $draftData['ownership'] = $data['ownership'];
            $hasChanges = true;
        }

        // Handle image uploads - update documents immediately (not in draft)
        $updateDocuments = false;
        $existingDocuments = $vehicle->documents ?? [];
        $newDocuments = $existingDocuments;

        try {
            if (isset($data['car_front'])) {
                $extension = $data['car_front']->getClientOriginalExtension();
                $newFrontImage = fileUploader('vehicle/document/', $extension, $data['car_front'], $existingDocuments[0] ?? null);
                if ($newFrontImage) {
                    $newDocuments[0] = $newFrontImage;
                    $updateDocuments = true;
                    $hasChanges = true;
                }
            }

            if (isset($data['car_back'])) {
                $extension = $data['car_back']->getClientOriginalExtension();
                $newBackImage = fileUploader('vehicle/document/', $extension, $data['car_back'], $existingDocuments[1] ?? null);
                if ($newBackImage) {
                    $newDocuments[1] = $newBackImage;
                    $updateDocuments = true;
                    $hasChanges = true;
                }
            }

            if (isset($data['car_license_image'])) {
                $extension = $data['car_license_image']->getClientOriginalExtension();
                $newLicenseImage = fileUploader('vehicle/document/', $extension, $data['car_license_image'], $existingDocuments[2] ?? null);
                if ($newLicenseImage) {
                    $newDocuments[2] = $newLicenseImage;
                    $updateDocuments = true;
                    $hasChanges = true;
                }
            }
        } catch (\Exception $e) {
            \Log::error('Vehicle document update failed', [
                'error' => $e->getMessage(),
                'vehicle_id' => $vehicle->id,
                'driver_id' => $vehicle->driver_id,
            ]);
            // Continue with other changes if document upload fails
        }

        // If there are changes, store them in draft and set status to PENDING
        if ($hasChanges) {
            $updateData = [
                'vehicle_request_status' => PENDING
            ];

            // Only update draft if there are non-image changes
            if (!empty($draftData)) {
                $updateData['draft'] = $draftData;
            }

            // Update documents immediately if images were uploaded
            if ($updateDocuments) {
                $updateData['documents'] = $newDocuments;
            }

            return $this->vehicleRepository->update($vehicle->id, $updateData);
        }

        return $vehicle;
    }

    public function approveVehicleUpdate(int|string $id): ?Model
    {
        $vehicle = $this->vehicleRepository->findOne($id);

        if (!$vehicle || !$vehicle->draft) {
            return $vehicle;
        }

        // Apply draft changes to the vehicle
        $draftData = $vehicle->draft;
        $updateData = [];

        // Apply each field from draft to the actual vehicle
        if (isset($draftData['brand_id'])) {
            $updateData['brand_id'] = $draftData['brand_id'];
        }

        if (isset($draftData['model_id'])) {
            $updateData['model_id'] = $draftData['model_id'];
        }

        if (isset($draftData['category_id'])) {
            $updateData['category_id'] = $draftData['category_id'];
        }

        if (isset($draftData['licence_plate_number'])) {
            $updateData['licence_plate_number'] = $draftData['licence_plate_number'];
        }

        if (isset($draftData['licence_expire_date'])) {
            $updateData['licence_expire_date'] = $draftData['licence_expire_date'];
        }

        if (isset($draftData['vin_number'])) {
            $updateData['vin_number'] = $draftData['vin_number'];
        }

        if (isset($draftData['transmission'])) {
            $updateData['transmission'] = $draftData['transmission'];
        }

        if (isset($draftData['parcel_weight_capacity'])) {
            $updateData['parcel_weight_capacity'] = $draftData['parcel_weight_capacity'];
        }

        if (isset($draftData['fuel_type'])) {
            $updateData['fuel_type'] = $draftData['fuel_type'];
        }

        if (isset($draftData['ownership'])) {
            $updateData['ownership'] = $draftData['ownership'];
        }

        // Clear draft and ensure status remains APPROVED
        $updateData['draft'] = null;
        $updateData['vehicle_request_status'] = APPROVED;

        return $this->vehicleRepository->update($vehicle->id, $updateData);
    }

    public function deniedVehicleUpdateByAdmin(int|string $id, array $data = []): ?Model
    {
        // When denying update, just clear the draft and keep vehicle status as APPROVED
        // The vehicle itself is still approved, just the pending update is rejected
        $updateData = [
            'draft' => null,
            'vehicle_request_status' => APPROVED
        ];

        return $this->vehicleRepository->update($id, $updateData);
    }

    public function exportUpdateVehicle(array $criteria = [], array $relations = [], array $orderBy = [], int $limit = null, int $offset = null, array $withCountQuery = []): Collection|LengthAwarePaginator|\Illuminate\Support\Collection
    {
        return $this->index(criteria: $criteria, relations: $relations, orderBy: $orderBy)->map(function ($item) {
            $afterEdit = [];

            // Check for each editable field and add it to the array if it exists in the draft
            if (array_key_exists('category_id', $item?->draft)) {
                $afterEdit['category_name'] = $item?->category?->name;
            }
            if (array_key_exists('brand_id', $item?->draft)) {
                $afterEdit['brand_name'] = $item?->brand?->name;
            }
            if (array_key_exists('model_id', $item?->draft)) {
                $afterEdit['model_name'] = $item?->model?->name;
            }
            if (array_key_exists('licence_plate_number', $item?->draft)) {
                $afterEdit['licence_plate_number'] = $item?->licence_plate_number;
            }
            if (array_key_exists('licence_expire_date', $item?->draft)) {
                $afterEdit['licence_expire_date'] =  date('Y-m-d', strtotime($item?->licence_expire_date));
            }

            return [
                'Id' => $item['id'],
                'Driver Name' => $item?->driver?->full_name ?? $item?->driver?->first_name . ' ' . $item?->driver?->last_name,
                'Date & Time' => date('d/m/Y', strtotime($item->updated_at)) . ' ' . date('h:i A', strtotime($item->updated_at)),
                'Before Edit' => is_array($item->draft) ? json_encode($item->draft) : $item->draft,
                'After Edit' => json_encode($afterEdit),
            ];
        });
    }

    public function approvePrimaryVehicleChange(int|string $id): ?Model
    {
        $vehicle = $this->vehicleRepository->findOne($id);

        if (!$vehicle || !$vehicle->has_pending_primary_request) {
            return $vehicle;
        }

        // Unset current primary vehicle for this driver
        $currentPrimary = $this->vehicleRepository->getBy(
            criteria: ['driver_id' => $vehicle->driver_id, 'is_primary' => true]
        )->first();

        if ($currentPrimary) {
            $this->vehicleRepository->update($currentPrimary->id, ['is_primary' => false]);
        }

        // Set this vehicle as primary and clear the pending request flag
        return $this->vehicleRepository->update($vehicle->id, [
            'is_primary' => true,
            'has_pending_primary_request' => false
        ]);
    }

    public function denyPrimaryVehicleChange(int|string $id): ?Model
    {
        $vehicle = $this->vehicleRepository->findOne($id);

        if (!$vehicle || !$vehicle->has_pending_primary_request) {
            return $vehicle;
        }

        // Just clear the pending request flag
        return $this->vehicleRepository->update($vehicle->id, [
            'has_pending_primary_request' => false
        ]);
    }
    }
