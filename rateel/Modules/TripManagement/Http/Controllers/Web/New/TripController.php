<?php

namespace Modules\TripManagement\Http\Controllers\Web\New;

use App\Http\Controllers\BaseController;
use Carbon\Factory;
use Illuminate\Console\Application;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Modules\TripManagement\Service\Interface\SafetyAlertServiceInterface;
use Modules\TripManagement\Service\Interface\TripRequestServiceInterface;
use Brian2694\Toastr\Facades\Toastr;
use Modules\UserManagement\Service\Interface\CustomerServiceInterface;
use Modules\UserManagement\Service\Interface\DriverServiceInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TripController extends BaseController
{
    use AuthorizesRequests;

    protected $tripRequestService;
    protected $customerService;
    protected $driverService;
    protected $safetyAlertService;

    public function __construct(TripRequestServiceInterface $tripRequestService, CustomerServiceInterface $customerService,
                                DriverServiceInterface      $driverService, SafetyAlertServiceInterface $safetyAlertService)
    {
        parent::__construct($tripRequestService);
        $this->tripRequestService = $tripRequestService;
        $this->customerService = $customerService;
        $this->driverService = $driverService;
        $this->safetyAlertService = $safetyAlertService;
    }

    public function tripList(?Request $request, string $type = null)
    {
        $this->authorize('trip_view');
        $attributes = [];
        $search = null;
        $date = null;
        if ($request->has('data')) {
            $date = getDateRange($request->data);
            $attributes['from'] = $date['start'];
            $attributes['to'] = $date['end'];
        }
        if ($type != 'all') {
            $attributes['current_status'] = $type;
        }
        $request->has('search') ? ($search = $attributes['search'] = $request->search) : null;

        //filter
        $customers = $this->customerService->getBy(criteria: ['user_type' => CUSTOMER], withTrashed: true);
        $drivers = $this->customerService->getBy(criteria: ['user_type' => DRIVER], withTrashed: true);


        #customer filter
        if ($request->has('customer_id')) {
            if ($request->customer_id && $request->customer_id != ALL) {
                $attributes['customer_id'] = $request->customer_id;
            }
        }
        #driver filter
        if ($request->has('driver_id')) {
            if ($request->driver_id && $request->driver_id != ALL) {
                $attributes['driver_id'] = $request->driver_id;
            }
        }

        #trip type filter
        if ($request->has('trip_type')) {
            if ($request->trip_type && $request->trip_type != ALL) {
                $attributes['type'] = $request->trip_type;
            }
        }

        #trip status filter
        if ($request->has('trip_status')) {
            if ($request->trip_status && $request->trip_status != ALL) {
                $attributes['current_status'] = $request->trip_status;
            }
        }

        #date filter
        if (!is_null($request->filter_date) && $request->filter_date != 'custom_date') {
            $attributes['filter_date'] = getDateRange($request->filter_date);
        } elseif (!is_null($request->filter_date)) {
            $attributes['filter_date'] = getDateRange([
                'start' => $request->start_date,
                'end' => $request->end_date
            ]);
        }
        $trips = $this->tripRequestService->index(criteria: $attributes, relations: ['tripStatus', 'customer', 'driver', 'fee', 'safetyAlerts'], orderBy: ['created_at' => 'desc'], limit: paginationLimit(), offset: $request['page'] ?? 1, appends: $request->all());
        $trip_counts = null;
        if ($type == 'all') {
            $trip_counts = $this->tripRequestService->statusWiseTotalTripRecords(['from' => $date['start'] ?? null, 'to' => $date['end'] ?? null]);
        }
        if ($request->ajax()) {
            return response()->json(view('tripmanagement::admin.trip.partials._trip-list-stat', compact('trip_counts', 'type'))->render());
        }
        return view('tripmanagement::admin.trip.index', compact('trips', 'type', 'trip_counts', 'search', 'customers', 'drivers'));
    }

    public function show($id, Request $request): \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View|RedirectResponse
    {
        $this->authorize('trip_view');

        $trip = $this->tripRequestService->findOne(id: $id, relations: ['customer', 'driver', 'tripStatus', 'parcelRefund.refundProofs', 'parcel.parcelCategory'], withTrashed: true);
        $safetyAlerts = $this->safetyAlertService->getBy(criteria: ['trip_request_id' => $id], relations: ['sentBy', 'solvedBy', 'trip', 'lastLocation'], orderBy: ['created_at' => 'desc']);
        if (!$trip) {
            Toastr::error(TRIP_REQUEST_404['message']);
            return back();
        }
        if ($request['page'] == 'log') {

            return view('tripmanagement::admin.trip.log', compact('trip'));
        }

        return view('tripmanagement::admin.trip.details', compact('trip', 'safetyAlerts'));
    }

    public function destroy($id)
    {
        $this->authorize('trip_delete');

        $this->tripRequestService->delete($id);

        Toastr::success(TRIP_REQUEST_DELETE_200['message']);
        return back();
    }

    public function export(Request $request): View|Factory|Response|StreamedResponse|string|Application
    {
        $this->authorize('trip_export');

        $attributes = [];
        if ($request->has('data')) {
            $date = getDateRange($request->data);
            $attributes['from'] = $date['start'];
            $attributes['to'] = $date['end'];
        }
        if ($request->has('type')) {
            if ($request->type && $request->type != ALL) {
                $attributes['current_status'] = $request->type;
            }
        }
        $request->has('search') ? ($search = $attributes['search'] = $request->search) : null;

        #customer filter
        if ($request->has('customer_id')) {
            if ($request->customer_id && $request->customer_id != ALL) {
                $attributes['customer_id'] = $request->customer_id;
            }
        }
        #driver filter
        if ($request->has('driver_id')) {
            if ($request->driver_id && $request->driver_id != ALL) {
                $attributes['driver_id'] = $request->driver_id;
            }
        }

        #trip type filter
        if ($request->has('trip_type')) {
            if ($request->trip_type && $request->trip_type != ALL) {
                $attributes['type'] = $request->trip_type;
            }
        }

        #trip status filter
        if ($request->has('trip_status')) {
            if ($request->trip_status && $request->trip_status != ALL) {
                $attributes['current_status'] = $request->trip_status;
            }
        }

        #date filter
        if (!is_null($request->filter_date) && $request->filter_date != 'custom_date') {
            $attributes['filter_date'] = getDateRange($request->filter_date);
        } elseif (!is_null($request->filter_date)) {
            $attributes['filter_date'] = getDateRange([
                'start' => $request->start_date,
                'end' => $request->end_date
            ]);
        }
        $trips = $this->tripRequestService->index(criteria: $attributes, relations: ['tripStatus', 'customer', 'driver', 'fee'], orderBy: ['created_at' => 'desc'], appends: $request->all());
        $data = $trips->map(fn($item) => [
            'id' => $item['id'],
            'Trip ID' => $item['ref_id'],
            'Date' => date('d F Y', strtotime($item['created_at'])) . ' ' . date('h:i a', strtotime($item['created_at'])),
            'Customer' => $item['customer']?->first_name . ' ' . $item['customer']?->last_name,
            'Driver' => $item['driver'] ? $item['driver']?->first_name . ' ' . $item['driver']?->last_name : 'no driver assigned',
            'Trip Cost' => $item['current_status'] == 'completed' ? getCurrencyFormat($item['actual_fare'] ?? 0) : getCurrencyFormat($item['estimated_fare'] ?? 0),
            'Coupon Discount' => getCurrencyFormat($item['coupon_amount'] ?? 0),
            'Delay Fee' => getCurrencyFormat($item['fee'] ? ($item['fee']->delay_fee) : 0),
            'Idle Fee' => getCurrencyFormat($item['fee'] ? ($item['fee']->idle_fee) : 0),
            'Cancellation Fee' => getCurrencyFormat($item['fee'] ? ($item['fee']->cancellation_fee) : 0),
            'Vat/Tax Fee' => getCurrencyFormat($item['fee'] ? ($item['fee']->vat_tax) : 0),
            'Total Additional Fee' => getCurrencyFormat($item['fee'] ? ($item['fee']->waiting_fee + $item['fee']->delay_fee + $item['fee']->idle_fee + $item['fee']->cancellation_fee + $item['fee']->vat_tax) : 0),
            'Total Trip Cost' => getCurrencyFormat($item['paid_fare'] - $item['tips']),
            'Admin Commission' => getCurrencyFormat($item['fee'] ? $item['fee']->admin_commission : 0),
            'Payment Status' => ucwords($item['payment_status']),
            'Trip Status' => ucwords($item['current_status'])
        ]);
        return exportData($data, $request['file'], 'tripmanagement::admin.trip.print');
    }

}
