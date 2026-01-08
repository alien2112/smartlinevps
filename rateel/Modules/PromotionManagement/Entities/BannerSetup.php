<?php

namespace Modules\PromotionManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\AdminModule\Entities\ActivityLog;

class BannerSetup extends Model
{
    use HasFactory, HasUuid, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'time_period',
        'display_position',
        'redirect_link',
        'banner_group',
        'start_date',
        'end_date',
        'image',
        'total_redirection',
        'is_active',
        'deleted_at',
        'created_at',
        'updated_at',
        'target_audience',
        'banner_type',
        'coupon_code',
        'discount_code',
        'is_promotion',
        'coupon_id',
    ];

    protected $casts = [
        'total_redirection' => 'float',
        'is_active' => 'boolean',
        'is_promotion' => 'boolean'
    ];

    public function logs()
    {
        return $this->morphMany(ActivityLog::class, 'logable');
    }

    public function coupon()
    {
        return $this->belongsTo(\Modules\PromotionManagement\Entities\CouponSetup::class, 'coupon_id');
    }

    protected function scopeOfStatus($query, $status = 1)
    {
        $query->where('is_active', $status);
    }

    protected static function newFactory()
    {
        return \Modules\PromotionManagement\Database\factories\BannerSetupFactory::new();
    }

    protected static function boot()
    {
        parent::boot();


        static::updated(function ($item) {
            $array = [];
            foreach ($item->changes as $key => $change) {
                $array[$key] = $item->original[$key];
            }
            if (!empty($array)) {
                $log = new ActivityLog();
                $log->edited_by = auth()->user()->id ?? 'user_update';
                $log->before = $array;
                $log->after = $item->changes;
                $item->logs()->save($log);
            }
        });

        static::deleted(function ($item) {
            $log = new ActivityLog();
            $log->edited_by = auth()->user()->id;
            $log->before = $item->original;
            $item->logs()->save($log);
        });

    }
}
