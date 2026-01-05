<?php

namespace Modules\UserManagement\Http\Controllers\Api\Driver;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Modules\UserManagement\Entities\DriverTimeLog;
use Modules\UserManagement\Interfaces\DriverDetailsInterface;
use Modules\UserManagement\Interfaces\DriverInterface;
use Modules\UserManagement\Interfaces\DriverTimeLogInterface;
use Modules\UserManagement\Transformers\DriverResource;
use Modules\UserManagement\Transformers\DriverTimeLogResource;

class DriverController extends Controller
{
    public function __construct(
        private DriverInterface $driver,
        private DriverDetailsInterface $details,
        private DriverTimeLogInterface $timeLog
    )
    {
    }



    /**
     * @return JsonResponse
     */
    public function onlineStatus(): JsonResponse
    {
        $driver = auth('api')->user();
        $details = $this->details->getBy('user_id', $driver->id);
        $attributes = [
            'column' => 'user_id',
            'is_online' => $details['is_online'] == 1 ? 0 : 1,
            'availability_status' => $details['is_online'] == 1 ? 'unavailable' : 'available',
        ];
        $this->details->update(attributes: $attributes, id: $driver->id);
        // Time log set into driver details
        $this->details->setTimeLog(
            driver_id:$driver->id,
            date:date('Y-m-d'),
            online:($details->is_online == 1 ? now() : null),
            offline:($details->is_online == 1 ? null : now()),
            activeLog:true
        );

        return response()->json(responseFormatter(DEFAULT_STATUS_UPDATE_200));
    }


}
