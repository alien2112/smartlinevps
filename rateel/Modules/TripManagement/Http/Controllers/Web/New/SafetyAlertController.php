<?php

namespace Modules\TripManagement\Http\Controllers\Web\New;

use App\Http\Controllers\Controller;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Modules\TripManagement\Service\Interface\SafetyAlertServiceInterface;
use Modules\TripManagement\Service\Interface\TripRequestServiceInterface;
use Modules\UserManagement\Service\Interface\UserLastLocationServiceInterface;

class SafetyAlertController extends Controller
{
    protected $tripRequestService;
    protected $safetyAlertService;

    protected $userLastLocationService;


    public function __construct(TripRequestServiceInterface $tripRequestService, SafetyAlertServiceInterface $safetyAlertService, UserLastLocationServiceInterface $userLastLocationService)
    {
        $this->tripRequestService = $tripRequestService;
        $this->safetyAlertService = $safetyAlertService;
        $this->userLastLocationService = $userLastLocationService;
    }

    public function index($type, Request $request)
    {
        $this->authorize('trip_view');
        $whereHasRelation = [
            'sentBy' => [
                'user_type' => $type
            ]
        ];
        $relations = [
            'sentBy', 'solvedBy', 'trip.customer', 'trip.driver'
        ];
        $safetyAlerts = $this->safetyAlertService->index(criteria: $request->all(), relations: $relations, whereHasRelations: $whereHasRelation, orderBy: ['created_at' => 'desc'], limit: paginationLimit(), offset: $request['page'] ?? 1);
        return view('tripmanagement::admin.safety-alert.index', compact('safetyAlerts', 'type'));
    }

    public function export($type, Request $request)
    {
        $this->authorize('trip_export');
        $whereHasRelations = [
            'sentBy' => [
                'user_type' => $type
            ]
        ];
        $relations = [
            'sentBy', 'solvedBy', 'trip.customer', 'trip.driver'
        ];
        $data = $this->safetyAlertService->export(criteria: $request->all(), relations: $relations, whereHasRelations: $whereHasRelations);
        return exportData($data, $request['file'], '');
    }

    public function markAsSolved($id)
    {
        $this->authorize('trip_view');
        $attributes = ['resolved_by' => Auth::user()?->id];
        $safetyAlert = $this->safetyAlertService->findOneBy(criteria: ['id' => $id]);
        if (!$safetyAlert) {
            Toastr::error('Safety Alert not found');
            return redirect()->back();
        }

        if ($safetyAlert->status == 'solved') {
            Toastr::error('This safety alert is already marked as solved');
            return redirect()->back();
        }

        $this->safetyAlertService->updatedBy(criteria: ['trip_request_id' => $safetyAlert?->trip_request_id, 'sent_by' => $safetyAlert->sent_by], data: $attributes);
        sendDeviceNotification(fcm_token: $safetyAlert?->sentBy?->fcm_token,
            title: translate("Safety Alert Resolved"),
            description: translate("The issue with your safety alert has been resolved."),
            status: 1,
            ride_request_id: $safetyAlert?->trip_request_id,
            type: 'safety_alert',
            action: 'safety_alert_solved',
            user_id: $safetyAlert?->sent_by
        );
        Toastr::success('Safety Alert marked as solved successfully');
        return redirect()->back()->with('success', 'Safety Alert marked as solved successfully');
    }

    public function ajaxMarkAsSolved($id)
    {
        $attributes = ['resolved_by' => Auth::user()?->id];
        $safetyAlert = $this->safetyAlertService->findOneBy(criteria: ['id' => $id]);
        if (!$safetyAlert) {
            return response()->json(['error' => 'Safety Alert not found'], 403);
        }
        if ($safetyAlert->status == 'solved') {
            return response()->json([
                'status' => 403,
                'code' => 'already_solved',
                'message' => translate('This safety alert is already marked as solved')
            ], 403);
        }
        $this->safetyAlertService->updatedBy(criteria: ['trip_request_id' => $safetyAlert?->trip_request_id, 'sent_by' => $safetyAlert?->sent_by], data: $attributes);
        sendDeviceNotification(fcm_token: $safetyAlert?->sentBy?->fcm_token,
            title: translate("Safety Alert Resolved"),
            description: translate("The issue with your safety alert has been resolved."),
            status: 1,
            ride_request_id: $safetyAlert?->trip_request_id,
            type: 'safety_alert',
            action: 'safety_alert_solved',
            user_id: $safetyAlert?->sent_by
        );

        return response()->json(['success' => translate('Safety Alert marked as solved successfully')], 200);
    }
}
