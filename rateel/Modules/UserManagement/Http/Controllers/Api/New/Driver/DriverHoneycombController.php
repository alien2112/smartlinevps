<?php

namespace Modules\UserManagement\Http\Controllers\Api\New\Driver;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

/**
 * DriverHoneycombController
 *
 * Manages driver's honeycomb dispatch preference.
 * Allows drivers to enable/disable honeycomb zone-based dispatch for their account.
 */
class DriverHoneycombController extends Controller
{
    /**
     * Get driver's honeycomb dispatch preference
     * GET /api/driver/honeycomb/status
     */
    public function status(): JsonResponse
    {
        $driver = auth('api')->user();
        $driverDetails = $driver->driverDetails;

        if (!$driverDetails) {
            return response()->json(responseFormatter(
                constant: DEFAULT_404,
                content: null
            ), 404);
        }

        $data = $driverDetails->getHoneycombInfo();

        return response()->json(responseFormatter(
            constant: DEFAULT_200,
            content: $data
        ));
    }

    /**
     * Toggle honeycomb dispatch on/off
     * PATCH /api/driver/honeycomb/toggle
     */
    public function toggle(): JsonResponse
    {
        $driver = auth('api')->user();
        $driverDetails = $driver->driverDetails;

        if (!$driverDetails) {
            return response()->json(responseFormatter(
                constant: DEFAULT_404,
                content: null
            ), 404);
        }

        $success = $driverDetails->toggleHoneycomb();

        if (!$success) {
            return response()->json(responseFormatter(
                constant: DEFAULT_UPDATE_FAIL_200,
                content: null
            ), 500);
        }

        \Log::info('Driver toggled honeycomb preference', [
            'driver_id' => $driver->id,
            'honeycomb_enabled' => $driverDetails->honeycomb_enabled,
        ]);

        return response()->json(responseFormatter(
            constant: DEFAULT_STATUS_UPDATE_200,
            content: $driverDetails->getHoneycombInfo(),
            message: 'Honeycomb dispatch ' . ($driverDetails->honeycomb_enabled ? 'enabled' : 'disabled')
        ));
    }

    /**
     * Set honeycomb dispatch status explicitly
     * PATCH /api/driver/honeycomb/set
     */
    public function set(Request $request): JsonResponse
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

        $success = $driverDetails->setHoneycomb($request->enabled);

        if (!$success) {
            return response()->json(responseFormatter(
                constant: DEFAULT_UPDATE_FAIL_200,
                content: null
            ), 500);
        }

        \Log::info('Driver set honeycomb preference', [
            'driver_id' => $driver->id,
            'honeycomb_enabled' => $request->enabled,
        ]);

        return response()->json(responseFormatter(
            constant: DEFAULT_STATUS_UPDATE_200,
            content: $driverDetails->getHoneycombInfo(),
            message: 'Honeycomb dispatch ' . ($request->enabled ? 'enabled' : 'disabled')
        ));
    }
}
