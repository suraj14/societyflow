<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VillaArea extends Model
{
    use HasFactory;

    protected $fillable = [
        'society_id',
        'name',
        'code',
        'description',
        'total_villas',
        'status',
    ];

    // Relationships
    public function society(): BelongsTo
    {
        return $this->belongsTo(Society::class);
    }

    public function villas(): HasMany
    {
        return $this->hasMany(Flat::class, 'villa_area_id')->where('property_type', 'villa');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeBySociety($query, $societyId)
    {
        return $query->where('society_id', $societyId);
    }

    // Helper methods
    public function updateTotals(): void
    {
        $this->update([
            'total_villas' => $this->villas()->count(),
        ]);
    }

    public function getOccupiedVillasCount(): int
    {
        return $this->villas()->count() - $this->getVacantVillasCount();
    }

    public function getVacantVillasCount(): int
    {
        // Canonical occupancy, not the stored status column.
        $states = \App\Support\PropertyOccupancy::statesFor($this->villas()->get());

        return collect($states)->where('state', \App\Support\PropertyOccupancy::VACANT)->count();
    }
}
