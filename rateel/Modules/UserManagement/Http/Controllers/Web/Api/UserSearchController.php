<?php

namespace Modules\UserManagement\Http\Controllers\Web\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\UserManagement\Service\Interface\CustomerServiceInterface;
use Modules\UserManagement\Service\Interface\DriverServiceInterface;

/**
 * API endpoint for AJAX user search in admin filters
 * Replaces loading all users in dropdowns with server-side search
 */
class UserSearchController extends Controller
{
    protected CustomerServiceInterface $customerService;
    protected DriverServiceInterface $driverService;

    public function __construct(
        CustomerServiceInterface $customerService,
        DriverServiceInterface $driverService
    ) {
        $this->customerService = $customerService;
        $this->driverService = $driverService;
    }

    /**
     * Search customers for select2/autocomplete dropdowns
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function searchCustomers(Request $request): JsonResponse
    {
        $search = $request->get('search', '');
        $limit = min($request->get('limit', 20), 50); // Cap at 50

        $criteria = ['user_type' => CUSTOMER];
        if (!empty($search)) {
            $searchCriteria = [
                'fields' => ['full_name', 'first_name', 'last_name', 'email', 'phone'],
                'value' => $search,
            ];
        } else {
            $searchCriteria = [];
        }

        $customers = $this->customerService->getBaseRepository()->getBy(
            criteria: $criteria,
            searchCriteria: $searchCriteria,
            orderBy: ['first_name' => 'asc'],
            limit: $limit,
            withTrashed: true
        );

        return response()->json([
            'results' => $customers->map(function ($customer) {
                return [
                    'id' => $customer->id,
                    'text' => $customer->full_name ?? ($customer->first_name . ' ' . $customer->last_name),
                    'phone' => $customer->phone,
                    'email' => $customer->email,
                ];
            }),
        ]);
    }

    /**
     * Search drivers for select2/autocomplete dropdowns
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function searchDrivers(Request $request): JsonResponse
    {
        $search = $request->get('search', '');
        $limit = min($request->get('limit', 20), 50); // Cap at 50

        $criteria = ['user_type' => DRIVER];
        if (!empty($search)) {
            $searchCriteria = [
                'fields' => ['full_name', 'first_name', 'last_name', 'email', 'phone'],
                'value' => $search,
            ];
        } else {
            $searchCriteria = [];
        }

        $drivers = $this->driverService->getBaseRepository()->getBy(
            criteria: $criteria,
            searchCriteria: $searchCriteria,
            orderBy: ['first_name' => 'asc'],
            limit: $limit,
            withTrashed: true
        );

        return response()->json([
            'results' => $drivers->map(function ($driver) {
                return [
                    'id' => $driver->id,
                    'text' => $driver->full_name ?? ($driver->first_name . ' ' . $driver->last_name),
                    'phone' => $driver->phone,
                    'email' => $driver->email,
                ];
            }),
        ]);
    }

    /**
     * Get a specific customer by ID (for initial select2 value)
     */
    public function getCustomer(Request $request): JsonResponse
    {
        $id = $request->get('id');
        if (!$id) {
            return response()->json(['error' => 'ID required'], 400);
        }

        $customer = $this->customerService->findOne(id: $id);
        if (!$customer) {
            return response()->json(['error' => 'Not found'], 404);
        }

        return response()->json([
            'id' => $customer->id,
            'text' => $customer->full_name ?? ($customer->first_name . ' ' . $customer->last_name),
        ]);
    }

    /**
     * Get a specific driver by ID (for initial select2 value)
     */
    public function getDriver(Request $request): JsonResponse
    {
        $id = $request->get('id');
        if (!$id) {
            return response()->json(['error' => 'ID required'], 400);
        }

        $driver = $this->driverService->findOne(id: $id);
        if (!$driver) {
            return response()->json(['error' => 'Not found'], 404);
        }

        return response()->json([
            'id' => $driver->id,
            'text' => $driver->full_name ?? ($driver->first_name . ' ' . $driver->last_name),
        ]);
    }
}
