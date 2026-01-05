<?php

declare(strict_types=1);

namespace Modules\CouponManagement\Http\Controllers\Api\Customer;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\CouponManagement\Http\Requests\ValidateCouponRequest;
use Modules\CouponManagement\Service\CouponService;

class CouponController extends Controller
{
    public function __construct(
        private readonly CouponService $couponService
    ) {}

    /**
     * Validate a coupon code
     *
     * POST /api/v1/coupons/validate
     *
     * Request:
     * {
     *   "code": "SAVE20",
     *   "fare": 25.50,
     *   "city_id": "city-uuid",
     *   "service_type": "ride"
     * }
     *
     * Response (success):
     * {
     *   "response_code": "default_200",
     *   "message": "Coupon is valid",
     *   "content": {
     *     "valid": true,
     *     "discount_amount": 5.10,
     *     "coupon": {
     *       "id": "coupon-uuid",
     *       "code": "SAVE20",
     *       "name": "20% Off",
     *       "type": "PERCENT",
     *       "value": 20,
     *       "max_discount": 10.00
     *     },
     *     "meta": {
     *       "original_fare": 25.50,
     *       "discounted_fare": 20.40
     *     }
     *   }
     * }
     *
     * Response (error):
     * {
     *   "response_code": "default_400",
     *   "message": "Coupon validation failed",
     *   "content": {
     *     "valid": false,
     *     "error_code": "COUPON_EXPIRED",
     *     "error_message": "This coupon has expired"
     *   }
     * }
     */
    public function validate(ValidateCouponRequest $request): JsonResponse
    {
        $user = auth('api')->user();
        $code = $request->input('code');
        $estimateContext = $request->getEstimateContext();

        $result = $this->couponService->validateCoupon($user, $code, $estimateContext);

        if ($result['valid']) {
            return response()->json(responseFormatter(
                constant: DEFAULT_200,
                content: $result
            ));
        }

        return response()->json(responseFormatter(
            constant: DEFAULT_400,
            content: $result
        ), 400);
    }

    /**
     * Get user's available coupons
     *
     * GET /api/v1/coupons/available
     */
    public function available(): JsonResponse
    {
        $user = auth('api')->user();

        // Get public coupons + targeted coupons for this user
        $coupons = \Modules\CouponManagement\Entities\Coupon::active()
            ->valid()
            ->where(function ($query) use ($user) {
                $query->where('eligibility_type', 'ALL')
                    ->orWhere(function ($q) use ($user) {
                        $q->where('eligibility_type', 'TARGETED')
                            ->whereIn('id', function ($sub) use ($user) {
                                $sub->select('coupon_id')
                                    ->from('coupon_target_users')
                                    ->where('user_id', $user->id);
                            });
                    });
            })
            ->get()
            ->map(function ($coupon) use ($user) {
                // Check if user can still use this coupon
                $canUse = !$coupon->isGlobalLimitReached() && !$coupon->isUserLimitReached($user->id);

                return [
                    'id' => $coupon->id,
                    'code' => $coupon->code,
                    'name' => $coupon->name,
                    'description' => $coupon->description,
                    'type' => $coupon->type,
                    'value' => $coupon->value,
                    'max_discount' => $coupon->max_discount,
                    'min_fare' => $coupon->min_fare,
                    'ends_at' => $coupon->ends_at->toIso8601String(),
                    'can_use' => $canUse,
                ];
            });

        return response()->json(responseFormatter(
            constant: DEFAULT_200,
            content: ['coupons' => $coupons]
        ));
    }
}
