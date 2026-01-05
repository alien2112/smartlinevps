<?php

namespace Modules\UserManagement\Http\Controllers\Api\New\Driver;

use App\Http\Controllers\Controller;
use App\Models\DriverNotification;
use App\Models\NotificationSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class NotificationController extends Controller
{
    /**
     * Get all notifications for authenticated driver
     * GET /api/driver/auth/notifications
     */
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'limit' => 'sometimes|integer|min:1|max:100',
            'offset' => 'sometimes|integer|min:0',
            'status' => 'sometimes|in:all,read,unread',
            'category' => 'sometimes|in:trips,earnings,promotions,system,documents',
            'type' => 'sometimes|string',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(
                constant: DEFAULT_400,
                errors: errorProcessor($validator)
            ), 400);
        }

        $driver = auth('api')->user();
        $limit = $request->input('limit', 20);
        $offset = $request->input('offset', 0);

        $query = DriverNotification::where('driver_id', $driver->id)
            ->notExpired()
            ->orderBy('created_at', 'desc');

        // Filter by read status
        if ($request->has('status') && $request->status !== 'all') {
            if ($request->status === 'unread') {
                $query->unread();
            } else {
                $query->read();
            }
        }

        // Filter by category
        if ($request->has('category')) {
            $query->byCategory($request->category);
        }

        // Filter by type
        if ($request->has('type')) {
            $query->byType($request->type);
        }

        $total = $query->count();
        $notifications = $query->skip($offset)->take($limit)->get();

        // Get unread count
        $unreadCount = DriverNotification::where('driver_id', $driver->id)
            ->unread()
            ->notExpired()
            ->count();

        return response()->json(responseFormatter(
            constant: DEFAULT_200,
            content: [
                'notifications' => $notifications->map(fn($n) => $this->formatNotification($n)),
                'unread_count' => $unreadCount,
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset,
            ]
        ));
    }

    /**
     * Get unread count
     * GET /api/driver/auth/notifications/unread-count
     */
    public function unreadCount(): JsonResponse
    {
        $driver = auth('api')->user();

        $count = DriverNotification::where('driver_id', $driver->id)
            ->unread()
            ->notExpired()
            ->count();

        return response()->json(responseFormatter(DEFAULT_200, [
            'unread_count' => $count,
        ]));
    }

    /**
     * Mark notification as read
     * POST /api/driver/auth/notifications/{id}/read
     */
    public function markAsRead(string $id): JsonResponse
    {
        $driver = auth('api')->user();

        $notification = DriverNotification::where('driver_id', $driver->id)
            ->where('id', $id)
            ->first();

        if (!$notification) {
            return response()->json(responseFormatter(DEFAULT_404), 404);
        }

        $notification->markAsRead();

        return response()->json(responseFormatter([
            'response_code' => 'notification_marked_read_200',
            'message' => translate('Notification marked as read'),
        ]));
    }

    /**
     * Mark notification as unread
     * POST /api/driver/auth/notifications/{id}/unread
     */
    public function markAsUnread(string $id): JsonResponse
    {
        $driver = auth('api')->user();

        $notification = DriverNotification::where('driver_id', $driver->id)
            ->where('id', $id)
            ->first();

        if (!$notification) {
            return response()->json(responseFormatter(DEFAULT_404), 404);
        }

        $notification->markAsUnread();

        return response()->json(responseFormatter([
            'response_code' => 'notification_marked_unread_200',
            'message' => translate('Notification marked as unread'),
        ]));
    }

    /**
     * Mark all notifications as read
     * POST /api/driver/auth/notifications/read-all
     */
    public function markAllAsRead(): JsonResponse
    {
        $driver = auth('api')->user();

        DriverNotification::where('driver_id', $driver->id)
            ->unread()
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

        return response()->json(responseFormatter([
            'response_code' => 'all_notifications_marked_read_200',
            'message' => translate('All notifications marked as read'),
        ]));
    }

    /**
     * Delete notification
     * DELETE /api/driver/auth/notifications/{id}
     */
    public function destroy(string $id): JsonResponse
    {
        $driver = auth('api')->user();

        $notification = DriverNotification::where('driver_id', $driver->id)
            ->where('id', $id)
            ->first();

        if (!$notification) {
            return response()->json(responseFormatter(DEFAULT_404), 404);
        }

        $notification->delete();

        return response()->json(responseFormatter([
            'response_code' => 'notification_deleted_200',
            'message' => translate('Notification deleted'),
        ]));
    }

    /**
     * Clear all read notifications
     * POST /api/driver/auth/notifications/clear-read
     */
    public function clearRead(): JsonResponse
    {
        $driver = auth('api')->user();

        $count = DriverNotification::where('driver_id', $driver->id)
            ->read()
            ->delete();

        return response()->json(responseFormatter([
            'response_code' => 'notifications_cleared_200',
            'message' => translate('Read notifications cleared'),
            'data' => ['deleted_count' => $count],
        ]));
    }

    /**
     * Get notification settings
     * GET /api/driver/auth/notifications/settings
     */
    public function getSettings(): JsonResponse
    {
        $driver = auth('api')->user();
        $settings = NotificationSetting::getOrCreateForDriver($driver->id);

        return response()->json(responseFormatter(DEFAULT_200, $settings));
    }

    /**
     * Update notification settings
     * PUT /api/driver/auth/notifications/settings
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'trip_requests_enabled' => 'sometimes|boolean',
            'trip_updates_enabled' => 'sometimes|boolean',
            'payment_notifications_enabled' => 'sometimes|boolean',
            'promotional_notifications_enabled' => 'sometimes|boolean',
            'system_notifications_enabled' => 'sometimes|boolean',
            'email_notifications_enabled' => 'sometimes|boolean',
            'sms_notifications_enabled' => 'sometimes|boolean',
            'push_notifications_enabled' => 'sometimes|boolean',
            'quiet_hours_start' => 'sometimes|nullable|date_format:H:i',
            'quiet_hours_end' => 'sometimes|nullable|date_format:H:i',
            'quiet_hours_enabled' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(
                constant: DEFAULT_400,
                errors: errorProcessor($validator)
            ), 400);
        }

        $driver = auth('api')->user();
        $settings = NotificationSetting::getOrCreateForDriver($driver->id);
        $settings->update($request->only([
            'trip_requests_enabled',
            'trip_updates_enabled',
            'payment_notifications_enabled',
            'promotional_notifications_enabled',
            'system_notifications_enabled',
            'email_notifications_enabled',
            'sms_notifications_enabled',
            'push_notifications_enabled',
            'quiet_hours_start',
            'quiet_hours_end',
            'quiet_hours_enabled',
        ]));

        return response()->json(responseFormatter([
            'response_code' => 'settings_updated_200',
            'message' => translate('Notification settings updated'),
            'data' => $settings,
        ]));
    }

    /**
     * Format notification for API response
     */
    private function formatNotification(DriverNotification $notification): array
    {
        return [
            'id' => $notification->id,
            'type' => $notification->type,
            'title' => $notification->title,
            'message' => $notification->message,
            'data' => $notification->data,
            'action_type' => $notification->action_type,
            'action_url' => $notification->action_url,
            'is_read' => $notification->is_read,
            'read_at' => $notification->read_at?->toIso8601String(),
            'priority' => $notification->priority,
            'category' => $notification->category,
            'created_at' => $notification->created_at->toIso8601String(),
            'time_ago' => $notification->created_at->diffForHumans(),
        ];
    }
}
