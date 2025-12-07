<?php

namespace Modules\TripManagement\Http\Controllers\Web\New;

use App\Http\Controllers\BaseController;
use App\Http\Controllers\Controller;
use Brian2694\Toastr\Facades\Toastr;
use Carbon\Factory;
use Illuminate\Console\Application;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Modules\TripManagement\Http\Requests\ParcelRefundRequestApprovedOrDeniedStoreRequest;
use Modules\TripManagement\Http\Requests\ParcelRefundRequestRefundedStoreRequest;
use Modules\TripManagement\Service\Interface\ParcelRefundServiceInterface;
use Modules\TripManagement\Service\Interface\TripRequestServiceInterface;
use Modules\UserManagement\Service\Interface\CustomerServiceInterface;
use Modules\UserManagement\Service\Interface\DriverServiceInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RefundController extends BaseController
{
    use AuthorizesRequests;

    protected $parcelRefundService;
    protected $tripRequestService;

    public function __construct(ParcelRefundServiceInterface $parcelRefundService, TripRequestServiceInterface $tripRequestService)
    {
        parent::__construct($parcelRefundService);
        $this->parcelRefundService = $parcelRefundService;
        $this->tripRequestService = $tripRequestService;
    }

    public function parcelRefundList(?Request $request, string $type = null)
    {
        $this->authorize('trip_view');

        $attributes = [];
        $search = null;

        if ($type != 'all') {
            $attributes['status'] = $type;
        }
        $request->has('search') ? ($search = $attributes['search'] = $request->search) : null;


        $parcelRefunds = $this->parcelRefundService->index(criteria: $attributes, relations: ['tripRequest.customer', 'tripRequest.parcel.parcelCategory', 'tripRequest.parcelUserInfo', 'refundProofs'], orderBy: ['created_at' => 'desc'], limit: paginationLimit(), offset: $request['page'] ?? 1, appends: $request->all());
        return view('tripmanagement::admin.refund.index', compact('parcelRefunds', 'search', 'type'));
    }

    public function show($id, Request $request): \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View|RedirectResponse
    {
        $this->authorize('trip_view');

        $parcelRefund = $this->parcelRefundService->findOne(id: $id, withTrashed: true);
        if (!$parcelRefund) {
            Toastr::error(PARCEL_REFUND_REQUEST_404['message']);
            return back();
        }
        $trip = $this->tripRequestService->findOne(id: $parcelRefund->trip_request_id, relations: ['customer', 'driver', 'tripStatus', 'parcelRefund.refundProofs', 'parcel.parcelCategory'], withTrashed: true);
        if ($request['page'] == 'log') {

            return view('tripmanagement::admin.refund.log', compact('trip'));
        }

        return view('tripmanagement::admin.refund.details', compact('trip'));
    }

    public function storeApproved($id, ParcelRefundRequestApprovedOrDeniedStoreRequest $request): \Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View|RedirectResponse
    {
        $this->authorize('trip_edit');
        $parcelRefund = $this->parcelRefundService->findOne(id: $id);
        if (!$parcelRefund) {
            Toastr::error(PARCEL_REFUND_REQUEST_404['message']);
            return back();
        }
        $data = array_merge($request->validated(), ['status' => APPROVED]);
        $this->parcelRefundService->update(id: $id, data: $data);
        if ($parcelRefund?->tripRequest?->driver?->fcm_token) {
            try {
                $push = getNotification('refund_request_approved');
                sendDeviceNotification(fcm_token: $parcelRefund?->tripRequest?->driver?->fcm_token,
                    title: translate('refund_request_approved'),
                    description: translate('Refund request of parcel ID #') . $parcelRefund?->tripRequest?->ref_id . ' ' . translate('has been approved by Admin. If you have any quarries please contact with admin.'),
                    status: $push['status'],
                    ride_request_id: $parcelRefund?->trip_request_id,
                    type: $parcelRefund?->trip_request_id,
                    action: 'parcel_refund_request_approved',
                    user_id: $parcelRefund?->tripRequest?->driver?->id
                );
            } catch (\Exception $exception) {

            }
        }
        if ($parcelRefund?->tripRequest?->customer?->fcm_token) {
            try {
                $push = getNotification('refund_request_approved');
                sendDeviceNotification(fcm_token: $parcelRefund?->tripRequest?->customer?->fcm_token,
                    title: translate('refund_request_approved'),
                    description: translate('For parcel ID #') . $parcelRefund?->tripRequest?->ref_id . ', ' . translate('your refund request has been approved by admin. You will be refunded soon.'),
                    status: $push['status'],
                    ride_request_id: $parcelRefund?->trip_request_id,
                    type: $parcelRefund?->trip_request_id,
                    action: 'parcel_refund_request_approved',
                    user_id: $parcelRefund?->tripRequest?->customer?->id
                );
            } catch (\Exception $exception) {

            }
        }
        Toastr::success(PARCEL_REFUND_REQUEST_APPROVED_200['message']);
        return redirect()->route('admin.trip.refund.index', APPROVED);
    }

    public function storeDenied($id, ParcelRefundRequestApprovedOrDeniedStoreRequest $request): \Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View|RedirectResponse
    {
        $this->authorize('trip_edit');
        $parcelRefund = $this->parcelRefundService->findOne(id: $id);
        if (!$parcelRefund) {
            Toastr::error(PARCEL_REFUND_REQUEST_404['message']);
            return back();
        }
        $data = array_merge($request->validated(), ['status' => DENIED]);
        $this->parcelRefundService->update(id: $id, data: $data);
        if ($parcelRefund?->tripRequest?->driver?->fcm_token) {
            try {
                $push = getNotification('refund_request_denied');
                sendDeviceNotification(fcm_token: $parcelRefund?->tripRequest?->driver?->fcm_token,
                    title: translate('refund_request_denied'),
                    description: translate('Refund request of parcel ID #') . $parcelRefund?->tripRequest?->ref_id . ' ' . translate('has been denied by Admin. You don’t need to worry.'),
                    status: $push['status'],
                    ride_request_id: $parcelRefund?->trip_request_id,
                    type: $parcelRefund?->trip_request_id,
                    action: 'parcel_refund_request_denied',
                    user_id: $parcelRefund?->tripRequest?->driver?->id
                );
            } catch (\Exception $exception) {

            }
        }
        if ($parcelRefund?->tripRequest?->customer?->fcm_token) {
            try {
                $push = getNotification('refund_request_denied');
                sendDeviceNotification(fcm_token: $parcelRefund?->tripRequest?->customer?->fcm_token,
                    title: translate('refund_request_denied'),
                    description: translate('For parcel ID #') . $parcelRefund?->tripRequest?->ref_id . ', ' . translate('your refund request has been denied by admin. You can check the denied reason from parcel details.'),
                    status: $push['status'],
                    ride_request_id: $parcelRefund?->trip_request_id,
                    type: $parcelRefund?->trip_request_id,
                    action: 'parcel_refund_request_denied',
                    user_id: $parcelRefund?->tripRequest?->customer?->id
                );
            } catch (\Exception $exception) {

            }
        }
        Toastr::success(PARCEL_REFUND_REQUEST_DENIED_200['message']);
        return redirect()->route('admin.trip.refund.index', DENIED);
    }

    public function store($id, ParcelRefundRequestRefundedStoreRequest $request): \Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View|RedirectResponse
    {
        $this->authorize('trip_edit');
        $parcelRefund = $this->parcelRefundService->findOne(id: $id);
        if (!$parcelRefund) {
            Toastr::error(PARCEL_REFUND_REQUEST_404['message']);
            return back();
        }
        $data = array_merge($request->validated(), ['status' => REFUNDED]);
        $this->parcelRefundService->update(id: $id, data: $data);
        $parcelRefund = $this->parcelRefundService->findOne(id: $id);
        if ($parcelRefund?->tripRequest?->driver?->fcm_token) {
            try {
                $push = getNotification('debited_from_wallet');
                sendDeviceNotification(fcm_token: $parcelRefund?->tripRequest?->driver?->fcm_token,
                    title: translate('debited_from_wallet'),
                    description: translate('Due to a damaged parcel, ') . set_currency_symbol($parcelRefund->refund_amount_by_admin) . ' ' . translate('has been deducted from your wallet. Please settle the amount as soon as possible and check the parcel details.'),
                    status: $push['status'],
                    ride_request_id: $parcelRefund?->trip_request_id,
                    type: $parcelRefund?->trip_request_id,
                    action: 'debited_from_wallet',
                    user_id: $parcelRefund?->tripRequest?->driver?->id
                );
            } catch (\Exception $exception) {

            }
        }
        if ($parcelRefund?->tripRequest?->customer?->fcm_token) {
            if ($request->refund_method == 'coupon') {
                try {
                    $push = getNotification('refunded_as_coupon');
                    sendDeviceNotification(fcm_token: $parcelRefund?->tripRequest?->customer?->fcm_token,
                        title: translate('refund_request_denied'),
                        description: translate('For parcel ID #') . $parcelRefund?->tripRequest?->ref_id . ', ' . translate(' your refund request has been approved by admin and ') . set_currency_symbol($parcelRefund->refund_amount_by_admin) . ' ' . translate('has been issued as a coupon. You can use this coupon for your trip whenever you like.'),
                        status: $push['status'],
                        ride_request_id: $parcelRefund?->coupon_setup_id,
                        type: 'coupon',
                        action: 'refunded_as_coupon',
                        user_id: $parcelRefund?->tripRequest?->customer?->id
                    );
                } catch (\Exception $exception) {

                }
            }
            if ($request->refund_method == 'wallet') {
                try {
                    $push = getNotification('refunded_to_wallet');
                    sendDeviceNotification(fcm_token: $parcelRefund?->tripRequest?->customer?->fcm_token,
                        title: translate('refunded_to_wallet'),
                        description: translate('For parcel ID #') . $parcelRefund?->tripRequest?->ref_id . ', ' . translate(' your refund request has been approved by admin and ') . set_currency_symbol($parcelRefund->refund_amount_by_admin) . ' ' . translate('refunded to your Wallet.'),
                        status: $push['status'],
                        ride_request_id: $parcelRefund?->trip_request_id,
                        type: 'wallet',
                        action: 'refunded_to_wallet',
                        user_id: $parcelRefund?->tripRequest?->customer?->id
                    );
                } catch (\Exception $exception) {

                }
            }

        }
        Toastr::success(PARCEL_REFUND_REQUEST_REFUNDED_200['message']);
        return redirect()->route('admin.trip.refund.index', REFUNDED);
    }

    public function export(Request $request): View|Factory|Response|StreamedResponse|string|Application
    {
        $this->authorize('trip_export');

        $this->authorize('trip_view');

        $attributes = [];

        if ($request->type != 'all') {
            $attributes['status'] = $request->type;
        }
        $request->has('search') ? ($attributes['search'] = $request->search) : null;


        $parcelRefunds = $this->parcelRefundService->index(criteria: $attributes, relations: ['tripRequest.customer', 'tripRequest.parcel.parcelCategory', 'tripRequest.parcelUserInfo', 'refundProofs'], orderBy: ['created_at' => 'desc'], limit: paginationLimit(), offset: $request['page'] ?? 1, appends: $request->all());
        $data = $parcelRefunds->map(fn($item) => [
            'Refund ID' => $item['readable_id'],
            'Trip ID' => $item['tripRequest']->ref_id,
            'Date' => date('d F Y', strtotime($item['created_at'])) . ' ' . date('h:i a', strtotime($item['created_at'])),
            'Category' => $item['tripRequest']['parcel']['parcelCategory']->name,
            'Approximate Price' => $item['parcel_approximate_price'],
            'Customer Name' => $item['tripRequest']['customer']->full_name,
            'Customer Phone' => $item['tripRequest']['customer']->phone,
            'Refund Reason' => $item['reason'],
            'Refund Status' => ucwords($item['status'])
        ]);
        return exportData($data, $request['file'], 'tripmanagement::admin.refund.print');
    }
}
