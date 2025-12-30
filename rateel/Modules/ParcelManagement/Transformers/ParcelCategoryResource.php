<?php

namespace Modules\ParcelManagement\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;
use Modules\FareManagement\Transformers\ParcelFareWeightResource;

class ParcelCategoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'image' => $this->image,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'weightFares' => ParcelFareWeightResource::collection($this->weightFares),
            'created_at' => $this->created_at,
        ];
    }
}
