<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Building extends Model
{
    use HasFactory;

    protected $fillable = [
        'society_id',
        'name',
        'code',
        'description',
        'total_floors',
        'total_flats',
        'status',
    ];

    // Relationships
    public function society(): BelongsTo
    {
        return $this->belongsTo(Society::class);
    }

    public function flats(): HasMany
    {
        return $this->hasMany(Flat::class);
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
            'total_flats' => $this->flats()->count(),
            'total_floors' => $this->flats()->max('floor') ?? 0,
        ]);
    }

    public function getOccupiedFlatsCount(): int
    {
        return $this->flats()->where('status', 'occupied')->count();
    }

    public function getVacantFlatsCount(): int
    {
        return $this->flats()->where('status', 'vacant')->count();
    }

    public function getOccupancyRate(): float
    {
        if ($this->total_flats === 0) {
            return 0;
        }

        return ($this->getOccupiedFlatsCount() / $this->total_flats) * 100;
    }
}