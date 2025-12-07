<?php

namespace Modules\TripManagement\Lib;

use Carbon\Carbon;
use Modules\TripManagement\Entities\FareBidding;
use Modules\TripManagement\Entities\TripRequestFee;
use Modules\TripManagement\Entities\TripRequestTime;

trait CommonTrait
{
    use DiscountCalculationTrait, CouponCalculationTrait;

    // public function calculateFinalFare($trip, $fare): array
    // {
    //     $admin_trip_commission = (double)get_cache('trip_commission') ?? 0;
    //     // parcel start
    //     if ($trip->type == 'parcel') {

    //         $vat_percent = (double)get_cache('vat_percent') ?? 1;
    //         $actual_fare = $trip->actual_fare / (1 + ($vat_percent / 100));
    //         $parcel_payment = $actual_fare;
    //         $vat = round(($vat_percent * $parcel_payment) / 100, 2);
    //         $fee = TripRequestFee::where('trip_request_id', $trip->id)->first();
    //         $fee->vat_tax = $vat;
    //         $fee->admin_commission = (($parcel_payment * $admin_trip_commission) / 100) + $vat;
    //         $fee->save();

    //         return [
    //             'extra_fare_amount' => round($trip->extra_fare_amount, 2),
    //             'actual_fare' => round($actual_fare, 2),
    //             'final_fare' => round($parcel_payment + $vat, 2),
    //             'waiting_fee' => 0,
    //             'idle_fare' => 0,
    //             'cancellation_fee' => 0,
    //             'delay_fee' => 0,
    //             'vat' => $vat,
    //             'actual_distance' => $trip->estimated_distance,
    //         ];
    //     }

    //     $fee = TripRequestFee::query()->firstWhere('trip_request_id', $trip->id);
    //     $time = TripRequestTime::query()->firstWhere('trip_request_id', $trip->id);

    //     $bid_on_fare = FareBidding::where('trip_request_id', $trip->id)->where('is_ignored', 0)->first();
    //     $current_status = $trip->current_status;
    //     $cancellation_fee = 0;
    //     $waiting_fee = 0;
    //     $distance_in_km = 0;

    //     $drivingMode = $trip?->vehicleCategory?->type === 'motor_bike' ? 'TWO_WHEELER' : 'DRIVE';
    //     $drop_coordinate = [
    //         $trip->coordinate->drop_coordinates->latitude,
    //         $trip->coordinate->drop_coordinates->longitude
    //     ];
    //     $destination_coordinate = [
    //         $trip->coordinate->destination_coordinates->latitude,
    //         $trip->coordinate->destination_coordinates->longitude
    //     ];
    //     $pickup_coordinate = [
    //         $trip->coordinate->pickup_coordinates->latitude,
    //         $trip->coordinate->pickup_coordinates->longitude
    //     ];
    //     $intermediate_coordinate = [];
    //     if ($trip->coordinate->is_reached_1) {
    //         if ($trip->coordinate->is_reached_2) {
    //             $intermediate_coordinate[1] = [
    //                 $trip->coordiante->int_coordinate_2->latitude,
    //                 $trip->coordiante->int_coordinate_2->longitude
    //             ];
    //         }
    //         $intermediate_coordinate[0] = [
    //             $trip->coordiante->int_coordinate_1->latitude,
    //             $trip->coordiante->int_coordinate_1->longitude
    //         ];
    //     }

    //     if ($current_status === 'cancelled') {
    //         $route = getRoutes($pickup_coordinate, $drop_coordinate, $intermediate_coordinate, [$drivingMode]);
    //         $distance_in_km = $route[0]['distance'];

    //         $distance_wise_fare_cancelled = $fare->base_fare_per_km * $distance_in_km;
    //         $actual_fare = $fare->base_fare + $distance_wise_fare_cancelled;
    //         if ($trip->extra_fare_fee > 0) {
    //             $extraFare = ($actual_fare * $trip->extra_fare_fee) / 100;
    //             $actual_fare += $extraFare;
    //         }

    //         if ($trip->fee->cancelled_by === 'customer') {
    //             $cancellation_percent = $fare->cancellation_fee_percent;
    //             $cancellation_fee = max((($cancellation_percent * $distance_wise_fare_cancelled) / 100), $fare->min_cancellation_fee);
    //         }
    //     } elseif ($current_status == 'completed') {
    //         $route = getRoutes($pickup_coordinate, $drop_coordinate, $intermediate_coordinate, [$drivingMode]);
    //         $distance_in_km = $route[0]['distance'];

    //         $distance_wise_fare_completed = $fare->base_fare_per_km * $distance_in_km;
    //         $actual_fare = $fare->base_fare + $distance_wise_fare_completed;
    //         if ($trip->extra_fare_fee > 0) {
    //             $extraFare = ($actual_fare * $trip->extra_fare_fee) / 100;
    //             $actual_fare += $extraFare;
    //         }
    //         $vat_percent = (double)get_cache('vat_percent') ?? 1;
    //         $distanceFare = $trip->rise_request_count > 0 ? $trip->actual_fare / (1 + ($vat_percent / 100)) : $actual_fare;
    //         $actual_fare = $bid_on_fare ? $bid_on_fare->bid_fare / (1 + ($vat_percent / 100)) : $distanceFare;
    //     } else {
    //         $actual_fare = 0;
    //     }


    //     $trip_started = Carbon::parse($trip->tripStatus->ongoing);
    //     $trip_ended = Carbon::parse($trip->tripStatus->$current_status);
    //     $actual_time = $trip_started->diffInMinutes($trip_ended);

    //     //        Idle time & fee calculation
    //     $idle_fee_buffer = (double)get_cache('idle_fee') ?? 0;
    //     $idle_diff = $trip->time->idle_time - $idle_fee_buffer;
    //     $idle_time = max($idle_diff, 0);
    //     $idle_fee = $idle_time * $fare->idle_fee_per_min;

    //     //        Delay time & fee calculation
    //     $delay_fee_buffer = (double)get_cache('delay_fee') ?? 0;
    //     $delay_diff = $actual_time - ($trip->time->estimated_time + $delay_fee_buffer + $trip->time->idle_time);
    //     $delay_time = max($delay_diff, 0);
    //     $delay_fee = $delay_time * $fare->trip_delay_fee_per_min;


    //     $vat_percent = (double)get_cache('vat_percent') ?? 1;
    //     $final_fare_without_tax = ($actual_fare + $waiting_fee + $idle_fee + $cancellation_fee + $delay_fee);
    //     $vat = ($final_fare_without_tax * $vat_percent) / 100;

    //     $fee->vat_tax = round($vat, 2);
    //     $fee->admin_commission = (($final_fare_without_tax * $admin_trip_commission) / 100) + $vat;
    //     $fee->cancellation_fee = round($cancellation_fee, 2);
    //     $time->actual_time = $actual_time;
    //     $time->idle_time = $idle_time;
    //     $fee->idle_fee = round($idle_fee, 2);
    //     $time->delay_time = $delay_time;
    //     $fee->delay_fee = round($delay_fee, 2);
    //     $fee->save();
    //     $time->save();

    //     return [
    //         'extra_fare_amount' => round($extraFare ?? 0, 2),
    //         'actual_fare' => round($actual_fare, 2),
    //         'final_fare' => round($final_fare_without_tax + $vat, 2),
    //         'waiting_fee' => $waiting_fee,
    //         'idle_fare' => $idle_fee,
    //         'cancellation_fee' => $cancellation_fee,
    //         'delay_fee' => $delay_fee,
    //         'vat' => $vat,
    //         'actual_distance' => $distance_in_km
    //     ];
    // }
    
    // public function calculateFinalFare2($trip, $fare): array
    // {
    //     $admin_trip_commission = (double)get_cache('trip_commission') ?? 0;
    //     $vat_percent = (double)get_cache('vat_percent') ?? 1;
    //     $points = (int)getSession('currency_decimal_point') ?? 0;

    //     // Retrieve default fare from zone
    //     $zone = $trip->zone;
    //     $default_fare = $zone->defaultFare ?? null;
    //     $minimum_distance_threshold = $default_fare->minimum_distance_threshold ?? 0;
    //     $fixed_price_below_threshold = $default_fare->fixed_price_below_threshold ?? 0;

    //     $fee = TripRequestFee::query()->firstWhere('trip_request_id', $trip->id);
    //     $time = TripRequestTime::query()->firstWhere('trip_request_id', $trip->id);

    //     // Parcel handling
    //     if ($trip->type == 'parcel') {
    //         $actual_distance_km = $trip->estimated_distance / 1000; // Convert to km
    //         if ($actual_distance_km > 0 && $actual_distance_km <= $minimum_distance_threshold) {
    //             // Apply fixed price for parcels
    //             $parcel_payment = round($fixed_price_below_threshold, $points);
    //             $vat = round(($vat_percent * $parcel_payment) / 100, 2);
    //             $fee->vat_tax = $vat;
    //             $fee->admin_commission = (($parcel_payment * $admin_trip_commission) / 100) + $vat;
    //             $fee->save();

    //             return [
    //                 'extra_fare_amount' => 0,
    //                 'actual_fare' => $parcel_payment,
    //                 'final_fare' => $parcel_payment + $vat,
    //                 'waiting_fee' => 0,
    //                 'idle_fare' => 0,
    //                 'cancellation_fee' => 0,
    //                 'delay_fee' => 0,
    //                 'vat' => $vat,
    //                 'actual_distance' => $trip->estimated_distance,
    //             ];
    //         }

    //         // Original parcel logic
    //         $actual_fare = $trip->actual_fare / (1 + ($vat_percent / 100));
    //         $parcel_payment = $actual_fare;
    //         $vat = round(($vat_percent * $parcel_payment) / 100, 2);
    //         $fee->vat_tax = $vat;
    //         $fee->admin_commission = (($parcel_payment * $admin_trip_commission) / 100) + $vat;
    //         $fee->save();

    //         return [
    //             'extra_fare_amount' => round($trip->extra_fare_amount, 2),
    //             'actual_fare' => round($actual_fare, 2),
    //             'final_fare' => round($parcel_payment + $vat, 2),
    //             'waiting_fee' => 0,
    //             'idle_fare' => 0,
    //             'cancellation_fee' => 0,
    //             'delay_fee' => 0,
    //             'vat' => $vat,
    //             'actual_distance' => $trip->estimated_distance,
    //         ];
    //     }

    //     // Ride request handling
    //     $bid_on_fare = FareBidding::where('trip_request_id', $trip->id)->where('is_ignored', 0)->first();
    //     $current_status = $trip->current_status;
    //     $cancellation_fee = 0;
    //     $waiting_fee = 0;
    //     $distance_in_km = 0;

    //     $drivingMode = $trip?->vehicleCategory?->type === 'motor_bike' ? 'TWO_WHEELER' : 'DRIVE';
    //     $drop_coordinate = [
    //         $trip->coordinate->drop_coordinates->latitude,
    //         $trip->coordinate->drop_coordinates->longitude
    //     ];
    //     $destination_coordinate = [
    //         $trip->coordinate->destination_coordinates->latitude,
    //         $trip->coordinate->destination_coordinates->longitude
    //     ];
    //     $pickup_coordinate = [
    //         $trip->coordinate->pickup_coordinates->latitude,
    //         $trip->coordinate->pickup_coordinates->longitude
    //     ];
    //     $intermediate_coordinate = [];
    //     if ($trip->coordinate->is_reached_1) {
    //         if ($trip->coordinate->is_reached_2) {
    //             $intermediate_coordinate[1] = [
    //                 $trip->coordinate->int_coordinate_2->latitude,
    //                 $trip->coordinate->int_coordinate_2->longitude
    //             ];
    //         }
    //         $intermediate_coordinate[0] = [
    //             $trip->coordinate->int_coordinate_1->latitude,
    //             $trip->coordinate->int_coordinate_1->longitude
    //         ];
    //     }

    //     // Calculate actual distance
    //     if ($current_status === 'cancelled' || $current_status === 'completed') {
    //         $route = getRoutes($pickup_coordinate, $drop_coordinate, $intermediate_coordinate, [$drivingMode]);
    //         $distance_in_km = $route[0]['distance'] / 1000; // Convert to km
    //     }

    //     // Apply fixed price if distance is below threshold
    //     if ($distance_in_km > 0 && $distance_in_km <= $minimum_distance_threshold) {
    //         $actual_fare = round($fixed_price_below_threshold, $points);
    //         $final_fare_without_tax = $actual_fare;
    //         $vat = round(($vat_percent * $final_fare_without_tax) / 100, 2);
    //         $fee->vat_tax = $vat;
    //         $fee->admin_commission = (($final_fare_without_tax * $admin_trip_commission) / 100) + $vat;
    //         $fee->cancellation_fee = 0;
    //         $fee->idle_fee = 0;
    //         $fee->delay_fee = 0;
    //         $fee->save();
    //         $time->actual_time = $current_status === 'completed' ? $trip->time->actual_time : 0;
    //         $time->idle_time = 0;
    //         $time->delay_time = 0;
    //         $time->save();

    //         return [
    //             'extra_fare_amount' => 0,
    //             'actual_fare' => $actual_fare,
    //             'final_fare' => $final_fare_without_tax + $vat,
    //             'waiting_fee' => 0,
    //             'idle_fare' => 0,
    //             'cancellation_fee' => 0,
    //             'delay_fee' => 0,
    //             'vat' => $vat,
    //             'actual_distance' => $distance_in_km * 1000 // Convert back to meters
    //         ];
    //     }

    //     // Original ride request logic
    //     if ($current_status === 'cancelled') {
    //         $distance_wise_fare_cancelled = $fare->base_fare_per_km * $distance_in_km;
    //         $actual_fare = $fare->base_fare + $distance_wise_fare_cancelled;
    //         if ($trip->extra_fare_fee > 0) {
    //             $extraFare = ($actual_fare * $trip->extra_fare_fee) / 100;
    //             $actual_fare += $extraFare;
    //         }

    //         if ($trip->fee->cancelled_by === 'customer') {
    //             $cancellation_percent = $fare->cancellation_fee_percent;
    //             $cancellation_fee = max((($cancellation_percent * $distance_wise_fare_cancelled) / 100), $fare->min_cancellation_fee);
    //         }
    //     } elseif ($current_status === 'completed') {
    //         $distance_wise_fare_completed = $fare->base_fare_per_km * $distance_in_km;
    //         $actual_fare = $fare->base_fare + $distance_wise_fare_completed;
    //         if ($trip->extra_fare_fee > 0) {
    //             $extraFare = ($actual_fare * $trip->extra_fare_fee) / 100;
    //             $actual_fare += $extraFare;
    //         }
    //         $distanceFare = $trip->rise_request_count > 0 ? $trip->actual_fare / (1 + ($vat_percent / 100)) : $actual_fare;
    //         $actual_fare = $bid_on_fare ? $bid_on_fare->bid_fare / (1 + ($vat_percent / 100)) : $distanceFare;
    //     } else {
    //         $actual_fare = 0;
    //     }

    //     $trip_started = Carbon::parse($trip->tripStatus->ongoing);
    //     $trip_ended = Carbon::parse($trip->tripStatus->$current_status);
    //     $actual_time = $trip_started->diffInMinutes($trip_ended);

    //     // Idle time & fee calculation
    //     $idle_fee_buffer = (double)get_cache('idle_fee') ?? 0;
    //     $idle_diff = $trip->time->idle_time - $idle_fee_buffer;
    //     $idle_time = max($idle_diff, 0);
    //     $idle_fee = $idle_time * $fare->idle_fee_per_min;

    //     // Delay time & fee calculation
    //     $delay_fee_buffer = (double)get_cache('delay_fee') ?? 0;
    //     $delay_diff = $actual_time - ($trip->time->estimated_time + $delay_fee_buffer + $trip->time->idle_time);
    //     $delay_time = max($delay_diff, 0);
    //     $delay_fee = $delay_time * $fare->trip_delay_fee_per_min;

    //     $final_fare_without_tax = ($actual_fare + $waiting_fee + $idle_fee + $cancellation_fee + $delay_fee);
    //     $vat = ($final_fare_without_tax * $vat_percent) / 100;

    //     $fee->vat_tax = round($vat, 2);
    //     $fee->admin_commission = (($final_fare_without_tax * $admin_trip_commission) / 100) + $vat;
    //     $fee->cancellation_fee = round($cancellation_fee, 2);
    //     $time->actual_time = $actual_time;
    //     $time->idle_time = $idle_time;
    //     $fee->idle_fee = round($idle_fee, 2);
    //     $time->delay_time = $delay_time;
    //     $fee->delay_fee = round($delay_fee, 2);
    //     $fee->save();
    //     $time->save();

    //     return [
    //         'extra_fare_amount' => round($extraFare ?? 0, 2),
    //         'actual_fare' => round($actual_fare, 2),
    //         'final_fare' => round($final_fare_without_tax + $vat, 2),
    //         'waiting_fee' => $waiting_fee,
    //         'idle_fare' => $idle_fee,
    //         'cancellation_fee' => $cancellation_fee,
    //         'delay_fee' => $delay_fee,
    //         'vat' => $vat,
    //         'actual_distance' => $distance_in_km * 1000 // Convert back to meters
    //     ];
    // }
    

    // public function estimatedFare($tripRequest, $routes, $zone_id, $zone, $tripFare = null, $area_id = null, $beforeCreate = false): mixed
    // {

    //     if ($tripRequest['type'] == 'parcel') {
    //         abort_if(boolean: empty($tripFare), code: 403, message: translate('invalid_or_missing_information'));
    //         abort_if(boolean: empty($tripFare->fares), code: 403, message: translate('no_fares_found'));
    //         $extraFare = $this->checkZoneExtraFare($zone);

    //         $distance_wise_fare = $tripFare->fares[0]->fare_per_km * $routes[0]['distance'];
    //         $est_fare = $tripFare->fares[0]->base_fare + $distance_wise_fare;
    //         if (!empty($extraFare)) {
    //             $extraEstFareAmount = ($est_fare * $extraFare['extraFareFee']) / 100;
    //             $extraEstFare = $extraEstFareAmount + $est_fare;
    //             $extraReturnFee = ($extraEstFare * $tripFare->fares[0]->return_fee) / 100;
    //             $extraCancellationFee = ($extraEstFare * $tripFare->fares[0]->cancellation_fee) / 100;
    //         }
    //         $returnFee = ($est_fare * $tripFare->fares[0]->return_fee) / 100;
    //         $cancellationFee = ($est_fare * $tripFare->fares[0]->cancellation_fee) / 100;
    //         $user = auth('api')->user();
    //         $vat_percent = (double)get_cache('vat_percent') ?? 1;
    //         $discount = $this->getEstimatedDiscount(user: $user, zoneId: $zone_id, tripType: $tripRequest['type'], vehicleCategoryId: null, estimatedAmount: $est_fare, beforeCreate: $beforeCreate);
    //         $discountEstFare = $est_fare - ($discount ? $discount['discount_amount'] : 0);
    //         $extraDiscount = null;
    //         if (!empty($extraFare)) {
    //             $extraDiscount = $this->getEstimatedDiscount(user: $user, zoneId: $zone_id, tripType: $tripRequest['type'], vehicleCategoryId: null, estimatedAmount: $extraEstFare, beforeCreate: $beforeCreate);
    //             $extraDiscountEstFare = $extraEstFare - ($extraDiscount ? $extraDiscount['discount_amount'] : 0);
    //             $coupon = $this->getEstimatedCouponDiscount(user: $user, zoneId: $zone_id, tripType: $tripRequest['type'], vehicleCategoryId: null, estimatedAmount: $extraDiscountEstFare);
    //             $extraDiscountFareVat = ($extraDiscountEstFare * $vat_percent) / 100;
    //             $extraDiscountEstFare += $extraDiscountFareVat;
    //             $extraVat = ($extraEstFare * $vat_percent) / 100;
    //             $extraEstFare += $extraVat;
    //         } else {
    //             $coupon = $this->getEstimatedCouponDiscount(user: $user, zoneId: $zone_id, tripType: $tripRequest['type'], vehicleCategoryId: null, estimatedAmount: $discountEstFare);
    //         }
    //         $discountFareVat = ($discountEstFare * $vat_percent) / 100;
    //         $discountEstFare += $discountFareVat;
    //         $vat = ($est_fare * $vat_percent) / 100;
    //         $est_fare += $vat;
    //         $points = (int)getSession('currency_decimal_point') ?? 0;
    //         $estimated_fare = [
    //             'id' => $tripFare->id,
    //             'zone_id' => $zone->id,
    //             'area_id' => $area_id,
    //             'base_fare' => $tripFare->base_fare,
    //             'base_fare_per_km' => $tripFare->base_fare_per_km,
    //             'fare' => $tripFare->fares,
    //             'estimated_distance' => (double)$routes[0]['distance'],
    //             'estimated_duration' => $routes[0]['duration'],
    //             'estimated_fare' => round($est_fare, $points),
    //             'discount_fare' => round($discountEstFare, $points),
    //             'discount_amount' => round(($discount ? $discount['discount_amount'] : 0), $points),
    //             'coupon_applicable' => $coupon,
    //             'request type' => $tripRequest['type'],
    //             'encoded_polyline' => $routes[0]['encoded_polyline'],
    //             'return_fee' => $returnFee,
    //             'cancellation_fee' => $cancellationFee,
    //             'extra_estimated_fare' => round($extraEstFare ?? 0, $points),
    //             'extra_discount_fare' => round($extraDiscountEstFare ?? 0, $points),
    //             'extra_discount_amount' => round(($extraDiscount ? $extraDiscount['discount_amount'] : 0), $points),
    //             'extra_return_fee' => $extraReturnFee ?? 0,
    //             'extra_cancellation_fee' => $extraCancellationFee ?? 0,
    //             'extra_fare_amount' => round(($extraEstFareAmount ?? 0), $points),
    //             'extra_fare_fee' => $extraFare ? $extraFare['extraFareFee'] : 0,
    //             'extra_fare_reason' => $extraFare ? $extraFare['extraFareReason'] : ""
    //         ];

    //     } else {

    //         $estimated_fare = $tripFare->map(function ($trip) use ($routes, $tripRequest, $area_id, $beforeCreate, $zone) {
    //             foreach ($routes as $route) {
    //                 if ($route['drive_mode'] === 'DRIVE') {
    //                     $distance = $route['distance'];
    //                     $drive_fare = $trip->base_fare_per_km * $distance;
    //                     $drive_est_distance = (double)$routes[0]['distance'];
    //                     $drive_est_duration = $route['duration'];
    //                     $drive_polyline = $route['encoded_polyline'];
    //                 } elseif ($route['drive_mode'] === 'TWO_WHEELER') {
    //                     $distance = $route['distance'];
    //                     $bike_fare = $trip->base_fare_per_km * $distance;
    //                     $bike_est_distance = (double)$routes[0]['distance'];
    //                     $bike_est_duration = $route['duration'];
    //                     $bike_polyline = $route['encoded_polyline'];
    //                 }
    //             }
    //             $extraFare = $this->checkZoneExtraFare($zone);
    //             $points = (int)getSession('currency_decimal_point') ?? 0;
    //             $est_fare = $trip->vehicleCategory->type === 'car' ? round(($trip->base_fare + $drive_fare), $points) : round(($trip->base_fare + $bike_fare), $points);
    //             if (!empty($extraFare)) {
    //                 $extraEstFareAmount = ($est_fare * $extraFare['extraFareFee']) / 100;
    //                 $extraEstFare = $extraEstFareAmount + $est_fare;
    //             }
    //             $user = auth('api')->user();
    //             $discount = $this->getEstimatedDiscount(user: $user, zoneId: $zone->id, tripType: $tripRequest['type'], vehicleCategoryId: $trip->vehicleCategory->id, estimatedAmount: $est_fare, beforeCreate: $beforeCreate);
    //             $vat_percent = (double)get_cache('vat_percent') ?? 1;
    //             $discountEstFare = $est_fare - ($discount ? $discount['discount_amount'] : 0);
    //             $extraDiscount = null;
    //             if (!empty($extraFare)) {
    //                 $extraDiscount = $this->getEstimatedDiscount(user: $user, zoneId: $zone->id, tripType: $tripRequest['type'], vehicleCategoryId: $trip->vehicleCategory->id, estimatedAmount: $extraEstFare, beforeCreate: $beforeCreate);
    //                 $extraDiscountEstFare = $extraEstFare - ($extraDiscount ? $extraDiscount['discount_amount'] : 0);
    //                 $coupon = $this->getEstimatedCouponDiscount(user: $user, zoneId: $zone->id, tripType: $tripRequest['type'], vehicleCategoryId: $trip->vehicleCategory->id, estimatedAmount: $extraDiscountEstFare);
    //                 $extraDiscountFareVat = ($extraDiscountEstFare * $vat_percent) / 100;
    //                 $extraDiscountEstFare += $extraDiscountFareVat;
    //                 $extraVat = ($extraEstFare * $vat_percent) / 100;
    //                 $extraEstFare += $extraVat;
    //             } else {
    //                 $coupon = $this->getEstimatedCouponDiscount(user: $user, zoneId: $zone->id, tripType: $tripRequest['type'], vehicleCategoryId: $trip->vehicleCategory->id, estimatedAmount: $discountEstFare);
    //             }
    //             $discountFareVat = ($discountEstFare * $vat_percent) / 100;
    //             $discountEstFare += $discountFareVat;
    //             $vat = ($est_fare * $vat_percent) / 100;
    //             $est_fare += $vat;
    //             return [
    //                 "id" => $trip->id,
    //                 "zone_id" => $zone->id,
    //                 'area_id' => $area_id,
    //                 "vehicle_category_id" => $trip->vehicle_category_id,
    //                 'base_fare' => $trip->base_fare,
    //                 'base_fare_per_km' => $trip->base_fare_per_km,
    //                 'fare' => $trip->VehicleCategory->type === 'car' ? round($drive_fare, 2) : round($bike_fare, 2),
    //                 'estimated_distance' => $trip->VehicleCategory->type === 'car' ? $drive_est_distance : $bike_est_distance,
    //                 'estimated_duration' => $trip->VehicleCategory->type === 'car' ? $drive_est_duration : $bike_est_duration,
    //                 'vehicle_category_type' => $trip->VehicleCategory->type === 'car' ? 'Car' : 'Motorbike',
    //                 'estimated_fare' => round($est_fare, $points),
    //                 'discount_fare' => round($discountEstFare, $points),
    //                 'discount_amount' => round(($discount ? $discount['discount_amount'] : 0), $points),
    //                 'coupon_applicable' => $coupon,
    //                 'request_type' => $tripRequest['type'],
    //                 'encoded_polyline' => $trip->VehicleCategory->type === 'car' ? $drive_polyline : $bike_polyline,
    //                 'return_fee' => 0,
    //                 'extra_estimated_fare' => round($extraEstFare ?? 0, $points),
    //                 'extra_discount_fare' => round($extraDiscountEstFare ?? 0, $points),
    //                 'extra_discount_amount' => round(($extraDiscount ? $extraDiscount['discount_amount'] : 0), $points),
    //                 'extra_return_fee' => 0,
    //                 'extra_cancellation_fee' => 0,
    //                 'extra_fare_amount' => round(($extraEstFareAmount ?? 0), $points),
    //                 'extra_fare_fee' => $extraFare ? $extraFare['extraFareFee'] : 0,
    //                 'extra_fare_reason' => $extraFare ? $extraFare['extraFareReason'] : ""
    //             ];

    //         });

    //     }

    //     return $estimated_fare;
    // }
    
     public function calculateFinalFare($trip, $fare): array
    {
        $admin_trip_commission = (double)get_cache('trip_commission') ?? 0;
        // Load default fare from zone
        $default_fare = $trip->zone->defaultFare ?? null;
        $minimum_distance_threshold = $default_fare->minimum_distance_threshold ?? 0;
        $fixed_price_below_threshold = $default_fare->fixed_price_below_threshold ?? 0;
    
        // Parcel start
        if ($trip->type == 'parcel') {
            $vat_percent = (double)get_cache('vat_percent') ?? 1;
            $distance_in_km = $trip->estimated_distance; // استخدام المسافة المخزنة
    
            // Apply default fare logic for parcels
            if ($distance_in_km <= $minimum_distance_threshold) {
                $actual_fare = $fixed_price_below_threshold;
            } else {
                // استخدام الأجرة المحسوبة بناءً على المسافة إذا كانت أكبر من العتبة
                $actual_fare = $trip->actual_fare / (1 + ($vat_percent / 100)); // الأجرة بدون الضريبة
            }
    
            $parcel_payment = $actual_fare;
            $vat = round(($vat_percent * $parcel_payment) / 100, 2);
            $fee = TripRequestFee::where('trip_request_id', $trip->id)->first();
            $fee->vat_tax = $vat;
            $fee->admin_commission = (($parcel_payment * $admin_trip_commission) / 100) + $vat;
            $fee->save();
    
            return [
                'extra_fare_amount' => round($trip->extra_fare_amount, 2),
                'actual_fare' => round($actual_fare, 2),
                'final_fare' => round($parcel_payment + $vat, 2),
                'waiting_fee' => 0,
                'idle_fare' => 0,
                'cancellation_fee' => 0,
                'delay_fee' => 0,
                'vat' => $vat,
                'actual_distance' => $distance_in_km,
            ];
        }
    
        $fee = TripRequestFee::query()->firstWhere('trip_request_id', $trip->id);
        $time = TripRequestTime::query()->firstWhere('trip_request_id', $trip->id);
    
        $bid_on_fare = FareBidding::where('trip_request_id', $trip->id)->where('is_ignored', 0)->first();
        $current_status = $trip->current_status;
        $cancellation_fee = 0;
        $waiting_fee = 0;
        $distance_in_km = 0;
    
        $drivingMode = $trip?->vehicleCategory?->type === 'motor_bike' ? 'TWO_WHEELER' : 'DRIVE';
        $drop_coordinate = [
            $trip->coordinate->drop_coordinates->latitude,
            $trip->coordinate->drop_coordinates->longitude
        ];
        $destination_coordinate = [
            $trip->coordinate->destination_coordinates->latitude,
            $trip->coordinate->destination_coordinates->longitude
        ];
        $pickup_coordinate = [
            $trip->coordinate->pickup_coordinates->latitude,
            $trip->coordinate->pickup_coordinates->longitude
        ];
        $intermediate_coordinate = [];
        if ($trip->coordinate->is_reached_1) {
            if ($trip->coordinate->is_reached_2) {
                $intermediate_coordinate[1] = [
                    $trip->coordinate->int_coordinate_2->latitude,
                    $trip->coordinate->int_coordinate_2->longitude
                ];
            }
            $intermediate_coordinate[0] = [
                $trip->coordinate->int_coordinate_1->latitude,
                $trip->coordinate->int_coordinate_1->longitude
            ];
        }
    
        if ($current_status === 'cancelled') {
            $route = getRoutes($pickup_coordinate, $drop_coordinate, $intermediate_coordinate, [$drivingMode]);
            $distance_in_km = $route[0]['distance'];
    
            // Apply default fare logic for cancelled trips
            if ($distance_in_km <= $minimum_distance_threshold) {
                $actual_fare = $fixed_price_below_threshold;
            } else {
                $distance_wise_fare_cancelled = $fare->base_fare_per_km * $distance_in_km;
                $actual_fare = $fare->base_fare + $distance_wise_fare_cancelled;
            }
            if ($trip->extra_fare_fee > 0) {
                $extraFare = ($actual_fare * $trip->extra_fare_fee) / 100;
                $actual_fare += $extraFare;
            }
    
            if ($trip->fee->cancelled_by === 'customer') {
                $cancellation_percent = $fare->cancellation_fee_percent;
                $cancellation_fee = max((($cancellation_percent * ($distance_in_km <= $minimum_distance_threshold ? $fixed_price_below_threshold : $distance_wise_fare_cancelled)) / 100), $fare->min_cancellation_fee);
            }
        } elseif ($current_status == 'completed') {
            $route = getRoutes($pickup_coordinate, $drop_coordinate, $intermediate_coordinate, [$drivingMode]);
            $distance_in_km = $route[0]['distance'];
    
            // Apply default fare logic for completed trips
            if ($distance_in_km <= $minimum_distance_threshold) {
                $actual_fare = $fixed_price_below_threshold;
            } else {
                $distance_wise_fare_completed = $fare->base_fare_per_km * $distance_in_km;
                $actual_fare = $fare->base_fare + $distance_wise_fare_completed;
            }
            if ($trip->extra_fare_fee > 0) {
                $extraFare = ($actual_fare * $trip->extra_fare_fee) / 100;
                $actual_fare += $extraFare;
            }
            $vat_percent = (double)get_cache('vat_percent') ?? 1;
            $distanceFare = $trip->rise_request_count > 0 ? $trip->actual_fare / (1 + ($vat_percent / 100)) : $actual_fare;
            $actual_fare = $bid_on_fare ? $bid_on_fare->bid_fare / (1 + ($vat_percent / 100)) : $distanceFare;
        } else {
            $actual_fare = 0;
        }
    
        $trip_started = Carbon::parse($trip->tripStatus->ongoing);
        $trip_ended = Carbon::parse($trip->tripStatus->$current_status);
        $actual_time = $trip_started->diffInMinutes($trip_ended);
    
        // Idle time & fee calculation
        $idle_fee_buffer = (double)get_cache('idle_fee') ?? 0;
        $idle_diff = $trip->time->idle_time - $idle_fee_buffer;
        $idle_time = max($idle_diff, 0);
        $idle_fee = $idle_time * $fare->idle_fee_per_min;
    
        // Delay time & fee calculation
        $delay_fee_buffer = (double)get_cache('delay_fee') ?? 0;
        $delay_diff = $actual_time - ($trip->time->estimated_time + $delay_fee_buffer + $trip->time->idle_time);
        $delay_time = max($delay_diff, 0);
        $delay_fee = $delay_time * $fare->trip_delay_fee_per_min;
    
        $vat_percent = (double)get_cache('vat_percent') ?? 1;
        $final_fare_without_tax = ($actual_fare + $waiting_fee + $idle_fee + $cancellation_fee + $delay_fee);
        $vat = ($final_fare_without_tax * $vat_percent) / 100;
    
        $fee->vat_tax = round($vat, 2);
        $fee->admin_commission = (($final_fare_without_tax * $admin_trip_commission) / 100) + $vat;
        $fee->cancellation_fee = round($cancellation_fee, 2);
        $time->actual_time = $actual_time;
        $time->idle_time = $idle_time;
        $fee->idle_fee = round($idle_fee, 2);
        $time->delay_time = $delay_time;
        $fee->delay_fee = round($delay_fee, 2);
        $fee->save();
        $time->save();
    
        return [
            'extra_fare_amount' => round($extraFare ?? 0, 2),
            'actual_fare' => round($actual_fare, 2),
            'final_fare' => round($final_fare_without_tax + $vat, 2),
            'waiting_fee' => $waiting_fee,
            'idle_fare' => $idle_fee,
            'cancellation_fee' => $cancellation_fee,
            'delay_fee' => $delay_fee,
            'vat' => $vat,
            'actual_distance' => $distance_in_km
        ];
    }
    
    public function estimatedFare($tripRequest, $routes, $zone_id, $zone, $tripFare = null, $area_id = null, $beforeCreate = false): mixed
    {
        // Load default fare from zone
        $default_fare = $zone->defaultFare;
        $minimum_distance_threshold = $default_fare->minimum_distance_threshold ?? 0;
        $fixed_price_below_threshold = $default_fare->fixed_price_below_threshold ?? 0;
    
        if ($tripRequest['type'] == 'parcel') {
            abort_if(boolean: empty($tripFare), code: 403, message: translate('invalid_or_missing_information'));
            abort_if(boolean: empty($tripFare->fares), code: 403, message: translate('no_fares_found'));
            $extraFare = $this->checkZoneExtraFare($zone);
    
            $distance = (double)$routes[0]['distance'];
            // Apply default fare logic for parcel
            if ($distance <= $minimum_distance_threshold) {
                $est_fare = $fixed_price_below_threshold;
            } else {
                $distance_wise_fare = $tripFare->fares[0]->fare_per_km * $distance;
                $est_fare = $tripFare->fares[0]->base_fare + $distance_wise_fare;
            }
    
            if (!empty($extraFare)) {
                $extraEstFareAmount = ($est_fare * $extraFare['extraFareFee']) / 100;
                $extraEstFare = $extraEstFareAmount + $est_fare;
                $extraReturnFee = ($extraEstFare * $tripFare->fares[0]->return_fee) / 100;
                $extraCancellationFee = ($extraEstFare * $tripFare->fares[0]->cancellation_fee) / 100;
            }
            $returnFee = ($est_fare * $tripFare->fares[0]->return_fee) / 100;
            $cancellationFee = ($est_fare * $tripFare->fares[0]->cancellation_fee) / 100;
            $user = auth('api')->user();
            $vat_percent = (double)get_cache('vat_percent') ?? 1;
            $discount = $this->getEstimatedDiscount(user: $user, zoneId: $zone_id, tripType: $tripRequest['type'], vehicleCategoryId: null, estimatedAmount: $est_fare, beforeCreate: $beforeCreate);
            $discountEstFare = $est_fare - ($discount ? $discount['discount_amount'] : 0);
            $extraDiscount = null;
            if (!empty($extraFare)) {
                $extraDiscount = $this->getEstimatedDiscount(user: $user, zoneId: $zone_id, tripType: $tripRequest['type'], vehicleCategoryId: null, estimatedAmount: $extraEstFare, beforeCreate: $beforeCreate);
                $extraDiscountEstFare = $extraEstFare - ($extraDiscount ? $extraDiscount['discount_amount'] : 0);
                $coupon = $this->getEstimatedCouponDiscount(user: $user, zoneId: $zone_id, tripType: $tripRequest['type'], vehicleCategoryId: null, estimatedAmount: $extraDiscountEstFare);
                $extraDiscountFareVat = ($extraDiscountEstFare * $vat_percent) / 100;
                $extraDiscountEstFare += $extraDiscountFareVat;
                $extraVat = ($extraEstFare * $vat_percent) / 100;
                $extraEstFare += $extraVat;
            } else {
                $coupon = $this->getEstimatedCouponDiscount(user: $user, zoneId: $zone_id, tripType: $tripRequest['type'], vehicleCategoryId: null, estimatedAmount: $discountEstFare);
            }
            $discountFareVat = ($discountEstFare * $vat_percent) / 100;
            $discountEstFare += $discountFareVat;
            $vat = ($est_fare * $vat_percent) / 100;
            $est_fare += $vat;
            $points = (int)getSession('currency_decimal_point') ?? 0;
            $estimated_fare = [
                'id' => $tripFare->id,
                'zone_id' => $zone->id,
                'area_id' => $area_id,
                'base_fare' => $distance <= $minimum_distance_threshold ? $fixed_price_below_threshold : $tripFare->base_fare,
                'base_fare_per_km' => $tripFare->fares[0]->fare_per_km,
                'fare' => $tripFare->fares,
                'estimated_distance' => $distance,
                'estimated_duration' => $routes[0]['duration'],
                'estimated_fare' => round($est_fare, $points),
                'discount_fare' => round($discountEstFare, $points),
                'discount_amount' => round(($discount ? $discount['discount_amount'] : 0), $points),
                'coupon_applicable' => $coupon,
                'request type' => $tripRequest['type'],
                'encoded_polyline' => $routes[0]['encoded_polyline'],
                'return_fee' => $returnFee,
                'cancellation_fee' => $cancellationFee,
                'extra_estimated_fare' => round($extraEstFare ?? 0, $points),
                'extra_discount_fare' => round($extraDiscountEstFare ?? 0, $points),
                'extra_discount_amount' => round(($extraDiscount ? $extraDiscount['discount_amount'] : 0), $points),
                'extra_return_fee' => $extraReturnFee ?? 0,
                'extra_cancellation_fee' => $extraCancellationFee ?? 0,
                'extra_fare_amount' => round(($extraEstFareAmount ?? 0), $points),
                'extra_fare_fee' => $extraFare ? $extraFare['extraFareFee'] : 0,
                'extra_fare_reason' => $extraFare ? $extraFare['extraFareReason'] : ""
            ];
        } else {
            $estimated_fare = $tripFare->map(function ($trip) use ($routes, $tripRequest, $area_id, $beforeCreate, $zone, $minimum_distance_threshold, $fixed_price_below_threshold) {
                $extraFare = $this->checkZoneExtraFare($zone);
                $points = (int)getSession('currency_decimal_point') ?? 0;
                foreach ($routes as $route) {
                    if ($route['drive_mode'] === 'DRIVE') {
                        $distance = $route['distance'];
                        // Apply default fare logic for car
                        if ($distance <= $minimum_distance_threshold) {
                            $est_fare = $fixed_price_below_threshold;
                        } else {
                            $drive_fare = $trip->base_fare_per_km * $distance;
                            $est_fare = $trip->base_fare + $drive_fare;
                        }
                        $drive_est_distance = (double)$route['distance'];
                        $drive_est_duration = $route['duration'];
                        $drive_polyline = $route['encoded_polyline'];
                    } elseif ($route['drive_mode'] === 'TWO_WHEELER') {
                        $distance = $route['distance'];
                        // Apply default fare logic for motorbike
                        if ($distance <= $minimum_distance_threshold) {
                            $est_fare = $fixed_price_below_threshold;
                        } else {
                            $bike_fare = $trip->base_fare_per_km * $distance;
                            $est_fare = $trip->base_fare + $bike_fare;
                        }
                        $bike_est_distance = (double)$route['distance'];
                        $bike_est_duration = $route['duration'];
                        $bike_polyline = $route['encoded_polyline'];
                    }
                }
                if (!empty($extraFare)) {
                    $extraEstFareAmount = ($est_fare * $extraFare['extraFareFee']) / 100;
                    $extraEstFare = $extraEstFareAmount + $est_fare;
                }
                $user = auth('api')->user();
                $discount = $this->getEstimatedDiscount(user: $user, zoneId: $zone->id, tripType: $tripRequest['type'], vehicleCategoryId: $trip->vehicleCategory->id, estimatedAmount: $est_fare, beforeCreate: $beforeCreate);
                $vat_percent = (double)get_cache('vat_percent') ?? 1;
                $discountEstFare = $est_fare - ($discount ? $discount['discount_amount'] : 0);
                $extraDiscount = null;
                if (!empty($extraFare)) {
                    $extraDiscount = $this->getEstimatedDiscount(user: $user, zoneId: $zone->id, tripType: $tripRequest['type'], vehicleCategoryId: $trip->vehicleCategory->id, estimatedAmount: $extraEstFare, beforeCreate: $beforeCreate);
                    $extraDiscountEstFare = $extraEstFare - ($extraDiscount ? $extraDiscount['discount_amount'] : 0);
                    $coupon = $this->getEstimatedCouponDiscount(user: $user, zoneId: $zone->id, tripType: $tripRequest['type'], vehicleCategoryId: $trip->vehicleCategory->id, estimatedAmount: $extraDiscountEstFare);
                    $extraDiscountFareVat = ($extraDiscountEstFare * $vat_percent) / 100;
                    $extraDiscountEstFare += $extraDiscountFareVat;
                    $extraVat = ($extraEstFare * $vat_percent) / 100;
                    $extraEstFare += $extraVat;
                } else {
                    $coupon = $this->getEstimatedCouponDiscount(user: $user, zoneId: $zone->id, tripType: $tripRequest['type'], vehicleCategoryId: $trip->vehicleCategory->id, estimatedAmount: $discountEstFare);
                }
                $discountFareVat = ($discountEstFare * $vat_percent) / 100;
                $discountEstFare += $discountFareVat;
                $vat = ($est_fare * $vat_percent) / 100;
                $est_fare += $vat;
                return [
                    "id" => $trip->id,
                    "zone_id" => $zone->id,
                    'area_id' => $area_id,
                    "vehicle_category_id" => $trip->vehicle_category_id,
                    'base_fare' => $distance <= $minimum_distance_threshold ? $fixed_price_below_threshold : $trip->base_fare,
                    'base_fare_per_km' => $trip->base_fare_per_km,
                    'fare' => $trip->VehicleCategory->type === 'car' ? round(($distance <= $minimum_distance_threshold ? 0 : $drive_fare), 2) : round(($distance <= $minimum_distance_threshold ? 0 : $bike_fare), 2),
                    'estimated_distance' => $trip->VehicleCategory->type === 'car' ? $drive_est_distance : $bike_est_distance,
                    'estimated_duration' => $trip->VehicleCategory->type === 'car' ? $drive_est_duration : $bike_est_duration,
                    'vehicle_category_type' => $trip->VehicleCategory->type === 'car' ? 'Car' : 'Motorbike',
                    'estimated_fare' => round($est_fare, $points),
                    'discount_fare' => round($discountEstFare, $points),
                    'discount_amount' => round(($discount ? $discount['discount_amount'] : 0), $points),
                    'coupon_applicable' => $coupon,
                    'request_type' => $tripRequest['type'],
                    'encoded_polyline' => $trip->VehicleCategory->type === 'car' ? $drive_polyline : $bike_polyline,
                    'return_fee' => 0,
                    'extra_estimated_fare' => round($extraEstFare ?? 0, $points),
                    'extra_discount_fare' => round($extraDiscountEstFare ?? 0, $points),
                    'extra_discount_amount' => round(($extraDiscount ? $extraDiscount['discount_amount'] : 0), $points),
                    'extra_return_fee' => 0,
                    'extra_cancellation_fee' => 0,
                    'extra_fare_amount' => round(($extraEstFareAmount ?? 0), $points),
                    'extra_fare_fee' => $extraFare ? $extraFare['extraFareFee'] : 0,
                    'extra_fare_reason' => $extraFare ? $extraFare['extraFareReason'] : ""
                ];
            });
        }
    
        return $estimated_fare;
    }

    public function checkZoneExtraFare($zone)
    {
        $extraFareFee = 0;
        $extraFareReason = "";
        if ($zone->extra_fare_status) {
            $extraFareFee = $zone->extra_fare_fee;
            $extraFareReason = $zone->extra_fare_reason;
        }
        if ($extraFareFee > 0) {
            return [
                'extraFareFee' => $extraFareFee,
                'extraFareReason' => $extraFareReason,
            ];
        }
        return [];
    }

}

