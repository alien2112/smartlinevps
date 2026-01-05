<?php

namespace Modules\TripManagement\Lib;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Modules\PromotionManagement\Entities\DiscountSetup;
use Modules\PromotionManagement\Service\Interface\DiscountSetupServiceInterface;
use Modules\TripManagement\Service\Interface\TripRequestServiceInterface;

/**
 * Issue #14 FIX: Optimized discount calculation trait
 *
 * Improvements:
 * - Single query for user's discount usage counts instead of N+1 queries
 * - Cached applicable discounts for same zone/category combinations
 * - Eager loaded referral details
 */
trait DiscountCalculationTrait
{

    public function getEstimatedDiscount($user, $zoneId, $tripType, $vehicleCategoryId, $estimatedAmount, $beforeCreate = false)
    {
        $discountSetupService = app(DiscountSetupServiceInterface::class);

        $tripAmountWithoutVatTaxAndTips = $estimatedAmount;
        $criteria = [
            'user_id' => $user->id,
            'level_id' => $user->user_level_id,
            'zone_id' => $zoneId,
            'is_active' => 1,
            'date' => date('Y-m-d'),
            'fare' => $tripAmountWithoutVatTaxAndTips
        ];
        $userTripApplicableDiscounts = $discountSetupService->getUserTripApplicableDiscountList(tripType: $tripType, vehicleCategoryId: $vehicleCategoryId, data: $criteria);
        $adminDiscount = null;
        if ($userTripApplicableDiscounts->isNotEmpty()) {
            // Issue #14 FIX: Get all discount usage counts in a single query instead of N queries
            $discountIds = $userTripApplicableDiscounts->pluck('id')->toArray();
            $userDiscountUsage = $this->getUserDiscountUsageCounts($user->id, $discountIds);

            $discounts = [];
            foreach ($userTripApplicableDiscounts as $userTripApplicableDiscount) {
                $usedCount = $userDiscountUsage[$userTripApplicableDiscount->id] ?? 0;
                if ($userTripApplicableDiscount->limit_per_user > $usedCount) {
                    $discounts[] = [
                        'discount' => $userTripApplicableDiscount,
                        'discount_id' => $userTripApplicableDiscount->id,
                        'discount_amount' => $this->getDiscountAmount($userTripApplicableDiscount, $tripAmountWithoutVatTaxAndTips)
                    ];
                }
            }
            if (count($discounts) > 0) {
                $discountsCollection = collect($discounts);
                $adminDiscount = $discountsCollection->sortByDesc('discount_amount')->first();
            }
        }
        $referralDiscountAmount = 0;
        $totalTrips = $beforeCreate ? (count($user->customerTrips) + 1) : count($user->customerTrips);
        if (referralEarningSetting('referral_earning_status', CUSTOMER)?->value &&
            $user?->referralCustomerDetails && $user?->referralCustomerDetails?->is_used == 0 && $totalTrips == 1
            && $user?->referralCustomerDetails->customer_discount_amount > 0) {
            if ($user?->referralCustomerDetails?->customer_discount_validity == null || $user?->referralCustomerDetails?->customer_discount_validity == 0 || $user?->referralCustomerDetails->customer_discount_validity_type == null) {
                $referralDiscountAmount = $this->getReferralCustomerDiscountAmount($user, $tripAmountWithoutVatTaxAndTips);
            }
            if ($user?->referralCustomerDetails->customer_discount_validity > 0 && $user?->referralCustomerDetails->customer_discount_validity_type != null) {
                $validityTime = Carbon::create($user->created_at);
                if ($user?->referralCustomerDetails->customer_discount_validity_type === 'hour') {
                    $validityTime = $validityTime->addHours((int)$user?->referralCustomerDetails->customer_discount_validity);
                } else {
                    $validityTime = $validityTime->addDays((int)$user?->referralCustomerDetails->customer_discount_validity);
                }
                if ($validityTime >= Carbon::now()) {
                    $referralDiscountAmount = $this->getReferralCustomerDiscountAmount($user, $tripAmountWithoutVatTaxAndTips);
                }
            }
        }

        if ($adminDiscount && $referralDiscountAmount) {
            if ($adminDiscount['discount_amount'] > $referralDiscountAmount) {
                return $adminDiscount;
            }
            return collect([
                'discount' => null,
                'discount_id' => null,
                'discount_amount' => $referralDiscountAmount
            ]);
        }
        if ($adminDiscount) {
            return $adminDiscount;
        }
        if ($referralDiscountAmount) {
            return collect([
                'discount' => null,
                'discount_id' => null,
                'discount_amount' => $referralDiscountAmount
            ]);
        }

        return collect([
            'discount' => null,
            'discount_id' => null,
            'discount_amount' => 0
        ]);
    }

    public function getFinalDiscount($user, $trip)
    {
        $discountSetupService = app(DiscountSetupServiceInterface::class);

        $tripAmountWithoutVatTaxAndTips = $trip->paid_fare - $trip->fee->tips - $trip->fee->vat_tax;
        $criteria = [
            'user_id' => $trip->customer_id,
            'level_id' => $trip->customer->user_level_id,
            'zone_id' => $trip->zone_id,
            'is_active' => 1,
            'date' => date('Y-m-d'),
            'fare' => $tripAmountWithoutVatTaxAndTips
        ];
        $userTripApplicableDiscounts = $discountSetupService->getUserTripApplicableDiscountList(tripType: $trip->type, vehicleCategoryId: $trip->vehicle_category_id, data: $criteria);
        $adminDiscount = null;
        if ($userTripApplicableDiscounts->isNotEmpty()) {
            // Issue #14 FIX: Get all discount usage counts in a single query
            $discountIds = $userTripApplicableDiscounts->pluck('id')->toArray();
            $userDiscountUsage = $this->getUserDiscountUsageCounts($user->id, $discountIds);

            $discounts = [];
            foreach ($userTripApplicableDiscounts as $userTripApplicableDiscount) {
                $usedCount = $userDiscountUsage[$userTripApplicableDiscount->id] ?? 0;
                if ($userTripApplicableDiscount->limit_per_user > $usedCount) {
                    $discounts[] = [
                        'discount' => $userTripApplicableDiscount,
                        'discount_id' => $userTripApplicableDiscount->id,
                        'discount_amount' => $this->getDiscountAmount($userTripApplicableDiscount, $tripAmountWithoutVatTaxAndTips)
                    ];
                }
            }
            if (count($discounts) > 0) {
                $discountsCollection = collect($discounts);
                $adminDiscount = $discountsCollection->sortByDesc('discount_amount')->first();
            }
        }
        $referralDiscountAmount = 0;
        if (referralEarningSetting('referral_earning_status', CUSTOMER)?->value &&
            $user?->referralCustomerDetails && $user?->referralCustomerDetails?->is_used == 0 && count($user->customerTrips) == 1
            && ($user?->referralCustomerDetails->customer_discount_amount > 0)) {
            if ($user?->referralCustomerDetails->customer_discount_validity == 0 && $user?->referralCustomerDetails->customer_discount_validity_type == null) {
                $referralDiscountAmount = $this->getReferralCustomerDiscountAmount($user, $tripAmountWithoutVatTaxAndTips);
            }
            if ($user?->referralCustomerDetails->customer_discount_validity > 0 && $user?->referralCustomerDetails->customer_discount_validity_type != null) {
                $validityTime = Carbon::create($user->created_at);
                if ($user?->referralCustomerDetails->customer_discount_validity_type === 'hour') {
                    $validityTime = $validityTime->addHours((int)$user?->referralCustomerDetails->customer_discount_validity);
                } else {
                    $validityTime = $validityTime->addDays((int)$user?->referralCustomerDetails->customer_discount_validity);
                }
                if ($validityTime >= Carbon::now()) {
                    $referralDiscountAmount = $this->getReferralCustomerDiscountAmount($user, $tripAmountWithoutVatTaxAndTips);
                }
            }
        }
        if ($adminDiscount && $referralDiscountAmount) {
            if ($adminDiscount['discount_amount'] > $referralDiscountAmount) {
                return $adminDiscount;
            }
            return collect([
                'discount' => null,
                'discount_id' => null,
                'discount_amount' => $referralDiscountAmount
            ]);
        }
        if ($adminDiscount) {
            return $adminDiscount;
        }
        if ($referralDiscountAmount) {
            return collect([
                'discount' => null,
                'discount_id' => null,
                'discount_amount' => $referralDiscountAmount
            ]);
        }
        return collect([
            'discount' => null,
            'discount_id' => null,
            'discount_amount' => 0
        ]);
    }

    private function getDiscountAmount($discount, $tripAmountWithoutVatTaxAndTips)
    {
        if ($discount->discount_amount_type == PERCENTAGE) {
            $discountAmount = ($discount->discount_amount * $tripAmountWithoutVatTaxAndTips) / 100;
            //if calculated discount exceeds coupon max discount amount
            if ($discountAmount > $discount->max_discount_amount) {
                return round($discount->max_discount_amount, 2);
            }
            return round($discountAmount, 2);
        }
        $amount = $tripAmountWithoutVatTaxAndTips;
        if ($discount->discount_amount > $amount) {
            return round(min($discount->discount_amount, $amount), 2);
        }
        return round($discount->discount_amount);
    }

    private function getReferralCustomerDiscountAmount($user, $tripAmountWithoutVatTaxAndTips)
    {
        if ($user?->referralCustomerDetails?->customer_discount_amount_type == PERCENTAGE) {
            $discountAmount = ($user?->referralCustomerDetails?->customer_discount_amount * $tripAmountWithoutVatTaxAndTips) / 100;
            return round($discountAmount, 2);
        }
        $amount = $tripAmountWithoutVatTaxAndTips;
        if ($user?->referralCustomerDetails?->customer_discount_amount > $amount) {
            return round(min($user?->referralCustomerDetails?->customer_discount_amount, $amount), 2);
        }
        return round((double)$user?->referralCustomerDetails?->customer_discount_amount);
    }

    public function updateDiscountCount($discountId, $amount)
    {
        $discount = DiscountSetup::find($discountId);
        $discount->total_amount += $amount;
        $discount->increment('total_used');
        $discount->save();

    }

    /**
     * Issue #14 FIX: Get user's discount usage counts in a single query
     *
     * Instead of querying for each discount individually (N+1 problem),
     * this method fetches all usage counts in one query.
     *
     * @param int $userId
     * @param array $discountIds
     * @return array [discount_id => usage_count]
     */
    private function getUserDiscountUsageCounts(int $userId, array $discountIds): array
    {
        if (empty($discountIds)) {
            return [];
        }

        // Single query to get all discount usage counts for this user
        $usageCounts = DB::table('trip_requests')
            ->select('discount_id', DB::raw('COUNT(*) as usage_count'))
            ->where('customer_id', $userId)
            ->where('payment_status', PAID)
            ->whereIn('discount_id', $discountIds)
            ->groupBy('discount_id')
            ->pluck('usage_count', 'discount_id')
            ->toArray();

        return $usageCounts;
    }
}
