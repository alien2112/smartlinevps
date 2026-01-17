<?php

namespace Modules\TripManagement\Http\Controllers\Api\New\Customer;

use App\Http\Controllers\Controller;
use App\Services\RealtimeEventPublisher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Modules\TripManagement\Service\Interface\SafetyAlertServiceInterface;
use Modules\TripManagement\Service\Interface\TripRequestServiceInterface;
use Modules\TripManagement\Transformers\SafetyAlertResource;

class SafetyAlertController extends Controller
{
    protected $tripRequestService;
    protected $safetyAlertService;
    protected $realtimeEventPublisher;


    public function __construct(
        TripRequestServiceInterface $tripRequestService, 
        SafetyAlertServiceInterface $safetyAlertService,
        RealtimeEventPublisher $realtimeEventPublisher
    ) {
        $this->tripRequestService = $tripRequestService;
        $this->safetyAlertService = $safetyAlertService;
        $this->realtimeEventPublisher = $realtimeEventPublisher;
    }


    public function storeSafetyAlert(Request $request)
    {
        // Log incoming request for debugging
        \Log::info('Safety Alert Store Request', [
            'user_id' => auth('api')->user()?->id,
            'user_phone' => auth('api')->user()?->phone,
            'request_data' => $request->all(),
            'ip' => $request->ip(),
        ]);

        $validator = Validator::make($request->all(), [
            'trip_request_id' => 'required|uuid',
            'lat' => 'required',
            'lng' => 'required',
        ]);

        if ($validator->fails()) {
            \Log::warning('Safety Alert Validation Failed', [
                'user_id' => auth('api')->user()?->id,
                'errors' => $validator->errors()->toArray(),
            ]);
            return response()->json(['error' => $validator->errors()], 403);
        }
        $whereHasRelations = [
            'sentBy' => [
                'user_type' => CUSTOMER
            ]
        ];
        $safetyAlert = $this->safetyAlertService->findOneBy(criteria: ['trip_request_id' => $request->trip_request_id], whereHasRelations: $whereHasRelations);
        if (!$safetyAlert) {
            $createdSafetyAlert = $this->safetyAlertService->create(data: $request->all());
            
            // If create failed, try to find it as fallback
            if (!$createdSafetyAlert) {
                $data = $this->safetyAlertService->findOneBy(criteria: ['trip_request_id' => $request->trip_request_id], relations: ['trip'], whereHasRelations: $whereHasRelations);
            } else {
                // Load the trip relation on the created model
                $data = $this->safetyAlertService->findOneBy(criteria: ['id' => $createdSafetyAlert->id], relations: ['trip'], whereHasRelations: $whereHasRelations);
            }
            
            // Only proceed if we have valid data
            if (!$data) {
                \Log::error('Failed to create or retrieve safety alert', [
                    'trip_request_id' => $request->trip_request_id,
                    'user_id' => auth('api')->user()?->id,
                ]);
                return response()->json(['error' => 'Failed to create safety alert'], 500);
            }
            
            // Send Firebase topic notification
            sendTopicNotification(
                topic: 'admin_safety_alert_notification',
                title: translate('new_safety_alert'),
                description: translate('you_have_new_safety_alert'),
                type: 'customer',
                sentBy: auth('api')->user()?->id,
                tripReferenceId: $data?->trip?->ref_id,
                route: $this->safetyAlertService->safetyAlertLatestUserRoute()
            );
            
            // Publish socket event for real-time alert (only if data is not null)
            if ($data) {
                $this->realtimeEventPublisher->publishSafetyAlertCreated($data, 'customer');
            }
            
            $safetyAlertData = new SafetyAlertResource($data);
            return response()->json(responseFormatter(SAFETY_ALERT_STORE_200, $safetyAlertData));
        }
        return response()->json(responseFormatter(SAFETY_ALERT_ALREADY_EXIST_400), 403);
    }

    public function resendSafetyAlert($tripRequestId)
    {
        $whereHasRelations = [
            'sentBy' => [
                'user_type' => CUSTOMER
            ]
        ];

        $safetyAlert = $this->safetyAlertService->findOneBy(criteria: ['trip_request_id' => $tripRequestId, 'status' => PENDING], relations: ['trip'], whereHasRelations: $whereHasRelations);
        if (!$safetyAlert) {
            return response()->json(responseFormatter(SAFETY_ALERT_NOT_FOUND_404), 403);
        }
        $safetyAlert->increment('number_of_alert');
        $safetyAlertData = new SafetyAlertResource($safetyAlert);
        
        // Send Firebase topic notification
        sendTopicNotification(
            topic: 'admin_safety_alert_notification',
            title: translate('new_safety_alert'),
            description: translate('you_have_new_safety_alert'),
            type: 'customer',
            sentBy: auth('api')->user()?->id,
            tripReferenceId: $safetyAlert?->trip?->ref_id,
            route: $this->safetyAlertService->safetyAlertLatestUserRoute()
        );
        
        // Publish socket event for real-time alert
        $this->realtimeEventPublisher->publishSafetyAlertCreated($safetyAlert, 'customer');

        return response()->json(responseFormatter(SAFETY_ALERT_RESEND_200, $safetyAlertData));
    }

    public function markAsSolvedSafetyAlert($tripRequestId)
    {
        $whereHasRelations = [
            'sentBy' => [
                'user_type' => CUSTOMER
            ]
        ];
        $safetyAlert = $this->safetyAlertService->findOneBy(criteria: ['trip_request_id' => $tripRequestId, 'status' => PENDING], whereHasRelations: $whereHasRelations);
        if (!$safetyAlert) {
            return response()->json(responseFormatter(SAFETY_ALERT_NOT_FOUND_404), 403);
        }
        $attributes = ['resolved_by' => auth('api')->user()?->id];
        $this->safetyAlertService->updatedBy(criteria: ['trip_request_id' => $tripRequestId, 'sent_by' => $safetyAlert->sent_by], data: $attributes);
        $data = $this->safetyAlertService->findOneBy(criteria: ['trip_request_id' => $tripRequestId, 'sent_by' => $safetyAlert->sent_by]);
        $safetyAlertData = new SafetyAlertResource($data);

        return response()->json(responseFormatter(SAFETY_ALERT_MARK_AS_SOLVED, $safetyAlertData));
    }

    public function showSafetyAlert($tripRequestId)
    {
        $whereHasRelations = [
            'sentBy' => [
                'user_type' => CUSTOMER
            ]
        ];
        $safetyAlert = $this->safetyAlertService->findOneBy(criteria: ['trip_request_id' => $tripRequestId], whereHasRelations: $whereHasRelations);

        if (!$safetyAlert) {
            return response()->json(responseFormatter(SAFETY_ALERT_NOT_FOUND_404), 403);
        }

        $safetyAlertData = new SafetyAlertResource($safetyAlert);

        return response()->json(responseFormatter(DEFAULT_200, $safetyAlertData));
    }

    public function deleteSafetyAlert($tripRequestId)
    {
        $whereHasRelations = [
            'sentBy' => [
                'user_type' => CUSTOMER
            ]
        ];
        $safetyAlert = $this->safetyAlertService->findOneBy(criteria: ['trip_request_id' => $tripRequestId], whereHasRelations: $whereHasRelations);
        if (!$safetyAlert) {
            return response()->json(responseFormatter(SAFETY_ALERT_NOT_FOUND_404), 403);
        }
        $safetyAlert->delete();
        return response()->json(responseFormatter(SAFETY_ALERT_UNDO_200));
    }
}
