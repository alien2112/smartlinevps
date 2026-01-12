<?php

namespace Modules\UserManagement\Http\Controllers\Api\New\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Modules\TripManagement\Entities\SafetyAlert;

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
            // Create SafetyAlert record in database
            $safetyAlert = SafetyAlert::create([
                'trip_request_id' => null, // No trip for panic alerts
                'sent_by' => $customer->id,
                'alert_location' => json_encode([
                    'lat' => $request->lat,
                    'lng' => $request->lng,
                ]),
                'reason' => $request->reason ? [$request->reason] : ['panic_alert'],
                'trip_status_when_make_alert' => null,
                'status' => 'pending',
                'number_of_alert' => 1,
            ]);

            // Build description with location and reason
            $description = translate('Customer :name triggered a panic alert', ['name' => $customer->first_name . ' ' . $customer->last_name]);
            $description .= ' | Location: ' . $request->lat . ',' . $request->lng;
            if ($request->reason) {
                $description .= ' | Reason: ' . $request->reason;
            }

            // Send Firebase notification to admin dashboard
            sendTopicNotification(
                topic: 'admin_panic_alert_notification',
                title: translate('Emergency Panic Alert'),
                description: $description,
                image: null,
                ride_request_id: null,
                type: 'panic_alert',
                sentBy: $customer->id,
                tripReferenceId: null,
                route: '/admin/business/setup/safety-precaution/precaution'
            );

            Log::info('Panic alert triggered', [
                'safety_alert_id' => $safetyAlert->id,
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
                'alert_id' => $safetyAlert->id,
                'timestamp' => now()->toIso8601String(),
            ]));

        } catch (\Exception $e) {
            Log::error('Failed to send panic alert', [
                'customer_id' => $customer->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(responseFormatter(DEFAULT_400, null, ['error' => 'Failed to trigger panic alert']), 500);
        }
    }
}
