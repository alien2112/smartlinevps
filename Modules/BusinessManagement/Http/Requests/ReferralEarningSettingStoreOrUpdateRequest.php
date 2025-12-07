<?php

namespace Modules\BusinessManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ReferralEarningSettingStoreOrUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'customer_referral_earning_status' => 'nullable',
            'customer_share_code_earning' => [Rule::requiredIf(function () {
                return $this->customer_referral_earning_status ?? false;
            })],'numeric','min:0','max:99999999',
            'customer_first_ride_discount_status' => 'nullable',
            'customer_discount_amount' => [Rule::requiredIf(function () {
                return $this->customer_first_ride_discount_status ?? false;
            })],'numeric','min:0','max:99999999',
            'customer_discount_amount_type' => [Rule::requiredIf(function () {
                return $this->customer_first_ride_discount_status ?? false;
            })],
            'customer_discount_validity' => 'nullable',
            'customer_discount_validity_type' => 'nullable',
            'driver_referral_earning_status' => 'nullable',
            'driver_share_code_earning' => [Rule::requiredIf(function () {
                return $this->driver_referral_earning_status ?? false;
            })],'numeric','min:0','max:99999999',
            'driver_use_code_earning' => [Rule::requiredIf(function () {
                return $this->driver_referral_earning_status ?? false;
            })],'numeric','min:0','max:99999999',
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::check();
    }
}
