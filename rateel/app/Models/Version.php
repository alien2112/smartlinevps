<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Version extends Model
{
    protected $fillable = ['version', 'description', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
