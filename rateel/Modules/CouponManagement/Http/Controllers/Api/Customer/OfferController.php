<?php

declare(strict_types=1);

namespace Modules\CouponManagement\Http\Controllers\Api\Customer;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\CouponManagement\Service\OfferService;

class OfferController extends Controller
{
    public function __construct(
        private readonly OfferService $offerService
    ) {}

    /**
     * Get available offers for the customer
     *
     * GET /api/v1/offers
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth('api')->user();

        $offers = $this->offerService->getOffersForApp($user);

        $data = $offers->map(fn($offer) => [
            'id' => $offer->id,
            'title' => $offer->title,
            'short_description' => $offer->short_description,
            'terms_conditions' => $offer->terms_conditions,
            'image' => $offer->image ? asset('storage/' . $offer->image) : null,
            'banner_image' => $offer->banner_image ? asset('storage/' . $offer->banner_image) : null,
            'discount_type' => $offer->discount_type,
            'discount_amount' => $offer->discount_amount,
            'max_discount' => $offer->max_discount,
            'min_trip_amount' => $offer->min_trip_amount,
            'start_date' => $offer->start_date->toIso8601String(),
            'end_date' => $offer->end_date->toIso8601String(),
            'zones' => $offer->zones_list,
            'vehicle_categories' => $offer->vehicle_categories_list,
            'remaining_uses' => $offer->limit_per_user - $offer->usages()
                ->where('user_id', $user->id)
                ->where('status', 'applied')
                ->count(),
        ]);

        return response()->json(responseFormatter(
            constant: DEFAULT_200,
            content: $data
        ));
    }

    /**
     * Get best available offer for a specific trip context
     *
     * POST /api/v1/offers/best
     *
     * Request:
     * {
     *   "zone_id": "uuid",
     *   "trip_type": "ride_request" | "parcel",
     *   "vehicle_category_id": "uuid",
     *   "fare": 50.00
     * }
     */
    public function getBest(Request $request): JsonResponse
    {
        $request->validate([
            'zone_id' => 'nullable|uuid',
            'trip_type' => 'nullable|in:ride_request,parcel',
            'vehicle_category_id' => 'nullable|uuid',
            'fare' => 'required|numeric|min:0',
        ]);

        $user = auth('api')->user();

        $context = [
            'zone_id' => $request->input('zone_id'),
            'trip_type' => $request->input('trip_type', 'ride_request'),
            'vehicle_category_id' => $request->input('vehicle_category_id'),
            'fare' => $request->input('fare'),
        ];

        $result = $this->offerService->getBestOffer($user, $context);

        if (!$result) {
            return response()->json(responseFormatter(
                constant: DEFAULT_200,
                content: [
                    'has_offer' => false,
                    'offer' => null,
                    'discount_amount' => 0,
                    'original_fare' => $context['fare'],
                    'final_fare' => $context['fare'],
                ]
            ));
        }

        return response()->json(responseFormatter(
            constant: DEFAULT_200,
            content: [
                'has_offer' => true,
                'offer' => [
                    'id' => $result['offer']->id,
                    'title' => $result['offer']->title,
                    'discount_type' => $result['offer']->discount_type,
                    'discount_amount' => $result['offer']->discount_amount,
                    'max_discount' => $result['offer']->max_discount,
                ],
                'discount_amount' => $result['discount_amount'],
                'original_fare' => $result['original_fare'],
                'final_fare' => $result['final_fare'],
            ]
        ));
    }

    /**
     * Get offer details
     *
     * GET /api/v1/offers/{id}
     */
    public function show(string $id): JsonResponse
    {
        $user = auth('api')->user();

        $offer = \Modules\CouponManagement\Entities\Offer::active()
            ->valid()
            ->findOrFail($id);

        $userUsedCount = $offer->usages()
            ->where('user_id', $user->id)
            ->where('status', 'applied')
            ->count();

        return response()->json(responseFormatter(
            constant: DEFAULT_200,
            content: [
                'id' => $offer->id,
                'title' => $offer->title,
                'short_description' => $offer->short_description,
                'terms_conditions' => $offer->terms_conditions,
                'image' => $offer->image ? asset('storage/' . $offer->image) : null,
                'banner_image' => $offer->banner_image ? asset('storage/' . $offer->banner_image) : null,
                'discount_type' => $offer->discount_type,
                'discount_amount' => $offer->discount_amount,
                'max_discount' => $offer->max_discount,
                'min_trip_amount' => $offer->min_trip_amount,
                'start_date' => $offer->start_date->toIso8601String(),
                'end_date' => $offer->end_date->toIso8601String(),
                'zones' => $offer->zones_list,
                'customer_levels' => $offer->customer_levels_list,
                'vehicle_categories' => $offer->vehicle_categories_list,
                'limit_per_user' => $offer->limit_per_user,
                'user_used_count' => $userUsedCount,
                'remaining_uses' => $offer->limit_per_user - $userUsedCount,
                'can_use' => $userUsedCount < $offer->limit_per_user,
            ]
        ));
    }
}
