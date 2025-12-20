<?php

namespace Modules\UserManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DriverStoreOrUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $id = $this->id;
        $maxSize = config('image.max_size', 500);
        $mimes = implode(',', config('image.allowed_mimes', ['jpeg', 'jpg', 'png', 'gif', 'webp']));
        
        return [
            'first_name' => 'required',
            'last_name' => 'required',
            'gender' => 'in:male,female',
            'email' => 'required|email|unique:users,email,' . $id,
            'phone' => 'required|regex:/^([0-9\s\-\+\(\)]*)$/|min:8|max:17|unique:users,phone,' . $id,
            'password' => !is_null($this->password) ? 'required|min:8' : 'nullable',
            'confirm_password' => [
                Rule::requiredIf(function (){
                    return $this->password != null;
                }),
                'same:password'],
            'profile_image' => [
                Rule::requiredIf(empty($id)),
                'image',
                "mimes:{$mimes}",
                "max:{$maxSize}"
            ],
            'identification_type' => 'required|in:passport,driving_license,nid',
            'identification_number' => 'required',
            'identity_images' => 'array',
            'existing_documents' => 'nullable|array',
            'other_documents' => 'array',
            'other_documents.*' => [
                Rule::requiredIf(empty($id)),
                "max:{$maxSize}"
            ],
            'identity_images.*' => [
                Rule::requiredIf(empty($id)),
                'image',
                "mimes:{$mimes}",
                "max:{$maxSize}"
            ],
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
