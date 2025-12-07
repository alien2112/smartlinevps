<?php

namespace Modules\VehicleManagement\Http\Controllers\Api\New\Driver;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\VehicleManagement\Entities\VehicleYear;

class VehicleYearController extends Controller
{
    public function yearList()
    {
        $years = VehicleYear::get();
        return response()->json($years, 200);
    }

}
