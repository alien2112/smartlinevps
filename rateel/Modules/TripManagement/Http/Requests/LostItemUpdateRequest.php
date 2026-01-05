<?php

namespace Modules\TripManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\TripManagement\Entities\LostItem;

class LostItemUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'status' => 'nullable|in:' . implode(',', LostItem::getStatuses()),
            'driver_response' => 'nullable|in:found,not_found',
            'driver_notes' => 'nullable|string|max:1000',
            'admin_notes' => 'nullable|string|max:2000',
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
