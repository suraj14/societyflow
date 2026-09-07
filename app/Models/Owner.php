<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Owner extends Model
{
    use HasFactory;

    protected $fillable = [
        'society_id',
        'property_type',
        'building_id',
        'floor',
        'flat_no',
        'villa_area_id',
        'villa_no',
        'document_path',
        'profile_image',
        'family_members',
        'name',
        'email',
        'phone',
        'address',
        'city',
        'state',
        'postal_code',
        'country',
        'id_type',
        'bank_name',
        'account_number',
        'ifsc_code',
        'status',
        'notes',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'family_members' => 'array',
    ];

    // Relationships
    public function society(): BelongsTo
    {
        return $this->belongsTo(Society::class);
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }

    public function villaArea(): BelongsTo
    {
        return $this->belongsTo(VillaArea::class);
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class, 'owner_id');
    }

    public function flats(): HasMany
    {
        return $this->hasMany(Flat::class, 'owner_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeInactive($query)
    {
        return $query->where('status', 'inactive');
    }

    public function scopeBySociety($query, $societyId)
    {
        return $query->where('society_id', $societyId);
    }

    // Helper methods
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function getPropertyDisplay(): string
    {
        if (!$this->property_type) {
            return '-';
        }

        if ($this->property_type === 'apartment') {
            $building = $this->building ? $this->building->name : 'Building';
            return "Apartment {$this->flat_no}";
        } elseif ($this->property_type === 'villa') {
            return "Villa {$this->villa_no}";
        }

        return '-';
    }

    public function getPropertiesCount(): int
    {
        // Count properties based on the owner's own property fields
        // An owner has 1 property if they have property_type set
        return $this->property_type ? 1 : 0;
    }

    public function getOccupiedPropertiesCount(): int
    {
        // For now, return 0 as we don't track occupancy at owner level
        return 0;
    }

    public function getVacantPropertiesCount(): int
    {
        // For now, return 0 as we don't track vacancy at owner level
        return 0;
    }
}
