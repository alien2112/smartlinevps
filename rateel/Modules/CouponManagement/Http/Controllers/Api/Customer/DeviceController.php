<?php

declare(strict_types=1);

namespace Modules\CouponManagement\Http\Controllers\Api\Customer;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Modules\CouponManagement\Entities\UserDevice;
use Modules\CouponManagement\Http\Requests\RegisterDeviceRequest;

class DeviceController extends Controller
{
    /**
     * Register FCM device token
     *
     * POST /api/v1/devices/register
     *
     * Request:
     * {
     *   "fcm_token": "dGVzdC10b2tlbi4uLg==",
     *   "platform": "android",
     *   "device_id": "unique-device-id",
     *   "device_model": "Samsung Galaxy S21",
     *   "app_version": "1.5.0"
     * }
     *
     * Response:
     * {
     *   "response_code": "default_200",
     *   "message": "Device registered successfully",
     *   "content": {
     *     "device_id": "device-uuid"
     *   }
     * }
     */
    public function register(RegisterDeviceRequest $request): JsonResponse
    {
        $user = auth('api')->user();
        $fcmToken = $request->input('fcm_token');

        Log::info('DeviceController: Registering device', [
            'user_id' => $user->id,
            'platform' => $request->input('platform'),
        ]);

        // Check if token already exists
        $existingDevice = UserDevice::where('fcm_token', $fcmToken)->first();

        if ($existingDevice) {
            // If token belongs to another user, transfer it
            if ($existingDevice->user_id !== $user->id) {
                Log::info('DeviceController: Transferring token to new user', [
                    'old_user_id' => $existingDevice->user_id,
                    'new_user_id' => $user->id,
                ]);

                $existingDevice->update([
                    'user_id' => $user->id,
                    'platform' => $request->input('platform'),
                    'device_id' => $request->input('device_id'),
                    'device_model' => $request->input('device_model'),
                    'app_version' => $request->input('app_version'),
                    'is_active' => true,
                    'failure_count' => 0,
                    'deactivated_at' => null,
                    'deactivation_reason' => null,
                    'last_used_at' => now(),
                ]);
            } else {
                // Same user, just update
                $existingDevice->update([
                    'platform' => $request->input('platform'),
                    'device_id' => $request->input('device_id'),
                    'device_model' => $request->input('device_model'),
                    'app_version' => $request->input('app_version'),
                    'is_active' => true,
                    'last_used_at' => now(),
                ]);
            }

            return response()->json(responseFormatter(
                constant: DEFAULT_200,
                content: ['device_id' => $existingDevice->id]
            ));
        }

        // Create new device record
        $device = UserDevice::create([
            'user_id' => $user->id,
            'fcm_token' => $fcmToken,
            'platform' => $request->input('platform'),
            'device_id' => $request->input('device_id'),
            'device_model' => $request->input('device_model'),
            'app_version' => $request->input('app_version'),
            'is_active' => true,
            'last_used_at' => now(),
        ]);

        Log::info('DeviceController: Device registered', [
            'user_id' => $user->id,
            'device_id' => $device->id,
        ]);

        return response()->json(responseFormatter(
            constant: DEFAULT_200,
            content: ['device_id' => $device->id]
        ));
    }

    /**
     * Unregister FCM device token
     *
     * POST /api/v1/devices/unregister
     *
     * Request:
     * {
     *   "fcm_token": "dGVzdC10b2tlbi4uLg=="
     * }
     *
     * Response:
     * {
     *   "response_code": "default_200",
     *   "message": "Device unregistered successfully"
     * }
     */
    public function unregister(Request $request): JsonResponse
    {
        $request->validate([
            'fcm_token' => 'required|string|max:500',
        ]);

        $user = auth('api')->user();
        $fcmToken = $request->input('fcm_token');

        Log::info('DeviceController: Unregistering device', [
            'user_id' => $user->id,
        ]);

        $device = UserDevice::where('user_id', $user->id)
            ->where('fcm_token', $fcmToken)
            ->first();

        if ($device) {
            $device->deactivate('user_unregistered');

            Log::info('DeviceController: Device unregistered', [
                'user_id' => $user->id,
                'device_id' => $device->id,
            ]);
        }

        return response()->json(responseFormatter(
            constant: DEFAULT_200,
            content: ['message' => 'Device unregistered successfully']
        ));
    }

    /**
     * Get user's registered devices
     *
     * GET /api/v1/devices
     */
    public function list(): JsonResponse
    {
        $user = auth('api')->user();

        $devices = UserDevice::forUser($user->id)
            ->active()
            ->get()
            ->map(fn($device) => [
                'id' => $device->id,
                'platform' => $device->platform,
                'device_model' => $device->device_model,
                'app_version' => $device->app_version,
                'last_used_at' => $device->last_used_at?->toIso8601String(),
            ]);

        return response()->json(responseFormatter(
            constant: DEFAULT_200,
            content: ['devices' => $devices]
        ));
    }
}
