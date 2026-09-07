<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LandingPricing extends Model
{
    protected $table = 'landing_pricing';

    protected $fillable = [
        'name', 'description', 'monthly_price', 'yearly_price', 'features', 'is_popular', 'sort_order', 'is_active'
    ];

    protected $casts = [
        'features' => 'array',
        'is_popular' => 'boolean',
        'is_active' => 'boolean',
        'monthly_price' => 'decimal:2',
        'yearly_price' => 'decimal:2',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }

    public function scopePopular($query)
    {
        return $query->where('is_popular', true);
    }
}
