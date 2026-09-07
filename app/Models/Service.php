<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'category',
        'icon',
        'is_enabled',
        'requires_attendance',
        'sort_order',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'requires_attendance' => 'boolean',
    ];

    // Relationships
    public function providers(): HasMany
    {
        return $this->hasMany(ServiceProvider::class);
    }

    // Scopes
    public function scopeEnabled($query)
    {
        return $query->where('is_enabled', true);
    }

    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    // Helper methods
    public function getCategoryLabelAttribute(): string
    {
        return match($this->category) {
            'daily' => 'Daily & Regular',
            'maintenance' => 'Maintenance & Utility',
            'society' => 'Society Support',
            'optional' => 'Optional Services',
            default => ucfirst($this->category),
        };
    }

    public function getIconClassAttribute(): string
    {
        return $this->icon ?: 'fas fa-concierge-bell';
    }
}