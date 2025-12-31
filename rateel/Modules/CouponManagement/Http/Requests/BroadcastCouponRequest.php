<?php

declare(strict_types=1);

namespace Modules\CouponManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\CouponManagement\Entities\Coupon;

class BroadcastCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $validSegments = implode(',', [
            Coupon::SEGMENT_INACTIVE_30_DAYS,
            Coupon::SEGMENT_NEW_USER,
            Coupon::SEGMENT_HIGH_VALUE,
        ]);

        return [
            'target' => 'required|in:all,targeted,segment,user_ids',
            'segment_key' => "required_if:target,segment|nullable|in:{$validSegments}",
            'user_ids' => 'required_if:target,user_ids|nullable|array|max:10000',
            'user_ids.*' => 'uuid|exists:users,id',
            'message_template' => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'target.required' => 'Target type is required',
            'target.in' => 'Target must be: all, targeted, segment, or user_ids',
            'segment_key.required_if' => 'Segment key is required when target is segment',
            'segment_key.in' => 'Invalid segment key',
            'user_ids.required_if' => 'User IDs are required when target is user_ids',
        ];
    }
}
