<?php

namespace Modules\TripManagement\Repository\Eloquent;

use App\Repository\Eloquent\BaseRepository;
use Modules\TripManagement\Entities\LostItemStatusLog;
use Modules\TripManagement\Repository\LostItemStatusLogRepositoryInterface;

class LostItemStatusLogRepository extends BaseRepository implements LostItemStatusLogRepositoryInterface
{
    public function __construct(LostItemStatusLog $model)
    {
        parent::__construct($model);
    }
}
