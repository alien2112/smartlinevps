<?php

declare(strict_types=1);

namespace Modules\CouponManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fcm_token' => 'required|string|max:500',
            'platform' => 'required|in:android,ios,web',
            'device_id' => 'nullable|string|max:100',
            'device_model' => 'nullable|string|max:100',
            'app_version' => 'nullable|string|max:20',
        ];
    }

    public function messages(): array
    {
        return [
            'fcm_token.required' => 'FCM token is required',
            'fcm_token.max' => 'FCM token is too long',
            'platform.required' => 'Platform is required',
            'platform.in' => 'Platform must be android, ios, or web',
        ];
    }
}
