<?php

namespace Modules\ParcelManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ParcelCategoryStoreOrUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $id = $this->id;
        $maxSize = config('image.icon.max_size', config('image.max_size', 500));
        $mimes = implode(',', config('image.icon.mimes', ['png']));
        
        return [
            'category_name' => 'required|max:255|unique:parcel_categories,name,' . $id,
            'short_desc' => 'required|max:900',
            'category_icon' => [
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
