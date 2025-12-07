<?php

namespace Modules\TripManagement\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\ParcelManagement\Transformers\InformationResource;
use Modules\ParcelManagement\Transformers\UserResource;
use Modules\PromotionManagement\Transformers\CouponResource;
use Modules\PromotionManagement\Transformers\DiscountResource;
use Modules\UserManagement\Transformers\CustomerResource;
use Modules\UserManagement\Transformers\DriverResource;
use Modules\VehicleManagement\Transformers\VehicleModelResource;
use Modules\VehicleManagement\Transformers\VehicleCategoryResource;
use Modules\VehicleManagement\Transformers\VehicleResource;
use Modules\ZoneManagement\Transformers\ZoneResource;

class ParcelRefundProofResource extends JsonResource
{


    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'file' => asset('storage/app/public/parcel/proof/'.$this->attachment),
        ];
    }
}
