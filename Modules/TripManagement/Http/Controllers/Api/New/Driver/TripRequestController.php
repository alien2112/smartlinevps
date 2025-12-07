<?php

namespace Modules\TripManagement\Http\Controllers\Api\New\Driver;


use Illuminate\Routing\Controller;
use Modules\Gateways\Traits\Payment;
use Modules\TransactionManagement\Traits\TransactionTrait;
use Modules\TripManagement\Lib\CommonTrait;
use Modules\TripManagement\Lib\CouponCalculationTrait;
use Modules\TripManagement\Service\Interface\TripRequestServiceInterface;
use Modules\TripManagement\Transformers\TripRequestResource;
use Modules\UserManagement\Lib\LevelHistoryManagerTrait;

class TripRequestController extends Controller
{

    use CommonTrait, TransactionTrait, Payment, CouponCalculationTrait, LevelHistoryManagerTrait;

    protected $tripRequestService;

    public function __construct(TripRequestServiceInterface $tripRequestService)
    {
        $this->tripRequestService = $tripRequestService;
    }

    public function currentRideStatus()
    {

        $relations = ['tripStatus', 'customer', 'driver', 'time', 'coordinate', 'time', 'fee', 'parcelRefund'];
        $criteria = ['type' => 'ride_request', 'driver_id' => auth('api')->id()];
        $orderBy = ['created_at' => 'desc'];
        $withAvgRelations = [['customerReceivedReviews', 'rating']];
        $trip = $this->tripRequestService->findOneBy(criteria: $criteria, withAvgRelations: $withAvgRelations, relations: $relations, orderBy: $orderBy);

        if (!$trip || $trip->fee->cancelled_by == 'driver' ||
            (!$trip->driver_id && $trip->current_status == 'cancelled') ||
            ($trip->driver_id && $trip->payment_status == PAID)) {
            return response()->json(responseFormatter(constant: DEFAULT_404), 404);
        }
        $trip = TripRequestResource::make($trip);
        return response()->json(responseFormatter(constant: DEFAULT_200, content: $trip));
    }

}
