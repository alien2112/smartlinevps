<?php

namespace Modules\FareManagement\Service;

use App\Service\BaseService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Modules\FareManagement\Repository\ParcelFareRepositoryInterface;

class ParcelFareService extends BaseService implements Interface\ParcelFareServiceInterface
{
    protected $parcelFareRepository;

    public function __construct(ParcelFareRepositoryInterface $parcelFareRepository)
    {
        parent::__construct($parcelFareRepository);
        $this->parcelFareRepository = $parcelFareRepository;
    }

    public function create(array $data): ?Model
    {
        DB::beginTransaction();
        $fare = $this->parcelFareRepository->findOneBy(criteria: ['zone_id' => $data['zone_id']]);
        $parcelFareData = [
            "zone_id" => $data['zone_id'],
            "base_fare" => $data['base_fare'],
            "return_fee" => $data['return_fee'],
            "cancellation_fee" => $data['cancellation_fee'],
            "base_fare_per_km" => 0,
            "cancellation_fee_percent" => 0,
            "min_cancellation_fee" => 0,
        ];
        if (is_null($fare)) {
            $parcelFare = $this->parcelFareRepository->create(data: $parcelFareData);
        } else {
            $parcelFare = $this->parcelFareRepository->update(id: $fare->id, data: $parcelFareData);
            $fare->fares()->delete();
        }


        foreach ($data['parcel_category'] as $category) {
            if (array_key_exists('weight_' . $category, $data)) {
                foreach ($data['parcel_weight'] as $weight) {
                    if (array_key_exists($weight['id'], $data['weight_' . $category])) {
                        $parcelFare?->fares()->create([
                            'parcel_weight_id' => $weight->id,
                            'parcel_category_id' => $category,
                            'base_fare' => $data['base_fare_' . $category] ?? 0,
                            'return_fee' => $data['return_fee'] ?? 0,
                            'cancellation_fee' => $data['cancellation_fee'] ?? 0,
                            'fare_per_km' => $data['weight_' . $category][$weight->id] ?? 0,
                            'zone_id' => $data['zone_id']
                        ]);
                    }
                }
            }
        }
        DB::commit();
        return $parcelFare;
    }
}
