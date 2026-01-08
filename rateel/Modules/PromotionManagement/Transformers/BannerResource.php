<?php

namespace Modules\PromotionManagement\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;

class BannerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request
     * @return array
     */
    public function toArray($request)
    {
        $data = [
            "id" => $this->id,
            "name" => $this->name,
            "description" => $this->description,
            "time_period" => $this->time_period,
            "display_position" => $this->display_position,
            "banner_group" => $this->banner_group,
            "start_date" => $this->start_date,
            "end_date" => $this->end_date,
            "image" => getMediaUrl('promotion/banner/' . $this->image),
            "target_audience" => $this->target_audience,
            "banner_type" => $this->banner_type ?? 'ad',
            "is_promotion" => $this->is_promotion ?? false,
        ];

        // Add redirect_link only for 'ad' type banners
        if ($this->banner_type === 'ad') {
            $data['redirect_link'] = $this->redirect_link;
        }

        // Add coupon_code for 'coupon' type banners
        if ($this->banner_type === 'coupon') {
            $data['coupon_code'] = $this->coupon_code;
            // If linked to a coupon, fetch coupon details
            if ($this->coupon_id && $this->coupon) {
                $data['coupon_details'] = [
                    'id' => $this->coupon->id,
                    'name' => $this->coupon->name,
                    'code' => $this->coupon->coupon_code,
                    'description' => $this->coupon->description,
                    'discount_amount' => $this->coupon->coupon,
                    'amount_type' => $this->coupon->amount_type,
                    'min_trip_amount' => $this->coupon->min_trip_amount,
                    'max_coupon_amount' => $this->coupon->max_coupon_amount,
                ];
            }
        }

        // Add discount_code for 'discount' type banners
        if ($this->banner_type === 'discount') {
            $data['discount_code'] = $this->discount_code;
        }

        return $data;
    }
}
