<?php

namespace Modules\PromotionManagement\Repository\Eloquent;

use App\Repository\Eloquent\BaseRepository;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Modules\PromotionManagement\Entities\CouponSetup;
use Modules\PromotionManagement\Repository\CouponSetupRepositoryInterface;
use Modules\UserManagement\Entities\User;

class CouponSetupRepository extends BaseRepository implements CouponSetupRepositoryInterface
{
    protected $user;

    public function __construct(CouponSetup $model, User $user)
    {
        parent::__construct($model);
        $this->user = $user;
    }


    public function fetchCouponDataCount($dateRange, string $status = null): int
    {
        $model = $this->model;
        $startDate = $endDate = null;

        switch ($dateRange) {
            case THIS_WEEK:
                $startDate = Carbon::now()->startOfWeek();
                $endDate = Carbon::now()->endOfWeek();
                break;
            case THIS_MONTH:
                $startDate = Carbon::now()->startOfMonth();
                $endDate = Carbon::now()->endOfMonth();
                break;
            case THIS_YEAR:
                $startDate = Carbon::now()->firstOfYear();
                $endDate = Carbon::now()->endOfYear();
                break;
            case TODAY:
                $startDate = $endDate = Carbon::today();
                break;
            default:
                $businessStart = $this->user->where(['user_type' => 'super-admin'])->first();
                $startDate = $businessStart->created_at != null ? Carbon::parse($businessStart?->created_at) : Carbon::parse('2023-11-01');
                $endDate = Carbon::today();
                break;
        }

        switch ($status) {
            case "active":
                $data = $model->where(function ($query) use ($startDate, $endDate) {
                    $query->where('start_date', '<=', $endDate)
                        ->where('end_date', '>=', $startDate);
                })->where('is_active', 1)->count();
                break;
            default:
                if ($startDate && $endDate) {
                    $data = $model->where(function ($query) use ($startDate, $endDate) {
                        $query->where('start_date', '<=', $endDate)
                            ->where('end_date', '>=', $startDate);
                    })->where('is_active', 0)->count();
                } else {
                    $data = 0; // Or handle default case based on your requirement
                }
                break;
        }

        return $data;
    }

    public function getUserCouponList(array $data, $limit = null, $offset = null)
    {
        $model = $this->model
            ->where(fn($query) => $query->where('customer_coupon_type', ALL)
                ->orWhereHas('customers', function ($query1) use ($data) {
                    $query1->where('id', $data['user_id']);
                }))
            ->where(fn($query) => $query->where('customer_level_coupon_type', ALL)
                ->orWhereHas('customerLevels', function ($query1) use ($data) {
                    $query1->where('id', $data['level_id']);
                }))
            ->where('is_active', $data['is_active'])
            ->whereDate('start_date', '<=', $data['date'])
            ->whereDate('end_date', '>=', $data['date']);
        if ($limit) {
            return $model->paginate(perPage: $limit, page: $offset);
        }
        return $model->get();
    }

    public function getAppliedCoupon($tripType, $vehicleCategoryId, array $data)
    {
        $model = $this->model
            ->where('id', $data['id'])
            ->where(fn($query) => $query->where('customer_coupon_type', ALL)
                ->orWhereHas('customers', function ($query1) use ($data) {
                    $query1->where('id', $data['user_id']);
                }))
            ->where(fn($query) => $query->where('customer_level_coupon_type', ALL)
                ->orWhereHas('customerLevels', function ($query1) use ($data) {
                    $query1->where('id', $data['level_id']);
                }))
            ->where(fn($query) => $query->where('zone_coupon_type', ALL)
                ->orWhereHas('zones', function ($query1) use ($data) {
                    $query1->where('id', $data['zone_id']);
                }))
            ->where(function ($query) use ($tripType, $vehicleCategoryId) {
                $query->whereRaw("JSON_CONTAINS(category_coupon_type, '\"all\"')")
                    ->orWhereRaw("JSON_CONTAINS(category_coupon_type, '\"$tripType\"')");

                if ($tripType != 'parcel' && $vehicleCategoryId != null) {
                    $query->orWhereHas('vehicleCategories', function ($query1) use ($vehicleCategoryId) {
                        $query1->where('id', $vehicleCategoryId);
                    });
                }
            })
            ->where('min_trip_amount', '<=', $data['fare'])
            ->where('is_active', $data['is_active'])
            ->whereDate('start_date', '<=', $data['date'])
            ->whereDate('end_date', '>=', $data['date']);
        return $model->first();
    }
}
