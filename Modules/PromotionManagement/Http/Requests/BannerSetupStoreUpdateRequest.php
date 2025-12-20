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
        $maxSize = config('image.banner.max_size', config('image.max_size', 500));
        $mimes = implode(',', config('image.banner.mimes', config('image.allowed_mimes', ['png', 'jpg', 'jpeg', 'webp'])));
        
        return [
            'banner_title' => 'required|max:255',
            'short_desc' => 'required|max:900',
            'time_period' => 'required',
            'redirect_link' => 'required|max:255',
            'start_date' => 'exclude_if:time_period,all_time|required|after_or_equal:today',
            'end_date' => 'exclude_if:time_period,all_time|required|after_or_equal:start_date',
            'target_audience' => 'required|in:driver,customer',
            'banner_image' => [
                Rule::requiredIf(empty($id)),
                'image',
                "mimes:{$mimes}",
                "max:{$maxSize}"]
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
