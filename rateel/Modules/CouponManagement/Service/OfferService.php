<?php

declare(strict_types=1);

namespace Modules\CouponManagement\Service;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\CouponManagement\Entities\Offer;
use Modules\CouponManagement\Entities\OfferUsage;
use Modules\UserManagement\Entities\User;

class OfferService
{
    /**
     * Get all applicable offers for a user and trip context
     *
     * @param User $user
     * @param array $context ['zone_id', 'trip_type', 'vehicle_category_id', 'fare']
     * @return Collection
     */
    public function getApplicableOffers(User $user, array $context): Collection
    {
        $logContext = [
            'user_id' => $user->id,
            'context' => $context,
        ];

        Log::debug('OfferService: Getting applicable offers', $logContext);

        // Get all active, valid offers
        $offers = Offer::active()
            ->valid()
            ->byPriority()
            ->get();

        if ($offers->isEmpty()) {
            Log::debug('OfferService: No active offers found');
            return collect();
        }

        // Get user's usage counts for all offers in one query
        $offerIds = $offers->pluck('id')->toArray();
        $userUsageCounts = $this->getUserOfferUsageCounts($user->id, $offerIds);

        // Filter applicable offers
        $applicableOffers = $offers->filter(function ($offer) use ($user, $context, $userUsageCounts) {
            return $this->isOfferApplicable($offer, $user, $context, $userUsageCounts);
        });

        Log::debug('OfferService: Found applicable offers', [
            'user_id' => $user->id,
            'count' => $applicableOffers->count(),
        ]);

        return $applicableOffers->values();
    }

    /**
     * Get the best offer for a user and trip context
     *
     * @param User $user
     * @param array $context ['zone_id', 'trip_type', 'vehicle_category_id', 'fare']
     * @return array|null ['offer' => Offer, 'discount_amount' => float]
     */
    public function getBestOffer(User $user, array $context): ?array
    {
        $applicableOffers = $this->getApplicableOffers($user, $context);

        if ($applicableOffers->isEmpty()) {
            return null;
        }

        $fare = (float) ($context['fare'] ?? 0);
        
        // Calculate discount for each offer and get the best one
        $bestOffer = null;
        $bestDiscount = 0;

        foreach ($applicableOffers as $offer) {
            $discount = $offer->calculateDiscount($fare);
            if ($discount > $bestDiscount) {
                $bestDiscount = $discount;
                $bestOffer = $offer;
            }
        }

        if (!$bestOffer) {
            return null;
        }

        Log::info('OfferService: Best offer found', [
            'user_id' => $user->id,
            'offer_id' => $bestOffer->id,
            'discount' => $bestDiscount,
        ]);

        return [
            'offer' => $bestOffer,
            'offer_id' => $bestOffer->id,
            'discount_amount' => $bestDiscount,
            'original_fare' => $fare,
            'final_fare' => $fare - $bestDiscount,
        ];
    }

    /**
     * Apply offer to a trip (atomic operation)
     *
     * @param User $user
     * @param string $offerId
     * @param string $tripId
     * @param float $fare
     * @return array ['success' => bool, 'usage' => ?OfferUsage, 'error' => ?string]
     */
    public function applyOffer(User $user, string $offerId, string $tripId, float $fare): array
    {
        $logContext = [
            'user_id' => $user->id,
            'offer_id' => $offerId,
            'trip_id' => $tripId,
            'fare' => $fare,
        ];

        Log::info('OfferService: Applying offer', $logContext);

        try {
            return DB::transaction(function () use ($user, $offerId, $tripId, $fare, $logContext) {
                // Lock the offer row
                $offer = Offer::where('id', $offerId)->lockForUpdate()->first();

                if (!$offer) {
                    return ['success' => false, 'usage' => null, 'error' => 'Offer not found'];
                }

                // Validate offer is still applicable
                if (!$offer->is_active) {
                    return ['success' => false, 'usage' => null, 'error' => 'Offer is inactive'];
                }

                if (!$offer->isWithinValidPeriod()) {
                    return ['success' => false, 'usage' => null, 'error' => 'Offer has expired'];
                }

                if ($offer->isGlobalLimitReached()) {
                    return ['success' => false, 'usage' => null, 'error' => 'Offer limit reached'];
                }

                if ($offer->isUserLimitReached($user->id)) {
                    return ['success' => false, 'usage' => null, 'error' => 'User limit reached'];
                }

                if ($fare < $offer->min_trip_amount) {
                    return ['success' => false, 'usage' => null, 'error' => 'Minimum fare not met'];
                }

                // Calculate discount
                $discountAmount = $offer->calculateDiscount($fare);

                // Create usage record
                $usage = OfferUsage::create([
                    'offer_id' => $offer->id,
                    'user_id' => $user->id,
                    'trip_id' => $tripId,
                    'original_fare' => $fare,
                    'discount_amount' => $discountAmount,
                    'final_fare' => $fare - $discountAmount,
                    'status' => OfferUsage::STATUS_APPLIED,
                ]);

                // Update offer stats
                $offer->incrementUsage($discountAmount);

                Log::info('OfferService: Offer applied successfully', array_merge($logContext, [
                    'usage_id' => $usage->id,
                    'discount' => $discountAmount,
                ]));

                return [
                    'success' => true,
                    'usage' => $usage,
                    'discount_amount' => $discountAmount,
                    'final_fare' => $fare - $discountAmount,
                    'error' => null,
                ];
            }, 3); // 3 retry attempts

        } catch (\Exception $e) {
            Log::error('OfferService: Error applying offer', array_merge($logContext, [
                'error' => $e->getMessage(),
            ]));

            return [
                'success' => false,
                'usage' => null,
                'error' => 'Failed to apply offer: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Cancel offer usage (e.g., when trip is cancelled)
     *
     * @param string $tripId
     * @return bool
     */
    public function cancelOfferUsage(string $tripId): bool
    {
        $usage = OfferUsage::forTrip($tripId)->applied()->first();

        if (!$usage) {
            return true; // No usage to cancel
        }

        try {
            DB::transaction(function () use ($usage) {
                $usage->markCancelled();

                // Decrement offer stats
                $offer = $usage->offer;
                if ($offer) {
                    $offer->decrement('total_used');
                    $offer->decrement('total_discount_given', $usage->discount_amount);
                }
            });

            Log::info('OfferService: Offer usage cancelled', [
                'usage_id' => $usage->id,
                'trip_id' => $tripId,
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('OfferService: Error cancelling offer usage', [
                'trip_id' => $tripId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get offers visible in app for a user
     *
     * @param User $user
     * @return Collection
     */
    public function getOffersForApp(User $user): Collection
    {
        $offers = Offer::active()
            ->valid()
            ->showInApp()
            ->byPriority()
            ->get();

        // Filter to only show offers user can potentially use
        return $offers->filter(function ($offer) use ($user) {
            // Check customer targeting
            if (!$offer->isCustomerAllowed($user->id)) {
                return false;
            }

            // Check customer level
            if (!$offer->isCustomerLevelAllowed($user->user_level_id)) {
                return false;
            }

            // Check user limit
            if ($offer->isUserLimitReached($user->id)) {
                return false;
            }

            return true;
        })->values();
    }

    /**
     * Get offer statistics for admin
     *
     * @param string $offerId
     * @return array
     */
    public function getOfferStats(string $offerId): array
    {
        $offer = Offer::findOrFail($offerId);

        $usages = OfferUsage::where('offer_id', $offerId);

        return [
            'offer' => [
                'id' => $offer->id,
                'title' => $offer->title,
                'discount_type' => $offer->discount_type,
                'discount_amount' => $offer->discount_amount,
                'is_active' => $offer->is_active,
                'status' => $offer->status,
                'start_date' => $offer->start_date->toIso8601String(),
                'end_date' => $offer->end_date->toIso8601String(),
            ],
            'limits' => [
                'global_limit' => $offer->global_limit,
                'total_used' => $offer->total_used,
                'remaining' => $offer->getRemainingUses(),
                'limit_per_user' => $offer->limit_per_user,
            ],
            'usage' => [
                'total_usages' => (clone $usages)->count(),
                'applied' => (clone $usages)->applied()->count(),
                'cancelled' => (clone $usages)->where('status', 'cancelled')->count(),
                'refunded' => (clone $usages)->where('status', 'refunded')->count(),
                'unique_users' => (clone $usages)->distinct('user_id')->count('user_id'),
            ],
            'financials' => [
                'total_discount_given' => $offer->total_discount_given,
                'average_discount' => (clone $usages)->applied()->avg('discount_amount'),
                'total_original_fare' => (clone $usages)->applied()->sum('original_fare'),
                'total_final_fare' => (clone $usages)->applied()->sum('final_fare'),
            ],
            'daily_stats' => OfferUsage::where('offer_id', $offerId)
                ->applied()
                ->selectRaw('DATE(created_at) as date, COUNT(*) as count, SUM(discount_amount) as discount')
                ->groupBy('date')
                ->orderBy('date', 'desc')
                ->limit(30)
                ->get(),
        ];
    }

    /**
     * Check if offer is applicable for user and context
     */
    private function isOfferApplicable(Offer $offer, User $user, array $context, array $userUsageCounts): bool
    {
        // Check global limit
        if ($offer->isGlobalLimitReached()) {
            return false;
        }

        // Check user limit
        $userUsed = $userUsageCounts[$offer->id] ?? 0;
        if ($userUsed >= $offer->limit_per_user) {
            return false;
        }

        // Check customer targeting
        if (!$offer->isCustomerAllowed($user->id)) {
            return false;
        }

        // Check customer level
        if (!$offer->isCustomerLevelAllowed($user->user_level_id)) {
            return false;
        }

        // Check zone
        $zoneId = $context['zone_id'] ?? null;
        if (!$offer->isZoneAllowed($zoneId)) {
            return false;
        }

        // Check service type
        $tripType = $context['trip_type'] ?? 'ride_request';
        $vehicleCategoryId = $context['vehicle_category_id'] ?? null;
        if (!$offer->isServiceAllowed($tripType, $vehicleCategoryId)) {
            return false;
        }

        // Check minimum fare
        $fare = (float) ($context['fare'] ?? 0);
        if ($fare < $offer->min_trip_amount) {
            return false;
        }

        return true;
    }

    /**
     * Get user's offer usage counts in a single query
     */
    private function getUserOfferUsageCounts(string $userId, array $offerIds): array
    {
        if (empty($offerIds)) {
            return [];
        }

        return DB::table('offer_usages')
            ->select('offer_id', DB::raw('COUNT(*) as usage_count'))
            ->where('user_id', $userId)
            ->where('status', 'applied')
            ->whereIn('offer_id', $offerIds)
            ->groupBy('offer_id')
            ->pluck('usage_count', 'offer_id')
            ->toArray();
    }

    /**
     * Deactivate expired offers (called by scheduler)
     */
    public function deactivateExpiredOffers(): int
    {
        $count = Offer::where('is_active', true)
            ->where('end_date', '<', now())
            ->update(['is_active' => false]);

        if ($count > 0) {
            Log::info('OfferService: Deactivated expired offers', ['count' => $count]);
        }

        return $count;
    }
}
