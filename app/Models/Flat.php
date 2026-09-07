<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Flat extends Model
{
    use HasFactory;

    protected $fillable = [
        'society_id',
        'building_id',
        'property_type',
        'villa_area_id',
        'owner_id',
        'flat_number',
        'villa_name',
        'floor',
        'type',
        'carpet_area',
        'built_up_area',
        'plot_area',
        'bedrooms',
        'bathrooms',
        'has_garden',
        'has_parking',
        'parking_slots',
        'maintenance_amount',
        'status',
        'amenities',
        'description',
    ];

    protected $casts = [
        'amenities' => 'array',
        'carpet_area' => 'decimal:2',
        'built_up_area' => 'decimal:2',
        'plot_area' => 'decimal:2',
        'maintenance_amount' => 'decimal:2',
        'has_garden' => 'boolean',
        'has_parking' => 'boolean',
    ];

    // Valid status values
    public const VALID_STATUSES = [
        'occupied',
        'vacant', 
        'maintenance',
        'on_rent',
        'under_maintenance'
    ];

    // Boot method to add validation
    protected static function boot()
    {
        parent::boot();
        
        static::saving(function ($flat) {
            // Validate status field
            if (!in_array($flat->status, self::VALID_STATUSES)) {
                throw new \InvalidArgumentException("Invalid status: {$flat->status}. Valid statuses are: " . implode(', ', self::VALID_STATUSES));
            }
        });
    }

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

    public function ownerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function residents(): HasMany
    {
        return $this->hasMany(Resident::class);
    }

    public function activeResident(): HasOne
    {
        return $this->hasOne(Resident::class)->where('status', 'active');
    }

    public function owner(): HasOne
    {
        return $this->hasOne(Resident::class)->where('type', 'owner')->where('status', 'active');
    }

    public function tenant(): HasOne
    {
        return $this->hasOne(Resident::class)->where('type', 'tenant')->where('status', 'active');
    }

    // New relationships for Owner and Tenant models
    public function ownerModel(): BelongsTo
    {
        return $this->belongsTo(Owner::class, 'owner_id');
    }

    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class);
    }

    public function activeTenant(): HasOne
    {
        return $this->hasOne(Tenant::class)->where('status', 'active');
    }

    public function maintenanceBills(): HasMany
    {
        return $this->hasMany(MaintenanceBill::class);
    }

    public function complaints(): HasMany
    {
        return $this->hasMany(Complaint::class);
    }

    public function facilityBookings(): HasMany
    {
        return $this->hasMany(FacilityBooking::class);
    }

    public function visitors(): HasMany
    {
        return $this->hasMany(Visitor::class);
    }

    // Scopes
    public function scopeApartments($query)
    {
        return $query->where('property_type', 'apartment');
    }

    public function scopeVillas($query)
    {
        return $query->where('property_type', 'villa');
    }

    public function scopeOccupied($query)
    {
        return $query->where('status', 'occupied');
    }

    public function scopeVacant($query)
    {
        return $query->where('status', 'vacant');
    }

    public function scopeBySociety($query, $societyId)
    {
        return $query->where('society_id', $societyId);
    }

    public function scopeByBuilding($query, $buildingId)
    {
        return $query->where('building_id', $buildingId);
    }

    public function scopeByVillaArea($query, $villaAreaId)
    {
        return $query->where('villa_area_id', $villaAreaId);
    }

    // Helper methods
    public function isVilla(): bool
    {
        return $this->property_type === 'villa';
    }

    public function isApartment(): bool
    {
        return $this->property_type === 'apartment';
    }

    public function getUnitNumberAttribute(): string
    {
        if ($this->isVilla()) {
            return $this->villa_name ?? $this->flat_number;
        }
        return $this->flat_number;
    }

    public function getUnitLabelAttribute(): string
    {
        return $this->isVilla() ? 'Villa' : 'Flat';
    }

    public function getFullAddressAttribute(): string
    {
        if ($this->isVilla()) {
            $area = $this->villaArea ? $this->villaArea->name : '';
            return ($this->villa_name ?? $this->flat_number) . ($area ? ', ' . $area : '');
        }
        return $this->flat_number . ', ' . ($this->building ? $this->building->name : '');
    }

    public function getLocationAttribute(): string
    {
        if ($this->isVilla()) {
            return $this->villaArea ? $this->villaArea->name : 'Villa Area';
        }
        return $this->building ? $this->building->name : 'Building';
    }

    public function getCurrentResident()
    {
        return $this->activeResident;
    }

    public function isOccupied(): bool
    {
        return $this->status === 'occupied';
    }

    public function isVacant(): bool
    {
        return $this->status === 'vacant';
    }

    public function getPendingDues(): float
    {
        return $this->maintenanceBills()
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->sum('balance_amount');
    }

    public function getLastPaymentDate()
    {
        $lastBill = $this->maintenanceBills()
            ->where('status', 'paid')
            ->latest('bill_date')
            ->first();

        return $lastBill ? $lastBill->bill_date : null;
    }

    public function updateStatus(): void
    {
        $hasActiveResident = $this->residents()->where('status', 'active')->exists();
        
        $this->update([
            'status' => $hasActiveResident ? 'occupied' : 'vacant'
        ]);
    }
}
