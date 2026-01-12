<?php

namespace Modules\VehicleManagement\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;
use Modules\FareManagement\Transformers\TripFareResource;

class VehicleCategoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request
     * @return array
     */
    public function toArray($request)
    {
        // Define allowed self-selectable categories for driver onboarding
        // Only Taxi, Scooter, and Uncategorized can be self-selected
        // Travel and premium categories require admin approval
        $allowedCategories = [
            'd4d1e8f1-c716-4cff-96e1-c0b312a1a58b', // Taxi
            '89060926-153c-4c43-a881-c2ea0eb47402', // سكوتر (Scooter)
            'd8e5a6e1-bf60-46a8-959a-22a18bdcd764', // Uncategorized
        ];

        return [
            'id' => $this->id,
            'name' => $this->name,
            'image' => getMediaUrl($this->image),
            'type' => $this->type,
            'self_selectable' => in_array($this->id, $allowedCategories),
            'requires_admin_assignment' => !in_array($this->id, $allowedCategories),
            'fare' => TripFareResource::collection($this->whenLoaded('tripFares'))
        ];
    }
}
