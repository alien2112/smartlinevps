<?php

namespace Modules\UserManagement\Http\Controllers\Api\New\Driver;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

/**
 * DriverDestinationPreferenceController
 *
 * Manages driver destination preferences for trip filtering.
 * Drivers can set up to 3 destinations and receive only trips heading that way.
 */
class DriverDestinationPreferenceController extends Controller
{
    /**
     * Get driver's destination preferences
     * GET /api/driver/destination-preferences
     */
    public function index(): JsonResponse
    {
        $driver = auth('api')->user();
        $driverDetails = $driver->driverDetails;

        if (!$driverDetails) {
            return response()->json(responseFormatter(
                constant: DEFAULT_404,
                content: null
            ), 404);
        }

        $data = $driverDetails->getDestinationPreferencesInfo();

        return response()->json(responseFormatter(
            constant: DEFAULT_200,
            content: $data
        ));
    }

    /**
     * Add new destination preference
     * POST /api/driver/destination-preferences
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'latitude' => 'required|numeric|min:-90|max:90',
            'longitude' => 'required|numeric|min:-180|max:180',
            'address' => 'nullable|string|max:500',
            'radius_km' => 'nullable|numeric|min:1|max:15',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(
                constant: DEFAULT_400,
                errors: formatValidationErrors($validator->errors())
            ), 400);
        }

        $driver = auth('api')->user();
        $driverDetails = $driver->driverDetails;

        if (!$driverDetails) {
            return response()->json(responseFormatter(
                constant: DEFAULT_404,
                content: null
            ), 404);
        }

        // Check if max 3 destinations already set
        $currentCount = count($driverDetails->destination_preferences ?? []);
        if ($currentCount >= 3) {
            return response()->json(responseFormatter(
                constant: DEFAULT_400,
                content: null,
                message: 'Maximum 3 destination preferences allowed'
            ), 400);
        }

        $success = $driverDetails->setDestinationPreference([
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'address' => $request->address,
            'radius_km' => $request->radius_km,
        ]);

        if (!$success) {
            return response()->json(responseFormatter(
                constant: DEFAULT_STORE_FAIL_200,
                content: null
            ), 500);
        }

        return response()->json(responseFormatter(
            constant: DEFAULT_STORE_200,
            content: $driverDetails->getDestinationPreferencesInfo()
        ));
    }

    /**
     * Update existing destination preference
     * PUT /api/driver/destination-preferences/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'latitude' => 'required|numeric|min:-90|max:90',
            'longitude' => 'required|numeric|min:-180|max:180',
            'address' => 'nullable|string|max:500',
            'radius_km' => 'nullable|numeric|min:1|max:15',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(
                constant: DEFAULT_400,
                errors: formatValidationErrors($validator->errors())
            ), 400);
        }

        $driver = auth('api')->user();
        $driverDetails = $driver->driverDetails;

        if (!$driverDetails) {
            return response()->json(responseFormatter(
                constant: DEFAULT_404,
                content: null
            ), 404);
        }

        // Validate ID exists
        $preferences = $driverDetails->destination_preferences ?? [];
        $exists = collect($preferences)->contains(fn($pref) => $pref['id'] === $id);

        if (!$exists) {
            return response()->json(responseFormatter(
                constant: DEFAULT_404,
                message: 'Destination preference not found'
            ), 404);
        }

        $success = $driverDetails->setDestinationPreference([
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'address' => $request->address,
            'radius_km' => $request->radius_km,
        ], $id);

        if (!$success) {
            return response()->json(responseFormatter(
                constant: DEFAULT_UPDATE_FAIL_200,
                content: null
            ), 500);
        }

        return response()->json(responseFormatter(
            constant: DEFAULT_UPDATE_200,
            content: $driverDetails->getDestinationPreferencesInfo()
        ));
    }

    /**
     * Delete destination preference
     * DELETE /api/driver/destination-preferences/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $driver = auth('api')->user();
        $driverDetails = $driver->driverDetails;

        if (!$driverDetails) {
            return response()->json(responseFormatter(
                constant: DEFAULT_404,
                content: null
            ), 404);
        }

        // Validate ID exists
        $preferences = $driverDetails->destination_preferences ?? [];
        $exists = collect($preferences)->contains(fn($pref) => $pref['id'] === $id);

        if (!$exists) {
            return response()->json(responseFormatter(
                constant: DEFAULT_404,
                message: 'Destination preference not found'
            ), 404);
        }

        $success = $driverDetails->removeDestinationPreference($id);

        if (!$success) {
            return response()->json(responseFormatter(
                constant: DEFAULT_DELETE_FAIL_200,
                content: null
            ), 500);
        }

        return response()->json(responseFormatter(
            constant: DEFAULT_DELETE_200,
            content: $driverDetails->getDestinationPreferencesInfo()
        ));
    }

    /**
     * Toggle destination filter on/off
     * PATCH /api/driver/destination-preferences/toggle-filter
     */
    public function toggleFilter(): JsonResponse
    {
        $driver = auth('api')->user();
        $driverDetails = $driver->driverDetails;

        if (!$driverDetails) {
            return response()->json(responseFormatter(
                constant: DEFAULT_404,
                content: null
            ), 404);
        }

        $success = $driverDetails->toggleDestinationFilter();

        if (!$success) {
            return response()->json(responseFormatter(
                constant: DEFAULT_UPDATE_FAIL_200,
                content: null
            ), 500);
        }

        return response()->json(responseFormatter(
            constant: DEFAULT_STATUS_UPDATE_200,
            content: $driverDetails->getDestinationPreferencesInfo(),
            message: 'Destination filter ' . ($driverDetails->destination_filter_enabled ? 'enabled' : 'disabled')
        ));
    }

    /**
     * Set destination filter status explicitly
     * PATCH /api/driver/destination-preferences/set-filter
     */
    public function setFilter(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'enabled' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(
                constant: DEFAULT_400,
                errors: formatValidationErrors($validator->errors())
            ), 400);
        }

        $driver = auth('api')->user();
        $driverDetails = $driver->driverDetails;

        if (!$driverDetails) {
            return response()->json(responseFormatter(
                constant: DEFAULT_404,
                content: null
            ), 404);
        }

        $success = $driverDetails->setDestinationFilter($request->enabled);

        if (!$success) {
            return response()->json(responseFormatter(
                constant: DEFAULT_UPDATE_FAIL_200,
                content: null
            ), 500);
        }

        return response()->json(responseFormatter(
            constant: DEFAULT_STATUS_UPDATE_200,
            content: $driverDetails->getDestinationPreferencesInfo(),
            message: 'Destination filter ' . ($request->enabled ? 'enabled' : 'disabled')
        ));
    }

    /**
     * Update default destination radius
     * PATCH /api/driver/destination-preferences/set-radius
     */
    public function setRadius(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'radius_km' => 'required|numeric|min:1|max:15',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(
                constant: DEFAULT_400,
                errors: formatValidationErrors($validator->errors())
            ), 400);
        }

        $driver = auth('api')->user();
        $driverDetails = $driver->driverDetails;

        if (!$driverDetails) {
            return response()->json(responseFormatter(
                constant: DEFAULT_404,
                content: null
            ), 404);
        }

        $success = $driverDetails->setDestinationRadius($request->radius_km);

        if (!$success) {
            return response()->json(responseFormatter(
                constant: DEFAULT_UPDATE_FAIL_200,
                content: null,
                message: 'Invalid radius. Must be between 1 and 15 km'
            ), 400);
        }

        return response()->json(responseFormatter(
            constant: DEFAULT_UPDATE_200,
            content: $driverDetails->getDestinationPreferencesInfo(),
            message: 'Default radius updated to ' . $request->radius_km . ' km'
        ));
    }
}
