<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LandingFeature extends Model
{
    protected $fillable = [
        'type', 'icon', 'image', 'title', 'description', 'sort_order', 'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }

    public function scopeWithIcon($query)
    {
        return $query->where('type', 'icon');
    }

    public function scopeWithImage($query)
    {
        return $query->where('type', 'image');
    }
}
