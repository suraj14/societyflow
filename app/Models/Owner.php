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

    protected static function boot()
    {
        parent::boot();

        // An owner record is one of the two facts occupancy is derived from
        // (App\Support\PropertyOccupancy). Keep the stored flats.status cache
        // of every unit this owner is linked to - by flats.owner_id or by
        // address - in step when the owner is created, changed (status,
        // address) or removed. The Tenant model does the same for tenancies.
        static::saved(function (Owner $owner) {
            $owner->resyncLinkedFlats($owner->getOriginal());
        });

        static::deleted(function (Owner $owner) {
            $owner->resyncLinkedFlats();
        });
    }

    /**
     * Re-derive the stored status of the flats this owner record points at
     * now and, after an address change, the ones it pointed at before.
     */
    public function resyncLinkedFlats(array $previous = []): void
    {
        $query = Flat::query()->where(function ($q) use ($previous) {
            $q->where('owner_id', $this->id);

            foreach ([$this->getAttributes(), $previous] as $attrs) {
                if (empty($attrs['society_id'])) {
                    continue;
                }
                if (($attrs['property_type'] ?? null) === 'villa' && !empty($attrs['villa_area_id']) && isset($attrs['villa_no'])) {
                    $q->orWhere(fn ($w) => $w->where('society_id', $attrs['society_id'])
                        ->where('property_type', 'villa')
                        ->where('villa_area_id', $attrs['villa_area_id'])
                        ->where('flat_number', $attrs['villa_no']));
                } elseif (($attrs['property_type'] ?? null) === 'apartment' && !empty($attrs['building_id']) && isset($attrs['flat_no'])) {
                    $q->orWhere(fn ($w) => $w->where('society_id', $attrs['society_id'])
                        ->where('property_type', 'apartment')
                        ->where('building_id', $attrs['building_id'])
                        ->where('flat_number', $attrs['flat_no']));
                }
            }
        });

        foreach ($query->get() as $flat) {
            $flat->updateStatus();
        }
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

    public function user(): HasOne
    {
        return $this->hasOne(User::class, 'owner_id');
    }

    public function flats(): HasMany
    {
        return $this->hasMany(Flat::class, 'owner_id');
    }

    /**
     * The inverse of Flat::resolvedOwner(): flats.owner_id is unset on most
     * rows (see UserFlatResolver / P1-7), so a direct flats() query returns
     * nothing for most real owners even though they do own a unit. Falls
     * back to the same villa_area_id+villa_no / building_id+flat_no address
     * match the property pages already use, so an owner's dashboard finds
     * their real unit instead of reporting "no property" (which previously
     * led callers to fabricate a brand new one).
     */
    public function resolvedFlats()
    {
        $direct = $this->flats;
        if ($direct->isNotEmpty()) {
            return $direct;
        }

        if ($this->property_type === 'villa' && $this->villa_area_id && $this->villa_no) {
            return Flat::with(['building', 'villaArea'])
                ->where('society_id', $this->society_id)
                ->where('property_type', 'villa')
                ->where('villa_area_id', $this->villa_area_id)
                ->where('flat_number', $this->villa_no)
                ->get();
        }

        if ($this->property_type === 'apartment' && $this->building_id && $this->flat_no) {
            return Flat::with(['building', 'villaArea'])
                ->where('society_id', $this->society_id)
                ->where('property_type', 'apartment')
                ->where('building_id', $this->building_id)
                ->where('flat_number', $this->flat_no)
                ->get();
        }

        return collect();
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

    /** Properties this owner owns that are lived in (by the owner or a tenant). */
    public function getOccupiedPropertiesCount(): int
    {
        return $this->countPropertiesByOccupancy(true);
    }

    /** Properties this owner owns that are currently vacant. */
    public function getVacantPropertiesCount(): int
    {
        return $this->countPropertiesByOccupancy(false);
    }

    private function countPropertiesByOccupancy(bool $occupied): int
    {
        $states = \App\Support\PropertyOccupancy::statesFor($this->resolvedFlats());

        return collect($states)
            ->filter(fn ($row) => ($row['state'] !== \App\Support\PropertyOccupancy::VACANT) === $occupied)
            ->count();
    }
}
