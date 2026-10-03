<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

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

    /**
     * The stored statuses that describe occupancy. They are derived, never
     * accepted from a request - see saving() below.
     */
    public const OCCUPANCY_STATUSES = ['occupied', 'vacant', 'on_rent'];

    /** The stored statuses a person may set by hand. */
    public const MANUAL_STATUSES = ['maintenance', 'under_maintenance'];

    // Boot method to add validation
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($flat) {
            // Validate status field
            if (!in_array($flat->status, self::VALID_STATUSES)) {
                throw new \InvalidArgumentException("Invalid status: {$flat->status}. Valid statuses are: " . implode(', ', self::VALID_STATUSES));
            }

            // Occupancy is business state (owner assigned, tenant assigned,
            // tenant moved out), not something a form chooses. Any write of
            // occupied / vacant / on_rent is replaced with the value the
            // canonical rule derives, so a stale or contradictory status can
            // never be stored - whichever of the ~25 call sites wrote it.
            // "Under maintenance" is the one state that is genuinely a
            // decision, so it is kept as submitted.
            if (in_array($flat->status, self::OCCUPANCY_STATUSES, true)) {
                $flat->status = self::derivedStoredStatus($flat);
            }
        });
    }

    /**
     * The stored status the canonical occupancy rule implies for this flat:
     * tenant -> on_rent, owner -> occupied, neither -> vacant. Works for an
     * unsaved flat too (no tenants can exist yet; the owner is resolved from
     * owner_id or the address fields already set on the model).
     */
    public static function derivedStoredStatus(Flat $flat): string
    {
        return match (\App\Support\PropertyOccupancy::stateOf($flat)) {
            \App\Support\PropertyOccupancy::TENANT => 'on_rent',
            \App\Support\PropertyOccupancy::OWNER  => 'occupied',
            default                                 => 'vacant',
        };
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

    /**
     * The owner's login account.
     *
     * owner_id is a foreign key to owners.id, so this has to travel through
     * the Owner record - users.owner_id points back at it. It used to be
     * belongsTo(User::class, 'owner_id'), which read an owners.id as if it
     * were a users.id: both tables are integer-keyed from 1, so it silently
     * returned whichever unrelated user happened to share that number.
     *
     * An owner with no login account yields null, which is legitimate -
     * owners are created before their account in some flows.
     */
    public function ownerUser(): HasOneThrough
    {
        return $this->hasOneThrough(
            User::class,     // what we want
            Owner::class,    // what we go through
            'id',            // owners.id ...
            'owner_id',      // ... users.owner_id
            'owner_id',      // flats.owner_id ...
            'id'             // ... matches owners.id
        );
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

    /**
     * The owner this flat actually belongs to, resolved the same two-tier
     * way UserFlatResolver::ownedFlatIds() already does in the other
     * direction (user -> flats): primary link is flats.owner_id; when that
     * is unset (108 of 121 rows in the dev database - see
     * UserFlatResolver's own docblock and TODO.md's P1-7), fall back to
     * matching the address fields the owner record itself carries
     * (villa_area_id+villa_no for a villa, building_id+flat_no for an
     * apartment). Owner Management's own show page
     * (resources/views/owners/show.blade.php) has only ever read a
     * property from those denormalized owner fields, never from
     * flats.owner_id - so a flat-centric screen that only checked
     * ownerModel() (the direct FK) disagreed with Owner Management for
     * every unlinked row, showing "Owner: -" for a unit Owner Management
     * correctly shows as owned.
     *
     * This is read-time resolution, not a second stored assignment - it
     * does not create or duplicate anything, and it does not write
     * flats.owner_id (P1-7 is explicit that any bulk backfill must come
     * from records of actual ownership, never inferred matching).
     */
    public function resolvedOwner(): ?Owner
    {
        // One resolution rule for the whole application. For a list of flats
        // call PropertyOccupancy::statesFor()/ownersFor() once instead of
        // this per flat.
        if ($this->owner_id && $this->relationLoaded('ownerModel')) {
            return $this->ownerModel ?: null;
        }

        return \App\Support\PropertyOccupancy::ownersFor(collect([$this]))[$this->id] ?? null;
    }

    /**
     * Canonical occupancy: 'tenant_occupied' | 'owner_occupied' | 'vacant'.
     * See App\Support\PropertyOccupancy - the stored `status` column is not
     * the source of truth.
     */
    public function occupancyState(): string
    {
        return \App\Support\PropertyOccupancy::stateOf($this);
    }

    public function getOccupancyLabelAttribute(): string
    {
        return \App\Support\PropertyOccupancy::label($this->occupancyState());
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
        return $this->occupancyState() !== \App\Support\PropertyOccupancy::VACANT;
    }

    public function isVacant(): bool
    {
        return $this->occupancyState() === \App\Support\PropertyOccupancy::VACANT;
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

    /**
     * Bring the stored `status` column in line with the canonical occupancy
     * (tenant -> on_rent, owner -> occupied, neither -> vacant). The column
     * is only a cache for the few queries that filter on it (rent unit
     * pickers); nothing should read it to decide what to display. This used
     * to flip on active *residents* only, which overwrote on_rent whenever
     * a resident row changed.
     *
     * A manually set 'under_maintenance' / 'maintenance' is left alone.
     */
    public function updateStatus(): void
    {
        if (in_array($this->status, self::MANUAL_STATUSES, true)) {
            return;
        }

        $stored = self::derivedStoredStatus($this);

        if ($this->status !== $stored) {
            $this->update(['status' => $stored]);
        }
    }
}
