<?php

namespace Modules\TripManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ParcelRefundRequestRefundedStoreRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        // Get the parcel refund to check the trip's paid fare
        $parcelRefund = \Modules\TripManagement\Entities\ParcelRefund::with('tripRequest')
            ->find($this->route('id'));
        $maxRefundAmount = $parcelRefund?->tripRequest?->paid_fare ?? 0;
        
        return [
            'refund_amount' => [
                'required',
                'numeric',
                'min:0.01',
                'max:' . $maxRefundAmount, // SECURITY: Prevent refunds exceeding original trip amount
            ],
            'refund_method' => ['required', 'string', Rule::in(['manually', 'wallet', 'coupon'])],
            'refund_note' => 'required|string|max:150',
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::check();
    }
}
