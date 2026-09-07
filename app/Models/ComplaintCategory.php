<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ComplaintCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'society_id',
        'name',
        'description',
        'status',
    ];

    // Relationships
    public function society(): BelongsTo
    {
        return $this->belongsTo(Society::class);
    }

    public function complaints(): HasMany
    {
        return $this->hasMany(Complaint::class);
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
    public function getComplaintsCount(): int
    {
        return $this->complaints()->count();
    }

    public function getOpenComplaintsCount(): int
    {
        return $this->complaints()->whereIn('status', ['open', 'in_progress'])->count();
    }

    public function getResolvedComplaintsCount(): int
    {
        return $this->complaints()->where('status', 'resolved')->count();
    }
}