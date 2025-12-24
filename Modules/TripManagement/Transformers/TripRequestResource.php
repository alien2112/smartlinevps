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

class TripRequestResource extends JsonResource
{
    public static $key = false;

    public static function setData($key)
    {
        self::$key = $key;
        return __CLASS__;
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
        ];

        $coordinate = [];
        // PERFORMANCE: Check if relationship is loaded before accessing to avoid N+1 queries
        if ($this->relationLoaded('coordinate') && $this->coordinate) {
            $coordinate = [
                'pickup_coordinates' => $this->coordinate->pickup_coordinates,
                'pickup_address' => utf8Clean($this->coordinate->pickup_address),
                'destination_coordinates' => $this->coordinate->destination_coordinates,
                'destination_address' => utf8Clean($this->coordinate->destination_address),
                'start_coordinates' => $this->coordinate->start_coordinates,
                'drop_coordinates' => $this->coordinate->drop_coordinates,
                'driver_accept_coordinates' => $this->coordinate->driver_accept_coordinates,
                'customer_request_coordinates' => $this->coordinate->customer_request_coordinates,
                'intermediate_coordinates' => $this->coordinate->intermediate_coordinates,
                'intermediate_addresses' => utf8Clean($this->coordinate->intermediate_addresses),
                'is_reached_destination' => (bool)$this->coordinate->is_reached_destination,
                'is_reached_1' => (bool)$this->coordinate->is_reached_1,
                'is_reached_2' => (bool)$this->coordinate->is_reached_2,
            ];
        }

        // PERFORMANCE: Check if relationship is loaded before accessing to avoid N+1 queries
        if ($this->relationLoaded('fee') && $this->fee) {
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
        // PERFORMANCE: Check if relationship is loaded before accessing to avoid N+1 queries
        if ($this->relationLoaded('time') && $this->time) {
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
