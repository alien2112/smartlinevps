<?php

namespace App\Http\Controllers\Api\Driver;

use App\Http\Controllers\Controller;
use App\Models\DriverNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PromotionController extends Controller
{
    /**
     * Get all available promotions
     * GET /api/driver/auth/promotions
     */
    public function index(Request $request): JsonResponse
    {
        $driver = auth('api')->user();

        $validator = Validator::make($request->all(), [
            'status' => 'sometimes|in:active,claimed,expired,all',
            'limit' => 'sometimes|integer|min:1|max:50',
            'offset' => 'sometimes|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(
                constant: DEFAULT_400,
                errors: errorProcessor($validator)
            ), 400);
        }

        $limit = $request->input('limit', 20);
        $offset = $request->input('offset', 0);
        $status = $request->input('status', 'active');

        $query = DB::table('driver_promotions')
            ->where('is_active', true)
            ->where(function($q) use ($driver) {
                $q->whereNull('target_driver_id')
                  ->orWhere('target_driver_id', $driver->id);
            });

        if ($status === 'active') {
            $query->where('expires_at', '>', now())
                  ->where(function($q) {
                      $q->whereNull('starts_at')
                        ->orWhere('starts_at', '<=', now());
                  });
        }

        $total = $query->count();
        $promotions = $query->orderBy('priority', 'desc')
            ->orderBy('created_at', 'desc')
            ->skip($offset)
            ->take($limit)
            ->get();

        $result = $promotions->map(function($promo) use ($driver) {
            // Check if driver has claimed
            $claim = DB::table('promotion_claims')
                ->where('promotion_id', $promo->id)
                ->where('driver_id', $driver->id)
                ->first();

            $canClaim = !$claim &&
                       ($promo->max_claims === null || $promo->current_claims < $promo->max_claims) &&
                       ($promo->expires_at === null || $promo->expires_at > now());

            return [
                'id' => $promo->id,
                'title' => $promo->title,
                'description' => $promo->description,
                'terms_conditions' => $promo->terms_conditions,
                'image_url' => $promo->image_url ? asset('storage/' . $promo->image_url) : null,
                'action_type' => $promo->action_type,
                'action_url' => $promo->action_url,
                'priority' => $promo->priority,
                'starts_at' => $promo->starts_at,
                'expires_at' => $promo->expires_at,
                'is_claimed' => (bool) $claim,
                'claimed_at' => $claim?->claimed_at,
                'can_claim' => $canClaim,
                'claims_remaining' => $promo->max_claims
                    ? max(0, $promo->max_claims - $promo->current_claims)
                    : null,
            ];
        });

        return response()->json(responseFormatter(DEFAULT_200, [
            'promotions' => $result,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
        ]));
    }

    /**
     * Get specific promotion details
     * GET /api/driver/auth/promotions/{id}
     */
    public function show(string $id): JsonResponse
    {
        $driver = auth('api')->user();

        $promotion = DB::table('driver_promotions')
            ->where('id', $id)
            ->where('is_active', true)
            ->where(function($q) use ($driver) {
                $q->whereNull('target_driver_id')
                  ->orWhere('target_driver_id', $driver->id);
            })
            ->first();

        if (!$promotion) {
            return response()->json(responseFormatter(DEFAULT_404), 404);
        }

        // Check if driver has claimed
        $claim = DB::table('promotion_claims')
            ->where('promotion_id', $promotion->id)
            ->where('driver_id', $driver->id)
            ->first();

        $canClaim = !$claim &&
                   ($promotion->max_claims === null || $promotion->current_claims < $promotion->max_claims) &&
                   ($promotion->expires_at === null || $promotion->expires_at > now());

        return response()->json(responseFormatter(DEFAULT_200, [
            'id' => $promotion->id,
            'title' => $promotion->title,
            'description' => $promotion->description,
            'terms_conditions' => $promotion->terms_conditions,
            'image_url' => $promotion->image_url ? asset('storage/' . $promotion->image_url) : null,
            'action_type' => $promotion->action_type,
            'action_url' => $promotion->action_url,
            'priority' => $promotion->priority,
            'starts_at' => $promotion->starts_at,
            'expires_at' => $promotion->expires_at,
            'max_claims' => $promotion->max_claims,
            'current_claims' => $promotion->current_claims,
            'is_claimed' => (bool) $claim,
            'claimed_at' => $claim?->claimed_at,
            'claim_status' => $claim?->status,
            'can_claim' => $canClaim,
        ]));
    }

    /**
     * Claim a promotion
     * POST /api/driver/auth/promotions/{id}/claim
     */
    public function claim(string $id): JsonResponse
    {
        $driver = auth('api')->user();

        $promotion = DB::table('driver_promotions')
            ->where('id', $id)
            ->where('is_active', true)
            ->where(function($q) use ($driver) {
                $q->whereNull('target_driver_id')
                  ->orWhere('target_driver_id', $driver->id);
            })
            ->first();

        if (!$promotion) {
            return response()->json(responseFormatter([
                'response_code' => 'promotion_not_found_404',
                'message' => translate('Promotion not found or not available'),
            ]), 404);
        }

        // Check if expired
        if ($promotion->expires_at && $promotion->expires_at < now()) {
            return response()->json(responseFormatter([
                'response_code' => 'promotion_expired_400',
                'message' => translate('This promotion has expired'),
            ]), 400);
        }

        // Check if not started
        if ($promotion->starts_at && $promotion->starts_at > now()) {
            return response()->json(responseFormatter([
                'response_code' => 'promotion_not_started_400',
                'message' => translate('This promotion has not started yet'),
            ]), 400);
        }

        // Check if already claimed
        $existingClaim = DB::table('promotion_claims')
            ->where('promotion_id', $promotion->id)
            ->where('driver_id', $driver->id)
            ->first();

        if ($existingClaim) {
            return response()->json(responseFormatter([
                'response_code' => 'already_claimed_400',
                'message' => translate('You have already claimed this promotion'),
                'data' => [
                    'claimed_at' => $existingClaim->claimed_at,
                    'status' => $existingClaim->status,
                ],
            ]), 400);
        }

        // Check max claims
        if ($promotion->max_claims && $promotion->current_claims >= $promotion->max_claims) {
            return response()->json(responseFormatter([
                'response_code' => 'max_claims_reached_400',
                'message' => translate('This promotion has reached maximum claims'),
            ]), 400);
        }

        // Create claim
        $claimId = DB::table('promotion_claims')->insertGetId([
            'id' => \Illuminate\Support\Str::uuid(),
            'promotion_id' => $promotion->id,
            'driver_id' => $driver->id,
            'status' => 'claimed',
            'claimed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Increment current claims
        DB::table('driver_promotions')
            ->where('id', $promotion->id)
            ->increment('current_claims');

        // Send notification
        DriverNotification::notify(
            $driver->id,
            'promotion',
            translate('Promotion Claimed'),
            translate('You have successfully claimed: :title', ['title' => $promotion->title]),
            ['promotion_id' => $promotion->id],
            'normal',
            'promotions'
        );

        return response()->json(responseFormatter([
            'response_code' => 'promotion_claimed_200',
            'message' => translate('Promotion claimed successfully'),
            'data' => [
                'claim_id' => $claimId,
                'promotion_title' => $promotion->title,
                'claimed_at' => now()->toIso8601String(),
            ],
        ]));
    }
}
