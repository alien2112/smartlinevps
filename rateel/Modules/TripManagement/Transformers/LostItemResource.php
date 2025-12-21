<?php

namespace Modules\TripManagement\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LostItemResource extends JsonResource
{
    public function toArray(Request $request)
    {
        $isDriverContext = $request->user()?->user_type === 'driver';

        return [
            'id' => $this->id,
            'trip_request_id' => $this->trip_request_id,
            'category' => $this->category,
            'description' => $this->description,
            'image_url' => $this->image_url,
            'status' => $this->status,
            'driver_response' => $this->driver_response,
            'driver_notes' => $this->driver_notes,
            'contact_preference' => $this->contact_preference,
            'item_lost_at' => $this->item_lost_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),

            // Trip info
            'trip' => $this->whenLoaded('trip', function () {
                return [
                    'id' => $this->trip->id,
                    'ref_id' => $this->trip->ref_id,
                    'pickup_address' => $this->trip->coordinate?->pickup_address,
                    'destination_address' => $this->trip->coordinate?->destination_address,
                    'completed_at' => $this->trip->time?->completed_at?->toIso8601String(),
                ];
            }),

            // Customer info (with masked phone for privacy)
            'customer' => $this->whenLoaded('customer', function () use ($isDriverContext) {
                return [
                    'id' => $this->customer->id,
                    'name' => $this->customer->first_name . ' ' . $this->customer->last_name,
                    'phone' => $isDriverContext ? $this->customer->phone : $this->maskPhoneNumber($this->customer->phone),
                    'phone_unmasked' => $isDriverContext ? $this->customer->phone : null,
                    'profile_image' => $this->customer->profile_image,
                ];
            }),

            // Driver info (full phone for direct contact about lost items)
            'driver' => $this->whenLoaded('driver', function () {
                return [
                    'id' => $this->driver->id,
                    'name' => $this->driver->first_name . ' ' . $this->driver->last_name,
                    'phone' => $this->driver->phone, // Full number for customer to contact driver
                    'profile_image' => $this->driver->profile_image,
                ];
            }),

            // Status timeline
            'status_logs' => $this->whenLoaded('statusLogs', function () {
                return $this->statusLogs->map(function ($log) {
                    return [
                        'id' => $log->id,
                        'from_status' => $log->from_status,
                        'to_status' => $log->to_status,
                        'notes' => $log->notes,
                        'changed_by' => $log->changedBy?->first_name . ' ' . $log->changedBy?->last_name,
                        'changed_at' => $log->created_at?->toIso8601String(),
                    ];
                });
            }),
        ];
    }

    /**
     * Mask phone number for privacy
     */
    protected function maskPhoneNumber(?string $phone): ?string
    {
        if (empty($phone)) {
            return null;
        }

        $length = strlen($phone);
        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        return substr($phone, 0, 3) . str_repeat('*', $length - 6) . substr($phone, -3);
    }
}
