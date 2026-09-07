<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Resident extends Model
{
    use HasFactory;

    protected $fillable = [
        'society_id',
        'user_id',
        'name',
        'email',
        'phone',
        'flat_id',
        'type',
        'move_in_date',
        'move_out_date',
        'security_deposit',
        'monthly_rent',
        'family_members',
        'vehicles',
        'emergency_contact',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'move_in_date' => 'date',
            'move_out_date' => 'date',
            'security_deposit' => 'decimal:2',
            'monthly_rent' => 'decimal:2',
            'family_members' => 'array',
            'vehicles' => 'array',
            'emergency_contact' => 'array',
            'notes' => 'array',
        ];
    }

    // Relationships
    public function society(): BelongsTo
    {
        return $this->belongsTo(Society::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function flat(): BelongsTo
    {
        return $this->belongsTo(Flat::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeOwners($query)
    {
        return $query->where('type', 'owner');
    }

    public function scopeTenants($query)
    {
        return $query->where('type', 'tenant');
    }

    public function scopeBySociety($query, $societyId)
    {
        return $query->where('society_id', $societyId);
    }

    // Helper methods
    public function isOwner(): bool
    {
        return $this->type === 'owner';
    }

    public function isTenant(): bool
    {
        return $this->type === 'tenant';
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function getResidencyDuration(): int
    {
        $endDate = $this->move_out_date ?? now();
        return $this->move_in_date->diffInDays($endDate);
    }

    public function getFamilyMembersCount(): int
    {
        return count($this->family_members ?? []);
    }

    public function getVehiclesCount(): int
    {
        return count($this->vehicles ?? []);
    }

    public function getFullAddressAttribute(): string
    {
        return $this->flat->full_address . ', ' . $this->society->name;
    }

    protected static function boot()
    {
        parent::boot();

        static::created(function ($resident) {
            $resident->flat->updateStatus();
        });

        static::updated(function ($resident) {
            if ($resident->wasChanged('status')) {
                $resident->flat->updateStatus();
            }
        });

        static::deleted(function ($resident) {
            $resident->flat->updateStatus();
        });
    }
}