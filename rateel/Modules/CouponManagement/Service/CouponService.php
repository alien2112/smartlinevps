<?php

declare(strict_types=1);

namespace Modules\CouponManagement\Service;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\CouponManagement\Entities\Coupon;
use Modules\CouponManagement\Entities\CouponRedemption;
use Modules\CouponManagement\Entities\CouponTargetUser;
use Modules\CouponManagement\Enums\CouponErrorCode;
use Modules\TripManagement\Entities\TripRequest;
use Modules\UserManagement\Entities\User;

class CouponService
{
    /**
     * Reservation expiry time in minutes
     */
    private const RESERVATION_EXPIRY_MINUTES = 30;

    /**
     * Validation result DTO
     */
    public function createValidationResult(
        bool $valid,
        ?CouponErrorCode $errorCode = null,
        float $discountAmount = 0.0,
        ?Coupon $coupon = null,
        array $meta = []
    ): array {
        return [
            'valid' => $valid,
            'error_code' => $errorCode?->value,
            'error_message' => $errorCode?->message(),
            'discount_amount' => round($discountAmount, 2),
            'coupon' => $coupon ? [
                'id' => $coupon->id,
                'code' => $coupon->code,
                'name' => $coupon->name,
                'type' => $coupon->type,
                'value' => $coupon->value,
                'max_discount' => $coupon->max_discount,
            ] : null,
            'meta' => $meta,
        ];
    }

    /**
     * Validate a coupon for a user and estimated ride context
     *
     * @param User $user
     * @param string $code
     * @param array $estimateContext ['fare' => float, 'city_id' => string, 'service_type' => string]
     * @return array
     */
    public function validateCoupon(User $user, string $code, array $estimateContext): array
    {
        $logContext = [
            'user_id' => $user->id,
            'coupon_code' => $code,
            'context' => $estimateContext,
        ];

        Log::info('CouponService: Validating coupon', $logContext);

        // Find coupon by code
        $coupon = Coupon::byCode($code)->first();

        if (!$coupon) {
            Log::warning('CouponService: Coupon not found', $logContext);
            return $this->createValidationResult(false, CouponErrorCode::COUPON_NOT_FOUND);
        }

        $logContext['coupon_id'] = $coupon->id;

        // Check if active
        if (!$coupon->is_active) {
            Log::info('CouponService: Coupon inactive', $logContext);
            return $this->createValidationResult(false, CouponErrorCode::COUPON_INACTIVE, coupon: $coupon);
        }

        // Check date validity
        $now = now();
        if ($now->lt($coupon->starts_at)) {
            Log::info('CouponService: Coupon not started', $logContext);
            return $this->createValidationResult(false, CouponErrorCode::COUPON_NOT_STARTED, coupon: $coupon);
        }

        if ($now->gt($coupon->ends_at)) {
            Log::info('CouponService: Coupon expired', $logContext);
            return $this->createValidationResult(false, CouponErrorCode::COUPON_EXPIRED, coupon: $coupon);
        }

        // Check global limit
        if ($coupon->isGlobalLimitReached()) {
            Log::info('CouponService: Global limit reached', $logContext);
            return $this->createValidationResult(false, CouponErrorCode::GLOBAL_LIMIT_REACHED, coupon: $coupon);
        }

        // Check user limit
        if ($coupon->isUserLimitReached($user->id)) {
            Log::info('CouponService: User limit reached', $logContext);
            return $this->createValidationResult(false, CouponErrorCode::USER_LIMIT_REACHED, coupon: $coupon);
        }

        // Check eligibility
        $eligibilityResult = $this->checkEligibility($coupon, $user);
        if (!$eligibilityResult['eligible']) {
            Log::info('CouponService: User not eligible', array_merge($logContext, ['reason' => $eligibilityResult['error_code']->value]));
            return $this->createValidationResult(false, $eligibilityResult['error_code'], coupon: $coupon);
        }

        // Check scope (city and service type)
        $cityId = $estimateContext['city_id'] ?? null;
        $serviceType = $estimateContext['service_type'] ?? null;

        if (!$coupon->isCityAllowed($cityId)) {
            Log::info('CouponService: City not allowed', $logContext);
            return $this->createValidationResult(false, CouponErrorCode::CITY_NOT_ALLOWED, coupon: $coupon);
        }

        if (!$coupon->isServiceTypeAllowed($serviceType)) {
            Log::info('CouponService: Service type not allowed', $logContext);
            return $this->createValidationResult(false, CouponErrorCode::SERVICE_TYPE_NOT_ALLOWED, coupon: $coupon);
        }

        // Check minimum fare
        $fare = (float) ($estimateContext['fare'] ?? 0);
        if ($fare < $coupon->min_fare) {
            Log::info('CouponService: Min fare not met', array_merge($logContext, ['fare' => $fare, 'min_fare' => $coupon->min_fare]));
            return $this->createValidationResult(
                false,
                CouponErrorCode::MIN_FARE_NOT_MET,
                coupon: $coupon,
                meta: ['min_fare' => $coupon->min_fare, 'current_fare' => $fare]
            );
        }

        // Calculate discount
        $discountAmount = $coupon->calculateDiscount($fare);

        Log::info('CouponService: Coupon valid', array_merge($logContext, ['discount_amount' => $discountAmount]));

        return $this->createValidationResult(
            true,
            null,
            $discountAmount,
            $coupon,
            [
                'original_fare' => $fare,
                'discounted_fare' => $fare - $discountAmount,
            ]
        );
    }

    /**
     * Check user eligibility based on coupon eligibility type
     */
    private function checkEligibility(Coupon $coupon, User $user): array
    {
        return match ($coupon->eligibility_type) {
            Coupon::ELIGIBILITY_ALL => ['eligible' => true, 'error_code' => null],
            Coupon::ELIGIBILITY_TARGETED => $this->checkTargetedEligibility($coupon, $user),
            Coupon::ELIGIBILITY_SEGMENT => $this->checkSegmentEligibility($coupon, $user),
            default => ['eligible' => false, 'error_code' => CouponErrorCode::NOT_ELIGIBLE],
        };
    }

    /**
     * Check if user is in target list
     */
    private function checkTargetedEligibility(Coupon $coupon, User $user): array
    {
        $isTargeted = CouponTargetUser::where('coupon_id', $coupon->id)
            ->where('user_id', $user->id)
            ->exists();

        return $isTargeted
            ? ['eligible' => true, 'error_code' => null]
            : ['eligible' => false, 'error_code' => CouponErrorCode::NOT_IN_TARGET_LIST];
    }

    /**
     * Check segment-based eligibility
     */
    private function checkSegmentEligibility(Coupon $coupon, User $user): array
    {
        $eligible = match ($coupon->segment_key) {
            Coupon::SEGMENT_INACTIVE_30_DAYS => $this->isInactive30Days($user),
            Coupon::SEGMENT_NEW_USER => $this->isNewUser($user),
            Coupon::SEGMENT_HIGH_VALUE => $this->isHighValueUser($user),
            default => false,
        };

        return $eligible
            ? ['eligible' => true, 'error_code' => null]
            : ['eligible' => false, 'error_code' => CouponErrorCode::SEGMENT_NOT_MATCHED];
    }

    /**
     * Check if user has been inactive for 30 days
     */
    private function isInactive30Days(User $user): bool
    {
        // Check last_ride_at on user if available
        if (isset($user->last_ride_at)) {
            return $user->last_ride_at === null || $user->last_ride_at->lt(now()->subDays(30));
        }

        // Otherwise check rides table
        $lastRide = TripRequest::where('customer_id', $user->id)
            ->where('current_status', 'completed')
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$lastRide) {
            return true; // Never had a ride = inactive
        }

        return $lastRide->created_at->lt(now()->subDays(30));
    }

    /**
     * Check if user is new (registered in last 7 days)
     */
    private function isNewUser(User $user): bool
    {
        return $user->created_at->gt(now()->subDays(7));
    }

    /**
     * Check if user is high value (10+ completed rides)
     */
    private function isHighValueUser(User $user): bool
    {
        $completedRides = TripRequest::where('customer_id', $user->id)
            ->where('current_status', 'completed')
            ->count();

        return $completedRides >= 10;
    }

    /**
     * Reserve a coupon for a ride (atomic, idempotent)
     *
     * @param User $user
     * @param string $code
     * @param string $rideId
     * @param string $idempotencyKey
     * @param array $estimateContext
     * @return array ['success' => bool, 'redemption' => ?CouponRedemption, 'error_code' => ?string, 'error_message' => ?string]
     */
    public function reserveCoupon(
        User $user,
        string $code,
        string $rideId,
        string $idempotencyKey,
        array $estimateContext
    ): array {
        $logContext = [
            'user_id' => $user->id,
            'coupon_code' => $code,
            'ride_id' => $rideId,
            'idempotency_key' => $idempotencyKey,
        ];

        Log::info('CouponService: Reserving coupon', $logContext);

        // Check for existing reservation with same idempotency key (idempotent behavior)
        $existingRedemption = CouponRedemption::where('user_id', $user->id)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existingRedemption) {
            Log::info('CouponService: Returning existing reservation (idempotent)', array_merge($logContext, ['redemption_id' => $existingRedemption->id]));
            return [
                'success' => true,
                'redemption' => $existingRedemption,
                'error_code' => null,
                'error_message' => null,
                'idempotent' => true,
            ];
        }

        // Check if ride already has a coupon
        $existingRideRedemption = CouponRedemption::forRide($rideId)->active()->first();
        if ($existingRideRedemption) {
            Log::warning('CouponService: Ride already has coupon', $logContext);
            return [
                'success' => false,
                'redemption' => null,
                'error_code' => CouponErrorCode::RIDE_ALREADY_HAS_COUPON->value,
                'error_message' => CouponErrorCode::RIDE_ALREADY_HAS_COUPON->message(),
            ];
        }

        // Validate coupon first
        $validationResult = $this->validateCoupon($user, $code, $estimateContext);

        if (!$validationResult['valid']) {
            Log::info('CouponService: Validation failed during reservation', array_merge($logContext, ['error' => $validationResult['error_code']]));
            return [
                'success' => false,
                'redemption' => null,
                'error_code' => $validationResult['error_code'],
                'error_message' => $validationResult['error_message'],
            ];
        }

        $coupon = Coupon::byCode($code)->first();

        // Use database transaction with locking for atomic operation
        try {
            $redemption = DB::transaction(function () use ($coupon, $user, $rideId, $idempotencyKey, $estimateContext, $validationResult) {
                // Lock the coupon row for update to prevent race conditions
                $lockedCoupon = Coupon::where('id', $coupon->id)->lockForUpdate()->first();

                // Re-check limits after locking
                if ($lockedCoupon->isGlobalLimitReached()) {
                    throw new \Exception(CouponErrorCode::GLOBAL_LIMIT_REACHED->value);
                }

                if ($lockedCoupon->isUserLimitReached($user->id)) {
                    throw new \Exception(CouponErrorCode::USER_LIMIT_REACHED->value);
                }

                // Create redemption record
                $redemption = CouponRedemption::create([
                    'coupon_id' => $lockedCoupon->id,
                    'user_id' => $user->id,
                    'ride_id' => $rideId,
                    'idempotency_key' => $idempotencyKey,
                    'status' => CouponRedemption::STATUS_RESERVED,
                    'estimated_fare' => $estimateContext['fare'] ?? null,
                    'estimated_discount' => $validationResult['discount_amount'],
                    'city_id' => $estimateContext['city_id'] ?? null,
                    'service_type' => $estimateContext['service_type'] ?? null,
                    'reserved_at' => now(),
                    'expires_at' => now()->addMinutes(self::RESERVATION_EXPIRY_MINUTES),
                ]);

                // Increment global used count atomically
                $lockedCoupon->incrementUsedCount();

                return $redemption;
            }, 3); // 3 retry attempts for deadlocks

            Log::info('CouponService: Coupon reserved successfully', array_merge($logContext, [
                'redemption_id' => $redemption->id,
                'coupon_id' => $coupon->id,
                'estimated_discount' => $validationResult['discount_amount'],
            ]));

            return [
                'success' => true,
                'redemption' => $redemption,
                'error_code' => null,
                'error_message' => null,
                'idempotent' => false,
            ];

        } catch (\Illuminate\Database\QueryException $e) {
            // Handle unique constraint violation (race condition)
            if ($e->getCode() === '23000') {
                Log::warning('CouponService: Concurrency conflict during reservation', array_merge($logContext, ['error' => $e->getMessage()]));
                return [
                    'success' => false,
                    'redemption' => null,
                    'error_code' => CouponErrorCode::CONCURRENCY_CONFLICT->value,
                    'error_message' => CouponErrorCode::CONCURRENCY_CONFLICT->message(),
                ];
            }
            throw $e;
        } catch (\Exception $e) {
            // Handle validation errors from within transaction
            $errorCode = CouponErrorCode::tryFrom($e->getMessage());
            if ($errorCode) {
                Log::info('CouponService: Reservation failed', array_merge($logContext, ['error' => $errorCode->value]));
                return [
                    'success' => false,
                    'redemption' => null,
                    'error_code' => $errorCode->value,
                    'error_message' => $errorCode->message(),
                ];
            }

            Log::error('CouponService: Unexpected error during reservation', array_merge($logContext, ['error' => $e->getMessage()]));
            return [
                'success' => false,
                'redemption' => null,
                'error_code' => CouponErrorCode::INTERNAL_ERROR->value,
                'error_message' => CouponErrorCode::INTERNAL_ERROR->message(),
            ];
        }
    }

    /**
     * Apply coupon on ride completion (recompute with final fare)
     *
     * @param User $user
     * @param string $rideId
     * @return array ['success' => bool, 'discount_amount' => float, 'error_code' => ?string]
     */
    public function applyCoupon(User $user, string $rideId): array
    {
        $logContext = [
            'user_id' => $user->id,
            'ride_id' => $rideId,
        ];

        Log::info('CouponService: Applying coupon', $logContext);

        // Find reserved redemption for this ride
        $redemption = CouponRedemption::forRide($rideId)
            ->where('user_id', $user->id)
            ->reserved()
            ->with('coupon')
            ->first();

        if (!$redemption) {
            Log::info('CouponService: No reservation found', $logContext);
            return [
                'success' => false,
                'discount_amount' => 0.0,
                'error_code' => CouponErrorCode::RESERVATION_NOT_FOUND->value,
                'error_message' => CouponErrorCode::RESERVATION_NOT_FOUND->message(),
            ];
        }

        $logContext['redemption_id'] = $redemption->id;
        $logContext['coupon_id'] = $redemption->coupon_id;

        // Check if reservation expired
        if ($redemption->isExpired()) {
            Log::info('CouponService: Reservation expired', $logContext);

            // Mark as expired and decrement count
            $redemption->markExpired();
            $redemption->coupon->decrementUsedCount();

            return [
                'success' => false,
                'discount_amount' => 0.0,
                'error_code' => CouponErrorCode::RESERVATION_EXPIRED->value,
                'error_message' => CouponErrorCode::RESERVATION_EXPIRED->message(),
            ];
        }

        // Get ride with final fare
        $ride = TripRequest::find($rideId);
        if (!$ride) {
            Log::error('CouponService: Ride not found', $logContext);
            return [
                'success' => false,
                'discount_amount' => 0.0,
                'error_code' => CouponErrorCode::INTERNAL_ERROR->value,
                'error_message' => 'Ride not found',
            ];
        }

        $finalFare = (float) $ride->paid_fare;
        $coupon = $redemption->coupon;

        // Recompute discount with final fare
        $finalDiscount = $coupon->calculateDiscount($finalFare);

        // Check if min fare is still met with final fare
        if ($finalFare < $coupon->min_fare) {
            Log::info('CouponService: Min fare not met with final fare', array_merge($logContext, [
                'final_fare' => $finalFare,
                'min_fare' => $coupon->min_fare,
            ]));

            // Cancel the reservation since fare requirement not met
            $redemption->markCancelled();
            $coupon->decrementUsedCount();

            return [
                'success' => false,
                'discount_amount' => 0.0,
                'error_code' => CouponErrorCode::MIN_FARE_NOT_MET->value,
                'error_message' => CouponErrorCode::MIN_FARE_NOT_MET->message(),
            ];
        }

        try {
            DB::transaction(function () use ($redemption, $finalFare, $finalDiscount) {
                // Lock redemption row
                $lockedRedemption = CouponRedemption::where('id', $redemption->id)
                    ->lockForUpdate()
                    ->first();

                if (!$lockedRedemption || !$lockedRedemption->isReserved()) {
                    throw new \Exception(CouponErrorCode::RESERVATION_NOT_FOUND->value);
                }

                // Mark as applied
                $lockedRedemption->markApplied($finalFare, $finalDiscount);
            });

            Log::info('CouponService: Coupon applied successfully', array_merge($logContext, [
                'final_fare' => $finalFare,
                'final_discount' => $finalDiscount,
            ]));

            return [
                'success' => true,
                'discount_amount' => $finalDiscount,
                'final_fare' => $finalFare,
                'discounted_fare' => $finalFare - $finalDiscount,
                'error_code' => null,
                'error_message' => null,
            ];

        } catch (\Exception $e) {
            Log::error('CouponService: Error applying coupon', array_merge($logContext, ['error' => $e->getMessage()]));
            return [
                'success' => false,
                'discount_amount' => 0.0,
                'error_code' => CouponErrorCode::INTERNAL_ERROR->value,
                'error_message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Cancel coupon reservation for a ride
     *
     * @param string $rideId
     * @return array ['success' => bool, 'error_code' => ?string]
     */
    public function cancelReservation(string $rideId): array
    {
        $logContext = ['ride_id' => $rideId];

        Log::info('CouponService: Cancelling reservation', $logContext);

        $redemption = CouponRedemption::forRide($rideId)
            ->reserved()
            ->with('coupon')
            ->first();

        if (!$redemption) {
            Log::info('CouponService: No reservation to cancel', $logContext);
            return [
                'success' => true, // Idempotent: already cancelled or never existed
                'error_code' => null,
                'error_message' => null,
            ];
        }

        $logContext['redemption_id'] = $redemption->id;
        $logContext['coupon_id'] = $redemption->coupon_id;

        try {
            DB::transaction(function () use ($redemption) {
                $redemption->markCancelled();
                $redemption->coupon->decrementUsedCount();
            });

            Log::info('CouponService: Reservation cancelled', $logContext);

            return [
                'success' => true,
                'error_code' => null,
                'error_message' => null,
            ];

        } catch (\Exception $e) {
            Log::error('CouponService: Error cancelling reservation', array_merge($logContext, ['error' => $e->getMessage()]));
            return [
                'success' => false,
                'error_code' => CouponErrorCode::INTERNAL_ERROR->value,
                'error_message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Expire stale reservations (called by scheduled command)
     */
    public function expireStaleReservations(): int
    {
        $expired = 0;

        CouponRedemption::expiredReservations()
            ->with('coupon')
            ->chunk(100, function ($redemptions) use (&$expired) {
                foreach ($redemptions as $redemption) {
                    try {
                        DB::transaction(function () use ($redemption) {
                            $redemption->markExpired();
                            $redemption->coupon->decrementUsedCount();
                        });
                        $expired++;

                        Log::info('CouponService: Expired stale reservation', [
                            'redemption_id' => $redemption->id,
                            'ride_id' => $redemption->ride_id,
                        ]);
                    } catch (\Exception $e) {
                        Log::error('CouponService: Error expiring reservation', [
                            'redemption_id' => $redemption->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        return $expired;
    }

    /**
     * Get coupon statistics for admin
     */
    public function getCouponStats(string $couponId): array
    {
        $coupon = Coupon::findOrFail($couponId);

        $redemptions = CouponRedemption::where('coupon_id', $couponId);

        return [
            'coupon' => [
                'id' => $coupon->id,
                'code' => $coupon->code,
                'name' => $coupon->name,
                'type' => $coupon->type,
                'value' => $coupon->value,
                'is_active' => $coupon->is_active,
                'starts_at' => $coupon->starts_at->toIso8601String(),
                'ends_at' => $coupon->ends_at->toIso8601String(),
            ],
            'limits' => [
                'global_limit' => $coupon->global_limit,
                'global_used' => $coupon->global_used_count,
                'per_user_limit' => $coupon->per_user_limit,
            ],
            'redemptions' => [
                'total' => (clone $redemptions)->count(),
                'reserved' => (clone $redemptions)->reserved()->count(),
                'applied' => (clone $redemptions)->applied()->count(),
                'cancelled' => (clone $redemptions)->where('status', CouponRedemption::STATUS_CANCELLED)->count(),
                'expired' => (clone $redemptions)->where('status', CouponRedemption::STATUS_EXPIRED)->count(),
            ],
            'discounts' => [
                'total_discount_applied' => (clone $redemptions)->applied()->sum('final_discount'),
                'average_discount' => (clone $redemptions)->applied()->avg('final_discount'),
            ],
            'targeting' => [
                'eligibility_type' => $coupon->eligibility_type,
                'targeted_users_count' => $coupon->eligibility_type === Coupon::ELIGIBILITY_TARGETED
                    ? CouponTargetUser::where('coupon_id', $couponId)->count()
                    : null,
                'notified_users_count' => $coupon->eligibility_type === Coupon::ELIGIBILITY_TARGETED
                    ? CouponTargetUser::where('coupon_id', $couponId)->where('notified', true)->count()
                    : null,
            ],
        ];
    }
}
