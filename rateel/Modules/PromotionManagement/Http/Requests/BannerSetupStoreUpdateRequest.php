<?php

namespace Modules\PromotionManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class BannerSetupStoreUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $id = $this->id;
        return [
            'banner_title' => 'required|max:255',
            'short_desc' => 'required|max:900',
            'time_period' => 'required',
            'banner_type' => 'required|in:ad,coupon,discount,promotion',
            'redirect_link' => [
                Rule::requiredIf($this->input('banner_type') === 'ad'),
                'max:255'
            ],
            'coupon_code' => [
                Rule::requiredIf($this->input('banner_type') === 'coupon'),
                'max:255'
            ],
            'discount_code' => [
                Rule::requiredIf($this->input('banner_type') === 'discount'),
                'max:255'
            ],
            'coupon_id' => 'nullable|exists:coupon_setups,id',
            'is_promotion' => 'nullable|boolean',
            'start_date' => 'exclude_if:time_period,all_time|required|after_or_equal:today',
            'end_date' => 'exclude_if:time_period,all_time|required|after_or_equal:start_date',
            'target_audience' => 'required|in:driver,customer',
            'banner_image' => [
                Rule::requiredIf(empty($id)),
                'image',
                'mimes:png,jpg,jpeg,webp',
                'max:5000']
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
