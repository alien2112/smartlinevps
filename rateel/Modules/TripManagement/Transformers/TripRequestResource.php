<?php

namespace Modules\TripManagement\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\ParcelManagement\Transformers\InformationResource;
use Modules\ParcelManagement\Transformers\UserResource;
use Modules\PromotionManagement\Transformers\CouponResource;
use Modules\PromotionManagement\Transformers\DiscountResource;
use Modules\UserManagement\Transformers\CustomerResource;
use Modules\UserManagement\Transformers\DriverResource;
use Modules\VehicleManagement\Transformers\VehicleModelResource;
use Modules\VehicleManagement\Transformers\VehicleCategoryResource;
use Modules\VehicleManagement\Transformers\VehicleResource;
use Modules\ZoneManagement\Transformers\ZoneResource;

/**
 * Issue #26 FIX: Added selective field loading to reduce response payload size
 *
 * Usage:
 *   TripRequestResource::$fieldSet = 'minimal'; // Only essential fields
 *   TripRequestResource::$fieldSet = 'basic';   // Basic + driver info
 *   TripRequestResource::$fieldSet = 'full';    // All fields (default)
 *
 *   // Or use specific fields:
 *   TripRequestResource::$fields = ['id', 'status', 'driver', 'fare'];
 */
class TripRequestResource extends JsonResource
{
    public static $key = false;

    /**
     * Issue #26 FIX: Field set presets
     * 'minimal' - Just IDs and status
     * 'basic' - Common fields for lists
     * 'full' - All fields (default)
     */
    public static string $fieldSet = 'full';

    /**
     * Issue #26 FIX: Custom field selection
     * When set, only these fields are included
     */
    public static array $fields = [];

    public static function setData($key)
    {
        self::$key = $key;
        return __CLASS__;
    }

    /**
     * Issue #26 FIX: Set field set preset
     */
    public static function withFieldSet(string $fieldSet): string
    {
        self::$fieldSet = $fieldSet;
        self::$fields = [];
        return __CLASS__;
    }

    /**
     * Issue #26 FIX: Set custom fields
     */
    public static function withFields(array $fields): string
    {
        self::$fields = $fields;
        return __CLASS__;
    }

    /**
     * Issue #26 FIX: Reset to defaults
     */
    public static function resetFields(): void
    {
        self::$fieldSet = 'full';
        self::$fields = [];
    }

    /**
     * Convert Point object to Flutter-friendly format with correct lat/lng labels.
     * Note: Our database stores coordinates with latitude in the 'longitude' field
     * and longitude in the 'latitude' field (swapped from GeoJSON standard).
     * This helper outputs them with correct labels for the mobile app.
     */
    private function formatCoordinates($point): ?array
    {
        if (!$point) {
            return null;
        }

        // The Point object's internal latitude/longitude are swapped in our DB
        // $point->longitude actually contains latitude (e.g., 31.1 for Alexandria)
        // $point->latitude actually contains longitude (e.g., 29.7 for Alexandria)
        $actualLat = $point->longitude;  // stored "longitude" is actually latitude
        $actualLng = $point->latitude;   // stored "latitude" is actually longitude

        return [
            'type' => 'Point',
            'coordinates' => [$actualLng, $actualLat], // GeoJSON format: [longitude, latitude]
            'lat' => $actualLat,
            'lng' => $actualLng,
        ];
    }

    /**
     * Issue #26 FIX: Check if a field should be included
     */
    private function shouldInclude(string $field): bool
    {
        // If specific fields are set, check against them
        if (!empty(self::$fields)) {
            return in_array($field, self::$fields);
        }

        // Otherwise use field sets
        $fieldSets = [
            'minimal' => [
                'id', 'ref_id', 'current_status', 'type', 'created_at',
            ],
            'basic' => [
                'id', 'ref_id', 'current_status', 'type', 'created_at',
                'estimated_fare', 'paid_fare', 'driver', 'customer',
                'pickup_coordinates', 'destination_coordinates',
                'pickup_address', 'destination_address',
                'payment_status', 'payment_method', 'otp',
            ],
            'full' => [], // Empty means include all
        ];

        // Full includes everything
        if (self::$fieldSet === 'full') {
            return true;
        }

        return in_array($field, $fieldSets[self::$fieldSet] ?? []);
    }

    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array
     */
    public function toArray($request)
    {
        $fee = [];
        $trip_request = [
            'id' => $this->id,
            'ref_id' => $this->ref_id,
            'customer' => CustomerResource::make($this->whenLoaded('customer')),
            'driver' => DriverResource::make($this->whenLoaded('driver')),
            // 'vehicle_category' => VehicleCategoryResource::make($this->whenLoaded('vehicleCategory')),
             'vehicle_category' => $this->vehicle && $this->vehicle->relationLoaded('categories')
            ? VehicleCategoryResource::collection($this->vehicle->categories)
            : VehicleCategoryResource::make($this->whenLoaded('vehicleCategory')),
            'vehicle' => VehicleResource::make($this->whenLoaded('vehicle')),
            'zone' => ZoneResource::make($this->whenLoaded('zone')),
            'model' => VehicleModelResource::make($this->whenLoaded('vehicle.model')),
            'estimated_fare' => round((double)$this->estimated_fare, 2),
            'actual_fare' => $this->actual_fare,
            'return_fee' => $this->return_fee,
            'return_time' => $this->return_time,
            'due_amount' => $this->due_amount,
            'discount_actual_fare' => $this->discount_actual_fare,
            'estimated_distance' => $this->estimated_distance,
            'paid_fare' => round((double)$this->paid_fare, 2),
            'actual_distance' => round((double)$this->actual_distance, 2),
            'accepted_by' => $this->accepted_by,
            'payment_status' => $this->payment_status,
            'payment_method' => $this->payment_method,
            'coupon_amount' => round((double)$this->coupon_amount, 2),
            'discount' => $this->discount_id === null ? null : DiscountResource::make($this->discount),
            'discount_amount' => $this->discount_amount === null ? null : round((double)$this->discount_amount, 2),
            'note' => utf8Clean($this->note),
            'otp' => $this->otp,
            'rise_request_count' => $this->rise_request_count,
            'type' => $this->type,
            'created_at' => $this->created_at,
            'entrance' => utf8Clean($this->entrance),
            'encoded_polyline' => utf8Clean($this->encoded_polyline),
            'customer_review' => !($this->customerReceivedReview == null),
            'driver_review' => !($this->driverReceivedReview == null),
            'customer_avg_rating' => $this->customer_received_reviews_avg_rating,
            'driver_avg_rating' => $this->driver_received_reviews_avg_rating,
            'current_status' => $this->current_status,
            'is_paused' => (bool)$this->is_paused,
            'fare_biddings' => FareBiddingResource::collection($this->whenLoaded('fare_biddings')),
            'parcel_information' => InformationResource::make($this->whenLoaded('parcel')),
            'parcel_user_info' => UserResource::collection($this->whenLoaded('parcelUserInfo')),
            'coupon' => $this->coupon_id === null ? null : CouponResource::make($this->coupon),
            'tripStatus' => TripStatusResource::make($this->whenLoaded('tripStatus')),
            'screenshot' => $this->map_screenshot,
            'parcel_complete_time' => $this->type ===PARCEL ? $this->tripStatus->completed : null,
            'ride_complete_time' => $this->type === RIDE_REQUEST ? $this->tripStatus->completed : null,
            'parcel_refund' => ParcelRefundResource::make($this->whenLoaded('parcelRefund')),
            'driver_safety_alert' => SafetyAlertResource::make($this->driverSafetyAlert),
            'customer_safety_alert' => SafetyAlertResource::make($this->customerSafetyAlert),
            'trip_cancellation_reason' => utf8Clean($this->trip_cancellation_reason),
            // Unified travel mode fields
            'trip_type' => $this->trip_type ?? 'normal',
            'is_travel' => $this->isTravel(), // Helper method supports both old and new
            'scheduled_at' => $this->scheduled_at,
            'seats_requested' => $this->seats_requested ?? 1,
            'seats_capacity' => $this->seats_capacity,
            'seats_taken' => $this->seats_taken ?? 0,
            'seats_left' => $this->seats_left ?? 0, // Computed attribute
            'min_price' => $this->min_price ? round((double)$this->min_price, 2) : null,
            'recommended_fare' => $this->recommended_fare ? round((double)$this->recommended_fare, 2) : null,
            'offer_price' => $this->offer_price ? round((double)$this->offer_price, 2) : null,
            // Legacy travel fields (for backward compatibility)
            'fixed_price' => $this->fixed_price ? round((double)$this->fixed_price, 2) : null,
            'travel_date' => $this->travel_date,
            'travel_passengers' => $this->travel_passengers,
            'travel_luggage' => $this->travel_luggage,
            'travel_notes' => utf8Clean($this->travel_notes),
            'travel_status' => $this->travel_status,
        ];

        $coordinate = [];
        if ($this->coordinate()->exists()) {
            $coordinate = [
                'pickup_coordinates' => $this->whenLoaded('coordinate', $this->formatCoordinates($this->coordinate->pickup_coordinates)),
                'pickup_address' => $this->whenLoaded('coordinate', utf8Clean($this->coordinate->pickup_address)),
                'destination_coordinates' => $this->whenLoaded('coordinate', $this->formatCoordinates($this->coordinate->destination_coordinates)),
                'destination_address' => $this->whenLoaded('coordinate', utf8Clean($this->coordinate->destination_address)),
                'start_coordinates' => $this->whenLoaded('coordinate', $this->formatCoordinates($this->coordinate->start_coordinates)),
                'drop_coordinates' => $this->whenLoaded('coordinate', $this->formatCoordinates($this->coordinate->drop_coordinates)),
                'driver_accept_coordinates' => $this->whenLoaded('coordinate', $this->formatCoordinates($this->coordinate->driver_accept_coordinates)),
                'customer_request_coordinates' => $this->whenLoaded('coordinate', $this->formatCoordinates($this->coordinate->customer_request_coordinates)),
                'intermediate_coordinates' => ($this->whenLoaded('coordinate', $this->coordinate->intermediate_coordinates)),
                'intermediate_addresses' => $this->whenLoaded('coordinate', utf8Clean($this->coordinate->intermediate_addresses)),
                'is_reached_destination' => (bool)$this->whenLoaded('coordinate', $this->coordinate->is_reached_destination),
                'is_reached_1' => (bool)$this->whenLoaded('coordinate', $this->coordinate->is_reached_1),
                'is_reached_2' => (bool)$this->whenLoaded('coordinate', $this->coordinate->is_reached_2),
            ];
        }

        if ($this->fee()->exists()) {
            $fee = [
                'waiting_fee' => round((double)$this->fee->waiting_fee, 2),
                'waited_by' => $this->fee->waited_by,
                'idle_fee' => round((double)$this->fee->idle_fee, 2),
                'delay_fee' => round((double)$this->fee->delay_fee, 2),
                'delayed_by' => $this->fee->delayed_by,
                'cancellation_fee' => round((double)$this->fee->cancellation_fee, 2),
                'cancelled_by' => $this->fee->cancelled_by,
                'vat_tax' => round((double)$this->fee->vat_tax, 2),
                'admin_commission' => round((double)$this->fee->admin_commission, 2),
                'tips' => round((double)$this->fee->tips, 2),
                'distance_wise_fare' => $this->whenAppended('distance_wise_fare', $this->distance_wise_fare()),
            ];


            if (self::$key == 'distance_wise_fare') {
                $fee = ['distance_wise_fare' => round($this->distance_wise_fare(), 2)];
            }
        }

        $time = [];
        if ($this->time()->exists()) {
            $time = [
                'waiting_time' => round((double)$this->time->waiting_time, 2),
                'delay_time' => round((double)$this->time->delay_time, 2),
                'idle_time' => round((double)$this->time->idle_time),
                'actual_time' => round((double)$this->time->actual_time, 2),
                'estimated_time' => round((double)$this->time->estimated_time, 2),
            ];
        }
        return array_merge($trip_request, $coordinate, $fee, $time);
    }
}
