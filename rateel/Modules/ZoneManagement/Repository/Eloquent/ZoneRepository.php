<?php

namespace Modules\ZoneManagement\Repository\Eloquent;

use App\Repository\Eloquent\BaseRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use MatanYadaev\EloquentSpatial\AxisOrder;
use Modules\ZoneManagement\Entities\Zone;
use Modules\ZoneManagement\Repository\ZoneRepositoryInterface;

/**
 * Updated: 2026-01-14 - Added default query limits to prevent memory exhaustion
 */
class ZoneRepository extends BaseRepository implements ZoneRepositoryInterface
{
    /**
     * Default limit for unbounded queries to prevent memory exhaustion
     * Updated: 2026-01-14
     */
    private const DEFAULT_QUERY_LIMIT = 1000;

    public function __construct(Zone $model)
    {
        parent::__construct($model);
    }


    public function getByPoints($point)
    {
        // Some DBs parse SRID 4326 WKT in lat/long order by default, while the app (via laravel-eloquent-spatial)
        // uses axis-order=long-lat. To keep zone lookup working regardless of how polygons were imported,
        // try both axis orders when supported.
        $wkt = method_exists($point, 'toWkt') ? $point->toWkt() : (string)$point;
        $srid = property_exists($point, 'srid') ? (int)$point->srid : 4326;

        $query = $this->model->newQuery();
        $connection = $this->model->getConnection();

        if (AxisOrder::supported($connection)) {
            $query->whereRaw(
                "ST_CONTAINS(ST_SRID(`coordinates`, ?), ST_GeomFromText(?, ?, 'axis-order=long-lat'))",
                [$srid, $wkt, $srid]
            )->orWhereRaw(
                "ST_CONTAINS(ST_SRID(`coordinates`, ?), ST_GeomFromText(?, ?, 'axis-order=lat-long'))",
                [$srid, $wkt, $srid]
            )->orderByRaw(
                "ST_CONTAINS(ST_SRID(`coordinates`, ?), ST_GeomFromText(?, ?, 'axis-order=long-lat')) DESC",
                [$srid, $wkt, $srid]
            );

            return $query;
        }

        return $query->whereRaw(
            "ST_CONTAINS(ST_SRID(`coordinates`, ?), ST_GeomFromText(?, ?))",
            [$srid, $wkt, $srid]
        );
    }

    public function findOne($id, array $relations = [], array $withAvgRelations = [],array $whereHasRelations = [], array $withCountQuery = [], bool $withTrashed = false, bool $onlyTrashed = false): ?Model
    {
        return $this->prepareModelForRelationAndOrder(relations: $relations)
            ->selectRaw("*,ST_AsText(ST_Centroid(ST_SRID(`coordinates`, 0))) as center")
            ->when(!empty($withCountQuery), function ($query) use ($withCountQuery) {
                $this->withCountQuery($query, $withCountQuery);
            })
            ->when(($onlyTrashed || $withTrashed), function ($query) use ($onlyTrashed, $withTrashed) {
                $this->withOrWithOutTrashDataQuery($query, $onlyTrashed, $withTrashed);
            })->when(!empty($withAvgRelations), function ($query) use ($withAvgRelations) {
                foreach ($withAvgRelations as $relation) {
                    $query->withAvg($relation[0], $relation[1]);
                }
            })
            ->find($id);
    }

    public function findOneBy(array $criteria = [], array $whereInCriteria = [], array $whereBetweenCriteria = [], array $withAvgRelations = [], array $relations = [],array $whereHasRelations = [], array $withCountQuery = [], array $orderBy = [], bool $withTrashed = false, bool $onlyTrashed = false): ?Model
    {
        return $this->prepareModelForRelationAndOrder(relations: $relations)
            ->selectRaw("*,ST_AsText(ST_Centroid(ST_SRID(`coordinates`, 0))) as center")
            ->where($criteria)
            ->when(!empty($whereInCriteria), function ($whereInQuery) use ($whereInCriteria) {
                foreach ($whereInCriteria as $column => $values) {
                    $whereInQuery->whereIn($column, $values);
                }
            })
            ->when(!empty($whereBetweenCriteria), function ($whereBetweenQuery) use ($whereBetweenCriteria) {
                foreach ($whereBetweenCriteria as $column => $range) {
                    $whereBetweenQuery->whereBetween($column, $range);
                }
            })
            ->when(!empty($withCountQuery), function ($query) use ($withCountQuery) {
                $this->withCountQuery($query, $withCountQuery);
            })
            ->when(($onlyTrashed || $withTrashed), function ($query) use ($onlyTrashed, $withTrashed) {
                $this->withOrWithOutTrashDataQuery($query, $onlyTrashed, $withTrashed);
            })->when(!empty($withAvgRelations), function ($query) use ($withAvgRelations) {
                foreach ($withAvgRelations as $relation) {
                    $query->withAvg($relation[0], $relation[1]);
                }
            })->when(!empty($orderBy), function ($query) use ($orderBy) {
                foreach ($orderBy as $column => $order) {
                    $query->orderBy($column, $order);
                }
            })
            ->first();
    }

    /**
     * Updated: 2026-01-14 - Added default limit to prevent memory exhaustion on large datasets
     */
    public function getAll(array $relations = [], array $orderBy = [], int $limit = null, int $offset = null, bool $onlyTrashed = false, bool $withTrashed = false, array $withCountQuery = [], array $groupBy = []): Collection|LengthAwarePaginator
    {
        $model = $this->prepareModelForRelationAndOrder(relations: $relations, orderBy: $orderBy)
            ->selectRaw("*,ST_AsText(ST_Centroid(ST_SRID(`coordinates`, 0))) as center")
            ->when(($onlyTrashed || $withTrashed), function ($query) use ($onlyTrashed, $withTrashed) {
                $this->withOrWithOutTrashDataQuery($query, $onlyTrashed, $withTrashed);
            })
            ->when(!empty($withCountQuery), function ($query) use ($withCountQuery) {
                $this->withCountQuery($query, $withCountQuery);
            })->when(!empty($groupBy), function ($query) use ($groupBy) {
                $selectFields = []; // Prepare an array to hold select fields

                foreach ($groupBy as $groupColumn) {
                    if (str_ends_with($groupColumn, 'created_at')) {
                        // Group by the date part of the created_at field
                        $query->groupBy(DB::raw('DATE(' . $groupColumn . ')'));
                        $selectFields[] = DB::raw('DATE(' . $groupColumn . ') as ' . $groupColumn); // Select the date part
                    } else {
                        $query->groupBy($groupColumn);
                        $selectFields[] = $groupColumn; // Select the original group column
                    }
                }

                // Update the select statement to include the group columns
                $query->select($selectFields);
            });
        if ($limit) {
            return $model->paginate(perPage: $limit, page: $offset ?? 1);
        }

        // Updated 2026-01-14: Apply default limit to prevent memory exhaustion
        // OLD CODE: return $model->get(); // Commented 2026-01-14 - unbounded query risk
        return $model->limit(self::DEFAULT_QUERY_LIMIT)->get();
    }

    public function getBy(array $criteria = [], array $searchCriteria = [], array $whereInCriteria = [], array $whereBetweenCriteria = [], array $whereHasRelations = [], array $withAvgRelations = [], array $relations = [], array $orderBy = [], int $limit = null, int $offset = null, bool $onlyTrashed = false, bool $withTrashed = false, array $withCountQuery = [], array $appends = [], array $groupBy = []): Collection|LengthAwarePaginator
    {
        $model = $this->prepareModelForRelationAndOrder(relations: $relations, orderBy: $orderBy)
            ->selectRaw("*,ST_AsText(ST_Centroid(ST_SRID(`coordinates`, 0))) as center")
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
            })->when(!empty($groupBy), function ($query) use ($groupBy) {
                $selectFields = []; // Prepare an array to hold select fields
                foreach ($groupBy as $groupColumn) {
                    if (str_ends_with($groupColumn, 'created_at')) {
                        // Group by the date part of the created_at field
                        $query->groupBy(DB::raw('DATE(' . $groupColumn . ')'));
                        $selectFields[] = DB::raw('DATE(' . $groupColumn . ') as ' . $groupColumn); // Select the date part
                    } else {
                        $query->groupBy($groupColumn);
                        $selectFields[] = $groupColumn; // Select the original group column
                    }
                }

                // Update the select statement to include the group columns
                $query->select($selectFields);
            });
        if ($limit) {
            return !empty($appends) ? $model->paginate(perPage: $limit, page: $offset ?? 1)->appends($appends) : $model->paginate(perPage: $limit, page: $offset ?? 1);
        }

        // Updated 2026-01-14: Apply default limit to prevent memory exhaustion
        // OLD CODE: return $model->get(); // Commented 2026-01-14 - unbounded query risk
        return $model->limit(self::DEFAULT_QUERY_LIMIT)->get();
    }

}
