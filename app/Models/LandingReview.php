<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LandingReview extends Model
{
    protected $fillable = [
        'name', 'designation', 'company', 'avatar', 'review', 'rating', 'sort_order', 'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'rating' => 'integer',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }
}
