<?php

namespace Modules\VehicleManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VehicleCategoryStoreUpdateRequest extends FormRequest
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
            'category_name' => 'required|string|min:3|max:255|unique:vehicle_categories,name,' . $id,
            'short_desc' => 'required|max:900',
            'type' => 'required|in:car,motor_bike',
            'category_image' => [
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
        return true;
    }
}
