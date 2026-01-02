<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Faq extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'category',
        'question',
        'answer',
        'order',
        'is_active',
        'user_type',
        'view_count',
        'helpful_count',
        'not_helpful_count',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'order' => 'integer',
        'view_count' => 'integer',
        'helpful_count' => 'integer',
        'not_helpful_count' => 'integer',
    ];

    const CATEGORY_GENERAL = 'general';
    const CATEGORY_TRIPS = 'trips';
    const CATEGORY_PAYMENTS = 'payments';
    const CATEGORY_ACCOUNT = 'account';
    const CATEGORY_VEHICLE = 'vehicle';

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForDriver($query)
    {
        return $query->whereIn('user_type', ['driver', 'both']);
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order')->orderBy('created_at');
    }

    public function incrementView(): void
    {
        $this->increment('view_count');
    }

    public function markAsHelpful(): void
    {
        $this->increment('helpful_count');
    }

    public function markAsNotHelpful(): void
    {
        $this->increment('not_helpful_count');
    }
}
