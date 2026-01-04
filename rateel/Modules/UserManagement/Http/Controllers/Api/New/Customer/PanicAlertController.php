<?php

namespace Modules\UserManagement\Http\Controllers\Api\New\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class PanicAlertController extends Controller
{
    /**
     * Trigger a panic/emergency alert to admin dashboard
     * This is a general SOS button not tied to any trip
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function trigger(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
            'reason' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(DEFAULT_400, null, errorProcessor($validator)), 400);
        }

        $customer = auth('api')->user();

        if (!$customer || $customer->user_type !== CUSTOMER) {
            return response()->json(responseFormatter(DEFAULT_401), 401);
        }

        try {
            // Send Firebase notification to admin dashboard
            sendTopicNotification(
                topic: 'admin_panic_alert_notification',
                title: translate('Emergency Panic Alert'),
                description: translate('Customer :name triggered a panic alert', ['name' => $customer->first_name . ' ' . $customer->last_name]),
                type: 'panic_alert',
                sentBy: $customer->id,
                tripReferenceId: null,
                route: '/admin/business/setup/safety-precaution/precaution',
                additionalData: [
                    'customer_id' => $customer->id,
                    'customer_name' => $customer->first_name . ' ' . $customer->last_name,
                    'customer_phone' => $customer->phone,
                    'lat' => $request->lat,
                    'lng' => $request->lng,
                    'reason' => $request->reason ?? 'Emergency',
                    'timestamp' => now()->toIso8601String(),
                    'alert_type' => 'panic',
                ]
            );

            Log::info('Panic alert triggered', [
                'customer_id' => $customer->id,
                'customer_phone' => $customer->phone,
                'lat' => $request->lat,
                'lng' => $request->lng,
                'reason' => $request->reason,
            ]);

            return response()->json(responseFormatter([
                'response_code' => 'panic_alert_sent_200',
                'message' => translate('Panic alert sent successfully. Help is on the way.'),
            ], [
                'alert_sent' => true,
                'timestamp' => now()->toIso8601String(),
            ]));

        } catch (\Exception $e) {
            Log::error('Failed to send panic alert', [
                'customer_id' => $customer->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(responseFormatter(DEFAULT_500), 500);
        }
    }
}
