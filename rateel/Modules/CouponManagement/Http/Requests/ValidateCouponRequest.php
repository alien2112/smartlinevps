<?php

declare(strict_types=1);

namespace Modules\CouponManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValidateCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => 'required|string|max:50',
            'fare' => 'required|numeric|min:0',
            'city_id' => 'required|string|max:50',
            'service_type' => 'required|string|max:50',
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Coupon code is required',
            'fare.required' => 'Estimated fare is required',
            'fare.numeric' => 'Fare must be a valid number',
            'city_id.required' => 'City ID is required',
            'service_type.required' => 'Service type is required',
        ];
    }

    public function getEstimateContext(): array
    {
        return [
            'fare' => (float) $this->input('fare'),
            'city_id' => $this->input('city_id'),
            'service_type' => $this->input('service_type'),
        ];
    }
}
