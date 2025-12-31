<?php

declare(strict_types=1);

namespace Modules\CouponManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\CouponManagement\Entities\Coupon;

class CreateCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware
    }

    public function rules(): array
    {
        return [
            'code' => 'required|string|max:50|unique:coupons,code',
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',

            'type' => 'required|in:PERCENT,FIXED,FREE_RIDE_CAP',
            'value' => 'required|numeric|min:0',
            'max_discount' => 'nullable|numeric|min:0',
            'min_fare' => 'nullable|numeric|min:0',

            'global_limit' => 'nullable|integer|min:1',
            'per_user_limit' => 'required|integer|min:1|max:100',

            'starts_at' => 'required|date|after_or_equal:today',
            'ends_at' => 'required|date|after:starts_at',

            'allowed_city_ids' => 'nullable|array',
            'allowed_city_ids.*' => 'string|max:50',
            'allowed_service_types' => 'nullable|array',
            'allowed_service_types.*' => 'string|max:50',

            'eligibility_type' => 'required|in:ALL,TARGETED,SEGMENT',
            'segment_key' => 'required_if:eligibility_type,SEGMENT|nullable|string|max:50',
            'target_user_ids' => 'required_if:eligibility_type,TARGETED|nullable|array',
            'target_user_ids.*' => 'uuid|exists:users,id',

            'is_active' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'code.unique' => 'This coupon code already exists',
            'type.in' => 'Coupon type must be PERCENT, FIXED, or FREE_RIDE_CAP',
            'ends_at.after' => 'End date must be after start date',
            'segment_key.required_if' => 'Segment key is required for SEGMENT eligibility',
            'target_user_ids.required_if' => 'Target user IDs are required for TARGETED eligibility',
            'value.min' => 'Value must be greater than or equal to 0',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Uppercase the code
        if ($this->has('code')) {
            $this->merge([
                'code' => strtoupper(trim($this->input('code'))),
            ]);
        }

        // Validate percent type value is <= 100
        if ($this->input('type') === Coupon::TYPE_PERCENT && $this->input('value') > 100) {
            $this->merge(['value' => 100]);
        }
    }
}
