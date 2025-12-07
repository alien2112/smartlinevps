<?php

namespace Modules\VehicleManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class VehicleApiStoreUpdateRequest extends FormRequest
{
    public function rules()
    {
        $id = $this->id;

        return [
            'brand_id' => 'required',
            'model_id' => 'required',
            'year_id' => 'required',
            // 'category_id' => 'required',
            'driver_id' => Rule::requiredIf(empty($id)),
            'ownership' => Rule::requiredIf(empty($id)),
            'licence_plate_number' => 'required',
            'licence_plate_char' => 'required',
            'licence_expire_date' => 'required|date',
            'vin_number' => 'sometimes',
            'transmission' => 'sometimes',
            'parcel_weight_capacity' => 'sometimes',
            'fuel_type' => Rule::requiredIf(empty($id)),
            // 'other_documents' => Rule::requiredIf(empty($id)),
        ];
    }

    public function authorize()
    {
        return Auth::check();
    }
}
