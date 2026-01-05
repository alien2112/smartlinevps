<?php

declare(strict_types=1);

namespace Modules\CouponManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignCouponUsersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_ids' => 'required|array|min:1|max:10000',
            'user_ids.*' => 'uuid|exists:users,id',
            'notify' => 'nullable|boolean',
            'message_template' => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'user_ids.required' => 'User IDs are required',
            'user_ids.array' => 'User IDs must be an array',
            'user_ids.min' => 'At least one user ID is required',
            'user_ids.max' => 'Maximum 10,000 users can be assigned at once',
            'user_ids.*.uuid' => 'Invalid user ID format',
            'user_ids.*.exists' => 'One or more user IDs do not exist',
        ];
    }
}
