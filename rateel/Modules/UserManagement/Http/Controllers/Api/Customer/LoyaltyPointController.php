<?php

namespace Modules\UserManagement\Http\Controllers\Api\Customer;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Modules\TransactionManagement\Traits\TransactionTrait;
use Modules\UserManagement\Interfaces\CustomerInterface;
use Modules\UserManagement\Interfaces\LoyaltyPointsHistoryInterface;
use Modules\UserManagement\Transformers\LoyaltyPointsHistoryResource;

class LoyaltyPointController extends Controller
{
    use TransactionTrait;

    public function __construct(
        private CustomerInterface $customer,
        private LoyaltyPointsHistoryInterface $history
    )
    {
    }

    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'limit' => 'required|integer',
            'offset' => 'required|integer',
        ]);
        if ($validator->fails()) {

            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 403);
        }
        $attributes = [
            'column' => 'user_id',
            'value' => auth('api')->id()
        ];
        $history = $this->history->get(limit: $request->limit,
            offset: $request->offset,
            dynamic_page: true,
            attributes: $attributes);
        $history = LoyaltyPointsHistoryResource::collection($history);

        return response()->json(responseFormatter(constant: DEFAULT_200, content: $history, limit: $request->limit, offset: $request->offset));
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function convert(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'points' => 'required',
        ]);
        if ($validator->fails()) {

            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 403);
        }
        $conversion_rate = businessConfig('loyalty_points', 'customer_settings')?->value;
        $user = auth('api')->user();
        // Minimum points to convert is 1
        $minPoints = 1;
        if (($conversion_rate['status'] ?? false) && $user->loyalty_points >= $request->points && $request->points >= $minPoints) {
            DB::beginTransaction();
            $driver = $this->customer->update(attributes: [
                'column' => 'id',
                'decrease' => $request->points,
            ], id: $user->id);
            // Use point_value if available (1 point = X currency), otherwise calculate from points
            $pointValue = $conversion_rate['point_value'] ?? (1 / ($conversion_rate['points'] ?? 1));
            $balance = $request->points * $pointValue;
            $account = $this->customerLoyaltyPointsTransaction($driver, $balance);
            $attributes = [
                'user_id' => $user->id,
                'model_id' => $account->id,
                'model' => 'userAccount',
                'points' => $request->points,
                'type' => 'debit'
            ];
            $this->history->store($attributes);

            DB::commit();

            return response()->json(responseFormatter(constant: DEFAULT_UPDATE_200));
        }

        return response()->json(responseFormatter(constant: INSUFFICIENT_POINTS_403), 403);
    }

}
