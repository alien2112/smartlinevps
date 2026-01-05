<?php

namespace Modules\TripManagement\Repository\Eloquent;

use App\Repository\Eloquent\BaseRepository;
use Modules\TripManagement\Entities\LostItem;
use Modules\TripManagement\Repository\LostItemRepositoryInterface;

class LostItemRepository extends BaseRepository implements LostItemRepositoryInterface
{
    public function __construct(LostItem $model)
    {
        parent::__construct($model);
    }
}
