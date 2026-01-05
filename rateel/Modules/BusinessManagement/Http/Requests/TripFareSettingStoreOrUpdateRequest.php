<?php

namespace Modules\BusinessManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class TripFareSettingStoreOrUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'type' => 'required',
            'idle_fee' => [Rule::requiredIf(function () {
                return $this->input('type') === TRIP_FARE_SETTINGS;
            }), 'gt:0'],
            'delay_fee' => [Rule::requiredIf(function () {
                return $this->input('type') === TRIP_FARE_SETTINGS;
            }), 'gt:0'],
            // Make these nullable - only validate if present
            'add_intermediate_points' => 'nullable|boolean',
            'trip_request_active_time' => 'nullable|numeric|gt:0|lte:30',
            'lost_item_response_timeout_hours' => 'nullable|integer|min:1|max:168',
            'trip_push_notification' => 'sometimes',
            'bidding_push_notification' => 'sometimes',
            "driver_otp_confirmation_for_trip" => "nullable|string|in:on",

            // Normal trip pricing
            'normal_price_per_km' => 'nullable|numeric|min:0',
            'normal_price_per_km_status' => 'nullable',
            'normal_min_price' => 'nullable|numeric|min:0',
            'normal_min_price_status' => 'nullable',

            // Travel mode pricing
            'travel_price_per_km' => 'nullable|numeric|min:0',
            'travel_price_per_km_status' => 'nullable',
            'travel_price_multiplier' => 'nullable|numeric|min:1.0|max:3.0',
            'travel_price_multiplier_status' => 'nullable',
            'travel_recommended_multiplier' => 'nullable|numeric|min:1.0|max:2.0',
            'travel_recommended_multiplier_status' => 'nullable',
            'travel_search_radius' => 'nullable|integer|min:10|max:200',
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return Auth::check();
    }
}
