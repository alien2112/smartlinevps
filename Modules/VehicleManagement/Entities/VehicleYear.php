<?php

namespace Modules\VehicleManagement\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class VehicleYear extends Model
{
    // use SoftDeletes;

    protected $fillable = ['year'];

    protected $table = 'vehicle_years';

    // protected $dates = ['deleted_at'];
}
