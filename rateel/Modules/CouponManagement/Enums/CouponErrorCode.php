<?php

declare(strict_types=1);

namespace Modules\CouponManagement\Enums;

enum CouponErrorCode: string
{
    // Coupon validity errors
    case COUPON_NOT_FOUND = 'COUPON_NOT_FOUND';
    case COUPON_INACTIVE = 'COUPON_INACTIVE';
    case COUPON_EXPIRED = 'COUPON_EXPIRED';
    case COUPON_NOT_STARTED = 'COUPON_NOT_STARTED';

    // Limit errors
    case GLOBAL_LIMIT_REACHED = 'GLOBAL_LIMIT_REACHED';
    case USER_LIMIT_REACHED = 'USER_LIMIT_REACHED';

    // Eligibility errors
    case NOT_ELIGIBLE = 'NOT_ELIGIBLE';
    case NOT_IN_TARGET_LIST = 'NOT_IN_TARGET_LIST';
    case SEGMENT_NOT_MATCHED = 'SEGMENT_NOT_MATCHED';

    // Scope errors
    case CITY_NOT_ALLOWED = 'CITY_NOT_ALLOWED';
    case SERVICE_TYPE_NOT_ALLOWED = 'SERVICE_TYPE_NOT_ALLOWED';
    case SCOPE_MISMATCH = 'SCOPE_MISMATCH';

    // Fare errors
    case MIN_FARE_NOT_MET = 'MIN_FARE_NOT_MET';

    // Redemption errors
    case ALREADY_REDEEMED = 'ALREADY_REDEEMED';
    case RESERVATION_NOT_FOUND = 'RESERVATION_NOT_FOUND';
    case RESERVATION_EXPIRED = 'RESERVATION_EXPIRED';
    case RESERVATION_CANCELLED = 'RESERVATION_CANCELLED';
    case RIDE_ALREADY_HAS_COUPON = 'RIDE_ALREADY_HAS_COUPON';

    // System errors
    case CONCURRENCY_CONFLICT = 'CONCURRENCY_CONFLICT';
    case INTERNAL_ERROR = 'INTERNAL_ERROR';

    /**
     * Get human-readable message for the error code
     */
    public function message(): string
    {
        return match ($this) {
            self::COUPON_NOT_FOUND => 'Coupon code not found',
            self::COUPON_INACTIVE => 'This coupon is no longer active',
            self::COUPON_EXPIRED => 'This coupon has expired',
            self::COUPON_NOT_STARTED => 'This coupon is not yet valid',
            self::GLOBAL_LIMIT_REACHED => 'This coupon has reached its usage limit',
            self::USER_LIMIT_REACHED => 'You have already used this coupon the maximum number of times',
            self::NOT_ELIGIBLE => 'You are not eligible for this coupon',
            self::NOT_IN_TARGET_LIST => 'This coupon is not available for your account',
            self::SEGMENT_NOT_MATCHED => 'You do not qualify for this coupon',
            self::CITY_NOT_ALLOWED => 'This coupon is not valid in your city',
            self::SERVICE_TYPE_NOT_ALLOWED => 'This coupon is not valid for this service type',
            self::SCOPE_MISMATCH => 'This coupon cannot be applied to this ride',
            self::MIN_FARE_NOT_MET => 'Minimum fare requirement not met for this coupon',
            self::ALREADY_REDEEMED => 'This coupon has already been applied to a ride',
            self::RESERVATION_NOT_FOUND => 'No coupon reservation found for this ride',
            self::RESERVATION_EXPIRED => 'Coupon reservation has expired',
            self::RESERVATION_CANCELLED => 'Coupon reservation was cancelled',
            self::RIDE_ALREADY_HAS_COUPON => 'This ride already has a coupon applied',
            self::CONCURRENCY_CONFLICT => 'Unable to apply coupon due to concurrent usage. Please try again.',
            self::INTERNAL_ERROR => 'An unexpected error occurred. Please try again.',
        };
    }

    /**
     * Get HTTP status code for the error
     */
    public function httpStatus(): int
    {
        return match ($this) {
            self::COUPON_NOT_FOUND,
            self::RESERVATION_NOT_FOUND => 404,

            self::CONCURRENCY_CONFLICT => 409,

            self::INTERNAL_ERROR => 500,

            default => 400,
        };
    }
}
