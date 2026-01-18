<?php

namespace Modules\TripManagement\Repository\Eloquent;

use App\Repository\Eloquent\BaseRepository;
use Carbon\Carbon;
use MatanYadaev\EloquentSpatial\Objects\Point;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Modules\TripManagement\Entities\TripRequest;
use Modules\TripManagement\Repository\TripRequestRepositoryInterface;

/**
 * Updated: 2026-01-14 - Added default query limits to prevent memory exhaustion
 */
class TripRequestRepository extends BaseRepository implements TripRequestRepositoryInterface
{
    /**
     * Default limit for unbounded queries to prevent memory exhaustion
     * Updated: 2026-01-14
     */
    private const DEFAULT_QUERY_LIMIT = 1000;

    public function __construct(TripRequest $model)
    {
        parent::__construct($model);
    }

    /**
     * Get all dashboard metrics in a single aggregated query
     * Replaces 7+ separate trip_requests queries with one query using conditional aggregation
     * 
     * @return object Contains: total_trips, total_parcels, total_coupon, total_discount,
     *                         total_earning, trips_earning, parcels_earning
     */
    public function getDashboardAggregatedMetrics(): object
    {
        return DB::table('trip_requests')
            ->leftJoin('trip_request_fees', 'trip_requests.id', '=', 'trip_request_fees.trip_request_id')
            ->selectRaw('
                SUM(CASE WHEN type = "ride_request" THEN 1 ELSE 0 END) as total_trips,
                SUM(CASE WHEN type = "parcel" THEN 1 ELSE 0 END) as total_parcels,
                SUM(CASE WHEN payment_status = "paid" THEN coupon_amount ELSE 0 END) as total_coupon,
                SUM(CASE WHEN payment_status = "paid" THEN discount_amount ELSE 0 END) as total_discount,
                SUM(CASE WHEN payment_status = "paid" AND (trip_request_fees.cancelled_by IS NULL OR trip_request_fees.cancelled_by = "CUSTOMER") THEN trip_request_fees.admin_commission ELSE 0 END) as total_earning,
                SUM(CASE WHEN type = "ride_request" AND payment_status = "paid" AND (trip_request_fees.cancelled_by IS NULL OR trip_request_fees.cancelled_by = "CUSTOMER") THEN trip_request_fees.admin_commission ELSE 0 END) as trips_earning,
                SUM(CASE WHEN type = "parcel" AND payment_status = "paid" AND (trip_request_fees.cancelled_by IS NULL OR trip_request_fees.cancelled_by = "CUSTOMER") THEN trip_request_fees.admin_commission ELSE 0 END) as parcels_earning
            ')
            ->first();
    }

    public function calculateCouponAmount($startDate = null, $endDate = null, $startTime = null, $month = null, $year = null): mixed
    {
        $query = $this->model->whereNotNull('coupon_amount');

        if ($startDate !== null && $endDate !== null) {
            $query->whereBetween('created_at', [
                "{$startDate->format('Y-m-d')} 00:00:00",
                "{$endDate->format('Y-m-d')} 23:59:59"
            ]);
        } elseif ($startTime !== null) {
            $query->whereBetween('created_at', [
                date('Y-m-d', strtotime(TODAY)) . ' ' . date('H:i:s', $startTime),
                date('Y-m-d', strtotime(TODAY)) . ' ' . date('H:i:s', strtotime('+2 hours', $startTime))
            ]);
        } elseif ($month !== null) {
            $query->whereMonth('created_at', $month)
                ->whereYear('created_at', now()->format('Y'));
        } elseif ($year !== null) {
            $query->whereYear('created_at', $year);
        } else {
            $query->whereDay('created_at', now()->format('d'))
                ->whereMonth('created_at', now()->format('m'));
        }

        return $query->sum('coupon_amount');
    }

    /**
     * Get analytics data aggregated by time period in a single query
     * Replaces N separate queries with 1 grouped query
     * 
     * @param string $period 'daily', 'weekly', 'monthly', 'yearly'
     * @param Carbon|null $startDate Start of date range
     * @param Carbon|null $endDate End of date range
     * @param int|null $year Year for yearly/monthly aggregation
     * @return array Aggregated coupon amounts keyed by period
     */
    public function getAnalyticsAggregated(string $period, $startDate = null, $endDate = null, $year = null): array
    {
        $query = $this->model->whereNotNull('coupon_amount');
        
        if ($startDate && $endDate) {
            $query->whereBetween('created_at', [
                $startDate->startOfDay(),
                $endDate->endOfDay()
            ]);
        } elseif ($year) {
            $query->whereYear('created_at', $year);
        }
        
        switch ($period) {
            case 'hourly':
                // Group by 2-hour blocks for "today" view
                $results = $query->selectRaw('
                    FLOOR(HOUR(created_at) / 2) as time_block,
                    SUM(coupon_amount) as total
                ')
                ->whereDate('created_at', now()->toDateString())
                ->groupBy('time_block')
                ->pluck('total', 'time_block')
                ->toArray();
                break;
                
            case 'daily':
                // Group by day of week
                $results = $query->selectRaw('
                    DAYOFWEEK(created_at) as day_of_week,
                    SUM(coupon_amount) as total
                ')
                ->groupBy('day_of_week')
                ->pluck('total', 'day_of_week')
                ->toArray();
                break;
                
            case 'weekly':
                // Group by week number within month
                $results = $query->selectRaw('
                    CEIL(DAY(created_at) / 7) as week_num,
                    SUM(coupon_amount) as total
                ')
                ->groupBy('week_num')
                ->pluck('total', 'week_num')
                ->toArray();
                break;
                
            case 'monthly':
                // Group by month
                $results = $query->selectRaw('
                    MONTH(created_at) as month,
                    SUM(coupon_amount) as total
                ')
                ->groupBy('month')
                ->orderBy('month')
                ->pluck('total', 'month')
                ->toArray();
                break;
                
            case 'yearly':
                // Group by year
                $results = $query->selectRaw('
                    YEAR(created_at) as year,
                    SUM(coupon_amount) as total
                ')
                ->groupBy('year')
                ->orderBy('year')
                ->pluck('total', 'year')
                ->toArray();
                break;
                
            default:
                $results = [];
        }
        
        return $results;
    }

    public function fetchTripData($dateRange): Collection
    {
        $query = $this->model->whereNotNull('coupon_amount');

        switch ($dateRange) {
            case THIS_WEEK:
                $startDate = Carbon::now()->startOfWeek();
                $endDate = Carbon::now()->endOfWeek();
                $query->whereBetween('created_at', [$startDate, $endDate]);
                break;

            case THIS_MONTH:
                $query->whereYear('created_at', Carbon::now()->year)
                    ->whereMonth('created_at', Carbon::now()->month);
                break;

            case THIS_YEAR:
                $query->whereYear('created_at', Carbon::now()->year);
                break;
            case TODAY:
                $query->whereDate('created_at', Carbon::today());
            default:
                $query;
                break;
        }

        // Updated 2026-01-14: Apply default limit to prevent memory exhaustion
        // OLD CODE: return $query->get(); // Commented 2026-01-14 - unbounded query risk
        return $query->limit(self::DEFAULT_QUERY_LIMIT)->get();
    }


    public function statusWiseTotalTripRecords(array $attributes): Collection
    {
        return $this->model->query()
            ->when($attributes['from'] ?? null, fn($query) => $query->whereBetween('created_at', [$attributes['from'], $attributes['to']]))
            ->selectRaw('current_status, count(*) as total_records')
            ->groupBy('current_status')->get();
    }


    public function pendingParcelList(array $attributes)
    {
        return $this->model->query()
            ->with([
                'customer', 'driver', 'vehicleCategory', 'vehicleCategory.tripFares', 'vehicle', 'coupon', 'time',
                'coordinate', 'fee', 'tripStatus', 'zone', 'vehicle.model', 'fare_biddings', 'parcel', 'parcelUserInfo'
            ])
            ->where(['type' => 'parcel', $attributes['column'] => $attributes['value']])
            ->when($attributes['whereNotNull'] ?? null, fn($query) => $query->whereNotNull($attributes['whereNotNull']))
            ->whereNotIn('current_status', ['cancelled', 'completed'])
            ->paginate(perPage: $attributes['limit'], page: $attributes['offset']);
    }


    public function updateRelationalTable($attributes): mixed
    {
        $trip = $this->findOne(id: $attributes['value']);

        // Support both 'trip_status' and 'current_status' keys for status updates
        $statusKey = $attributes['trip_status'] ?? $attributes['current_status'] ?? null;
        if ($statusKey) {
            $tripData['current_status'] = $statusKey;

            $trip->update($tripData);
            $trip->tripStatus()->update([
                $statusKey => now()
            ]);
        }
        if ($attributes['driver_id'] ?? null) {
            $trip->driver_id = null;
            $trip->save();
        }

        if ($attributes['coordinate'] ?? null) {
            // Use CoordinateHelper for drop_coordinates to bypass Eloquent Spatial bug
            if (isset($attributes['coordinate']['drop_coordinates'])) {
                $dropCoords = $attributes['coordinate']['drop_coordinates'];
                if ($dropCoords instanceof \MatanYadaev\EloquentSpatial\Objects\Point) {
                    \App\Helpers\CoordinateHelper::updateDropCoordinates(
                        $trip->id,
                        $dropCoords->latitude,
                        $dropCoords->longitude
                    );
                }
                // Remove drop_coordinates from the array so it doesn't get updated again
                unset($attributes['coordinate']['drop_coordinates']);
            }
            // Update any remaining coordinate fields through Eloquent
            if (!empty($attributes['coordinate'])) {
                $trip->coordinate()->update($attributes['coordinate']);
            }
        }
        if ($attributes['fee'] ?? null) {
            $trip->fee()->update($attributes['fee']);
        }
        return $trip->tripStatus;
    }


    public function findOneWithAvg(array $criteria = [], array $relations = [], array $withCountQuery = [], bool $withTrashed = false, bool $onlyTrashed = false, array $withAvgRelation = []): ?Model
    {
        $data = $this->prepareModelForRelationAndOrder(relations: $relations)
            ->where($criteria)
            ->when(!empty($withCountQuery), function ($query) use ($withCountQuery) {
                $this->withCountQuery($query, $withCountQuery);
            })
            ->when(($onlyTrashed || $withTrashed), function ($query) use ($onlyTrashed, $withTrashed) {
                $this->withOrWithOutTrashDataQuery($query, $onlyTrashed, $withTrashed);
            })
            ->when(!empty($withAvgRelation), function ($query) use ($withAvgRelation) {
                $query->withAvg($withAvgRelation[0], $withAvgRelation[1]);
            })
            ->first();
        return $data;
    }


    public function getWithAvg(array $criteria = [], array $searchCriteria = [], array $whereInCriteria = [], array $relations = [], array $orderBy = [], int $limit = null, int $offset = null, bool $onlyTrashed = false, bool $withTrashed = false, array $withCountQuery = [], array $withAvgRelation = [], array $whereBetweenCriteria = [], array $whereNotNullCriteria = []): Collection|LengthAwarePaginator
    {

        $model = $this->prepareModelForRelationAndOrder(relations: $relations, orderBy: $orderBy)
            ->when(!empty($criteria), function ($whereQuery) use ($criteria) {
                $whereQuery->where($criteria);
            })->when(!empty($whereInCriteria), function ($whereInQuery) use ($whereInCriteria) {
                foreach ($whereInCriteria as $column => $values) {
                    $whereInQuery->whereIn($column, $values);
                }
            })->when(!empty($searchCriteria), function ($whereQuery) use ($searchCriteria) {
                $this->searchQuery($whereQuery, $searchCriteria);
            })->when(($onlyTrashed || $withTrashed), function ($query) use ($onlyTrashed, $withTrashed) {
                $this->withOrWithOutTrashDataQuery($query, $onlyTrashed, $withTrashed);
            })
            ->when(!empty($withCountQuery), function ($query) use ($withCountQuery) {
                $this->withCountQuery($query, $withCountQuery);
            })
            ->when(!empty($whereBetweenCriteria), function ($whereQuery) use ($whereBetweenCriteria) {
                foreach ($whereBetweenCriteria as $column => $values) {
                    $whereQuery->whereBetween($column, $values);
                }
            })
            ->when(!empty($whereNotNullCriteria), function ($whereQuery) use ($whereNotNullCriteria) {
                foreach ($whereNotNullCriteria as $column) {
                    $whereQuery->whereNotNull($column);
                }
            })
            ->when(!empty($withAvgRelation), function ($query) use ($withAvgRelation) {
                $query->withAvg($withAvgRelation[0], $withAvgRelation[1]);
            });

        if ($limit) {
            return !empty($criteria) ? $model->paginate($limit)->appends($criteria) : $model->paginate($limit);
        }

        // Updated 2026-01-14: Apply default limit to prevent memory exhaustion
        // OLD CODE: return $model->get(); // Commented 2026-01-14 - unbounded query risk
        return $model->limit(self::DEFAULT_QUERY_LIMIT)->get();
    }


    public function getPendingRides($attributes): mixed
    {
        return $this->model->query()
            ->when($attributes['relations'] ?? null, fn($query) => $query->with($attributes['relations']))
            ->with([
                'fare_biddings' => fn($query) => $query->where('driver_id', auth()->id()),
                'coordinate' => fn($query) => $query->distanceSphere('pickup_coordinates', $attributes['driver_locations'], $attributes['distance'])
            ])
            ->whereHas('coordinate',
                fn($query) => $query->distanceSphere('pickup_coordinates', $attributes['driver_locations'], $attributes['distance']))
            ->when($attributes['withAvgRelation'] ?? null,
                fn($query) => $query->withAvg($attributes['withAvgRelation'], $attributes['withAvgColumn']))
            ->whereDoesntHave('ignoredRequests', fn($query) => $query->where('user_id', auth()->id()))
            ->where(fn($query) => $query->whereIn('vehicle_category_id', (array) $attributes['vehicle_category_id'])
                ->orWhereNull('vehicle_category_id')
            )
            ->where(['zone_id' => $attributes['zone_id'], 'current_status' => PENDING,])
            ->orderBy('created_at', 'desc')
            ->paginate(perPage: $attributes['limit'], page: $attributes['offset']);
    }

    public function getZoneWiseStatistics(array $criteria = [], array $searchCriteria = [], array $whereInCriteria = [], array $whereBetweenCriteria = [], array $whereHasRelations = [], array $withAvgRelations = [], array $relations = [], array $orderBy = [], int $limit = null, int $offset = null, bool $onlyTrashed = false, bool $withTrashed = false, array $withCountQuery = [], array $appends = []): Collection|LengthAwarePaginator
    {
        $model = $this->prepareModelForRelationAndOrder(relations: $relations, orderBy: $orderBy)
            ->when(!empty($criteria), function ($whereQuery) use ($criteria) {
                $whereQuery->where($criteria);
            })->when(!empty($whereInCriteria), function ($whereInQuery) use ($whereInCriteria) {
                foreach ($whereInCriteria as $column => $values) {
                    $whereInQuery->whereIn($column, $values);
                }
            })->when(!empty($whereHasRelations), function ($whereHasQuery) use ($whereHasRelations) {
                foreach ($whereHasRelations as $relation => $conditions) {
                    $whereHasQuery->whereHas($relation, function ($query) use ($conditions) {
                        $query->where($conditions);
                    });
                }
            })->when(!empty($whereBetweenCriteria), function ($whereBetweenQuery) use ($whereBetweenCriteria) {
                foreach ($whereBetweenCriteria as $column => $range) {
                    $whereBetweenQuery->whereBetween($column, $range);
                }
            })->when(!empty($searchCriteria), function ($whereQuery) use ($searchCriteria) {
                $this->searchQuery($whereQuery, $searchCriteria);
            })->when(($onlyTrashed || $withTrashed), function ($query) use ($onlyTrashed, $withTrashed) {
                $this->withOrWithOutTrashDataQuery($query, $onlyTrashed, $withTrashed);
            })
            ->when(!empty($withCountQuery), function ($query) use ($withCountQuery) {
                $this->withCountQuery($query, $withCountQuery);
            })->when(!empty($withAvgRelations), function ($query) use ($withAvgRelations) {
                foreach ($withAvgRelations as $relation) {
                    $query->withAvg($relation);
                }
            })->whereNotNull('zone_id')
            ->selectRaw('count(completed) as completed_trips,count(cancelled) as cancelled_trips,count(pending) as pending_trips,count(accepted) as accepted_trips,count(ongoing) as ongoing_trips,zone_id, count(*) as total_records')
            ->groupBy('zone_id')->orderBy('total_records', 'asc');
        if ($limit) {
            return !empty($appends) ? $model->paginate($limit)->appends($appends) : $model->paginate($limit);
        }

        // Updated 2026-01-14: Apply default limit to prevent memory exhaustion
        // OLD CODE: return $model->get(); // Commented 2026-01-14 - unbounded query risk
        return $model->limit(self::DEFAULT_QUERY_LIMIT)->get();
    }

    /**
     * Get aggregated zone statistics in a single query
     * Replaces N+1 zone queries with one grouped query
     * 
     * @param array $zoneIds Array of zone IDs to get statistics for
     * @param array $whereBetweenCriteria Date range criteria
     * @return Collection Aggregated statistics keyed by zone_id
     */
    public function getAggregatedZoneStatistics(array $zoneIds, array $whereBetweenCriteria = []): Collection
    {
        return $this->model->query()
            ->whereIn('zone_id', $zoneIds)
            ->when(!empty($whereBetweenCriteria), function ($query) use ($whereBetweenCriteria) {
                foreach ($whereBetweenCriteria as $column => $range) {
                    $query->whereBetween($column, $range);
                }
            })
            ->selectRaw('
                zone_id,
                COUNT(*) as total_trips,
                SUM(CASE WHEN current_status = "completed" THEN 1 ELSE 0 END) as completed_trips,
                SUM(CASE WHEN current_status = "cancelled" THEN 1 ELSE 0 END) as cancelled_trips,
                SUM(CASE WHEN current_status IN ("pending", "accepted", "ongoing") THEN 1 ELSE 0 END) as ongoing_trips
            ')
            ->groupBy('zone_id')
            ->get()
            ->keyBy('zone_id');
    }

    public function getZoneWiseEarning(array $criteria = [], array $searchCriteria = [], array $whereInCriteria = [], array $whereBetweenCriteria = [], array $whereHasRelations = [], array $withAvgRelations = [], array $relations = [], array $orderBy = [], int $limit = null, int $offset = null, bool $onlyTrashed = false, bool $withTrashed = false, array $withCountQuery = [], array $appends = [], $startDate = null, $endDate = null, $startTime = null, $month = null, $year = null): Collection|LengthAwarePaginator
    {
        $model = $this->prepareModelForRelationAndOrder(relations: $relations, orderBy: $orderBy)
            ->when(!empty($criteria), function ($whereQuery) use ($criteria) {
                $whereQuery->where($criteria);
            })->when(!empty($whereInCriteria), function ($whereInQuery) use ($whereInCriteria) {
                foreach ($whereInCriteria as $column => $values) {
                    $whereInQuery->whereIn($column, $values);
                }
            })->when(!empty($whereHasRelations), function ($whereHasQuery) use ($whereHasRelations) {
                foreach ($whereHasRelations as $relation => $conditions) {
                    $whereHasQuery->whereHas($relation, function ($query) use ($conditions) {
                        $query->where($conditions);
                    });
                }
            })->when(!empty($searchCriteria), function ($whereQuery) use ($searchCriteria) {
                $this->searchQuery($whereQuery, $searchCriteria);
            })->when(($onlyTrashed || $withTrashed), function ($query) use ($onlyTrashed, $withTrashed) {
                $this->withOrWithOutTrashDataQuery($query, $onlyTrashed, $withTrashed);
            })
            ->when(!empty($withCountQuery), function ($query) use ($withCountQuery) {
                $this->withCountQuery($query, $withCountQuery);
            })->when(!empty($withAvgRelations), function ($query) use ($withAvgRelations) {
                foreach ($withAvgRelations as $relation) {
                    $query->withAvg($relation);
                }
            });
        if ($startDate !== null && $endDate !== null) {
            $model->whereBetween('created_at', [
                "{$startDate->format('Y-m-d')} 00:00:00",
                "{$endDate->format('Y-m-d')} 23:59:59"
            ]);
        } elseif ($startDate !== null && $startTime !== null) {
            $model->whereBetween('created_at', [
                date('Y-m-d', strtotime($startDate)) . ' ' . date('H:i:s', $startTime),
                date('Y-m-d', strtotime($startDate)) . ' ' . date('H:i:s', strtotime('+2 hours', $startTime))
            ]);
        } elseif ($month !== null && $year) {
            $model->whereMonth('created_at', $month)
                ->whereYear('created_at', $year);
        } elseif ($month !== null && $year !== null) {
            $model->whereMonth('created_at', $month)
                ->whereYear('created_at', $year);
        } elseif ($month !== null) {
            $model->whereMonth('created_at', $month)
                ->whereYear('created_at', now()->format('Y'));
        } elseif ($year !== null) {
            $model->whereYear('created_at', $year);
        } else {
            $model->whereDay('created_at', now()->format('d'))
                ->whereMonth('created_at', now()->format('m'));
        }
        if ($limit) {
            return !empty($appends) ? $model->paginate($limit)->appends($appends) : $model->paginate($limit);
        }

        // Updated 2026-01-14: Apply default limit to prevent memory exhaustion
        // OLD CODE: return $model->get(); // Commented 2026-01-14 - unbounded query risk
        return $model->limit(self::DEFAULT_QUERY_LIMIT)->get();
    }

    public function getLeaderBoard(string $userType, array $criteria = [], array $searchCriteria = [], array $whereInCriteria = [], array $whereBetweenCriteria = [], array $whereHasRelations = [], array $withAvgRelations = [], array $relations = [], array $orderBy = [], int $limit = null, int $offset = null, bool $onlyTrashed = false, bool $withTrashed = false, array $withCountQuery = [], array $appends = []): Collection|LengthAwarePaginator
    {
        $model = $this->prepareModelForRelationAndOrder(relations: $relations, orderBy: $orderBy)
            ->when(!empty($criteria), function ($whereQuery) use ($criteria) {
                $whereQuery->where($criteria);
            })->when(!empty($whereInCriteria), function ($whereInQuery) use ($whereInCriteria) {
                foreach ($whereInCriteria as $column => $values) {
                    $whereInQuery->whereIn($column, $values);
                }
            })
            ->when(!empty($whereHasRelations), function ($whereHasQuery) use ($whereHasRelations) {
                foreach ($whereHasRelations as $relation => $conditions) {
                    $whereHasQuery->whereHas($relation, function ($query) use ($conditions) {
                        $query->where($conditions);
                    });
                }
            })->when(!empty($whereBetweenCriteria), function ($whereBetweenQuery) use ($whereBetweenCriteria) {
                foreach ($whereBetweenCriteria as $column => $range) {
                    $whereBetweenQuery->whereBetween($column, $range);
                }
            })->when(!empty($searchCriteria), function ($whereQuery) use ($searchCriteria) {
                $this->searchQuery($whereQuery, $searchCriteria);
            })->when(($onlyTrashed || $withTrashed), function ($query) use ($onlyTrashed, $withTrashed) {
                $this->withOrWithOutTrashDataQuery($query, $onlyTrashed, $withTrashed);
            })
            ->when(!empty($withCountQuery), function ($query) use ($withCountQuery) {
                $this->withCountQuery($query, $withCountQuery);
            })->when(!empty($withAvgRelations), function ($query) use ($withAvgRelations) {
                foreach ($withAvgRelations as $relation) {
                    $query->withAvg($relation);
                }
            })->whereNotNull($userType)
            ->selectRaw($userType . ', count(*) as total_records ,SUM(paid_fare) as income')
            ->groupBy($userType)
            ->orderBy('total_records', 'desc');
        if ($limit) {
            return !empty($appends) ? $model->paginate($limit)->appends($appends) : $model->paginate($limit);
        }

        // Updated 2026-01-14: Apply default limit to prevent memory exhaustion
        // OLD CODE: return $model->get(); // Commented 2026-01-14 - unbounded query risk
        return $model->limit(self::DEFAULT_QUERY_LIMIT)->get();
    }

    public function getPopularTips()
    {
        return $this->model->whereNot('tips', 0)->groupBy('tips')->selectRaw('tips, count(*) as total')->orderBy('total', 'desc')->first();
    }

    public function getTripHeatMapCompareDataBy(array $criteria = [], array $searchCriteria = [], array $whereInCriteria = [], array $whereBetweenCriteria = [], array $whereHasRelations = [], array $withAvgRelations = [], array $relations = [], array $orderBy = [], int $limit = null, int $offset = null, bool $onlyTrashed = false, bool $withTrashed = false, array $withCountQuery = [], array $appends = [], $startDate = null, $endDate = null): Collection|LengthAwarePaginator
    {
        $startDateTime = Carbon::createFromFormat('Y-m-d H:i:s', $startDate)->setTime(0, 0); // Start at 6 AM
        $endDateTime = $startDateTime->copy()->endOfDay(); // End of the same day
        $model = $this->prepareModelForRelationAndOrder(relations: $relations, orderBy: $orderBy)
            ->when(!empty($criteria), function ($whereQuery) use ($criteria) {
                $whereQuery->where($criteria);
            })->when(!empty($whereInCriteria), function ($whereInQuery) use ($whereInCriteria) {
                foreach ($whereInCriteria as $column => $values) {
                    $whereInQuery->whereIn($column, $values);
                }
            })->when(!empty($whereHasRelations), function ($whereHasQuery) use ($whereHasRelations) {
                foreach ($whereHasRelations as $relation => $conditions) {
                    $whereHasQuery->whereHas($relation, function ($query) use ($conditions) {
                        $query->where($conditions);
                    });
                }
            })->when(!empty($whereBetweenCriteria), function ($whereBetweenQuery) use ($whereBetweenCriteria) {
                foreach ($whereBetweenCriteria as $column => $range) {
                    $whereBetweenQuery->whereBetween($column, $range);
                }
            })->when(!empty($searchCriteria), function ($whereQuery) use ($searchCriteria) {
                $this->searchQuery($whereQuery, $searchCriteria);
            })->when(($onlyTrashed || $withTrashed), function ($query) use ($onlyTrashed, $withTrashed) {
                $this->withOrWithOutTrashDataQuery($query, $onlyTrashed, $withTrashed);
            })
            ->when(!empty($withCountQuery), function ($query) use ($withCountQuery) {
                $this->withCountQuery($query, $withCountQuery);
            })->when(!empty($withAvgRelations), function ($query) use ($withAvgRelations) {
                foreach ($withAvgRelations as $relation) {
                    $query->withAvg($relation['relation'], $relation['column']);
                }
            });

        if ($startDate->isSameDay($endDate)) {
            $model->select(
                DB::raw('DATE(created_at) as date'), // Extract the date part from created_at
                DB::raw('HOUR(created_at) AS hour'), // Get the hour part
                DB::raw('COUNT(CASE WHEN type = "parcel" THEN 1 END) as parcel_count'), // Count for parcel type
                DB::raw('COUNT(CASE WHEN type = "ride_request" THEN 1 END) as ride_count') // Count for ride type
            )
                ->whereBetween('created_at', [$startDateTime, $endDateTime]) // Full day range
                ->groupBy('date', 'hour')
                ->orderBy('hour', 'asc'); // Group by date and hour
        } elseif ($startDate->isSameWeek($endDate)) {
            $model->select(
                DB::raw('DATE(created_at) as date'), // Extract the date part from created_at
                DB::raw('DAYNAME(created_at) AS day'), // Get the hour part
                DB::raw('COUNT(CASE WHEN type = "parcel" THEN 1 END) as parcel_count'), // Count for parcel type
                DB::raw('COUNT(CASE WHEN type = "ride_request" THEN 1 END) as ride_count') // Count for ride type
            )
                ->whereBetween('created_at', [$startDate, $endDate]) // Full day range
                ->groupBy('date', 'day'); // Group by date and hour
        } elseif ($startDate->isSameMonth($endDate)) {

            $model->select(
                DB::raw('DATE(created_at) as date'), // Extract the date part from created_at
                DB::raw('COUNT(CASE WHEN type = "parcel" THEN 1 END) as parcel_count'), // Count for parcel type
                DB::raw('COUNT(CASE WHEN type = "ride_request" THEN 1 END) as ride_count') // Count for ride type
            )
                ->whereBetween('created_at', [$startDate, $endDate]) // Full day range
                ->groupBy('date')
                ->orderBy('date', 'asc');
        } elseif ($startDate->isSameYear($endDate)) {

            $model->select(
                DB::raw('MONTH(created_at) as month'), // Group by month (Year-Month format)
                DB::raw('YEAR(created_at) as year'), // Group by month (Year-Month format)
                DB::raw('COUNT(CASE WHEN type = "parcel" THEN 1 END) as parcel_count'), // Count for parcel type
                DB::raw('COUNT(CASE WHEN type = "ride_request" THEN 1 END) as ride_count') // Count for ride type
            )
                ->whereBetween('created_at', [$startDate, $endDate]) // Full day range
                ->groupBy('month', 'year')
                ->orderBy('month', 'asc');
        } else {

            $model->select(
                DB::raw('YEAR(created_at) as year'), // Group by year
                DB::raw('COUNT(CASE WHEN type = "parcel" THEN 1 END) as parcel_count'), // Count for parcel type
                DB::raw('COUNT(CASE WHEN type = "ride_request" THEN 1 END) as ride_count') // Count for ride type
            )
                ->whereBetween('created_at', [$startDate, $endDate]) // Full day range
                ->groupBy('year')
                ->orderBy('year', 'asc');
        }

        if ($limit) {
            return !empty($appends) ? $model->paginate(perPage: $limit, page: $offset ?? 1)->appends($appends) : $model->paginate(perPage: $limit, page: $offset ?? 1);
        }

        // Updated 2026-01-14: Apply default limit to prevent memory exhaustion
        // OLD CODE: return $model->get(); // Commented 2026-01-14 - unbounded query risk
        return $model->limit(self::DEFAULT_QUERY_LIMIT)->get();
    }
}
