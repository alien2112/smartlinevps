<?php

namespace Modules\AdminModule\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationMessage extends Model
{
    use HasFactory;
    public function translations()
    {
        return $this->morphMany('App\Model\Translation', 'translationable');
    }
}
