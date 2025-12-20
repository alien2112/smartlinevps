<?php

namespace Modules\TripManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\TripManagement\Entities\LostItem;

class LostItemStoreRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'trip_request_id' => 'required|uuid',
            'category' => 'required|in:' . implode(',', LostItem::getCategories()),
            'description' => 'required|string|max:1000',
            'image' => 'nullable|image|max:5120', // 5MB max
            'contact_preference' => 'nullable|in:in_app,phone',
            'item_lost_at' => 'nullable|date',
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

    /**
     * Custom error messages
     */
    public function messages()
    {
        return [
            'trip_request_id.required' => 'Trip ID is required',
            'trip_request_id.uuid' => 'Invalid trip ID format',
            'category.required' => 'Item category is required',
            'category.in' => 'Invalid item category',
            'description.required' => 'Item description is required',
            'description.max' => 'Description cannot exceed 1000 characters',
            'image.image' => 'File must be an image',
            'image.max' => 'Image size cannot exceed 5MB',
        ];
    }
}
