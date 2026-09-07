<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    use HasFactory;

    protected $fillable = [
        'society_id',
        'flat_id',
        'owner_id',
        'user_id',
        'name',
        'email',
        'phone',
        'status',
        'contract_start_date',
        'contract_end_date',
        'move_in_date',
        'move_out_date',
        'monthly_rent',
        'rent_billing_cycle',
        'security_deposit',
        'emergency_contact',
        'id_type',
        'document_path',
        'bank_name',
        'account_number',
        'ifsc_code',
        'family_members',
        'notes',
    ];

    protected $casts = [
        'move_in_date' => 'date',
        'move_out_date' => 'date',
        'contract_start_date' => 'date',
        'contract_end_date' => 'date',
        'monthly_rent' => 'decimal:2',
        'security_deposit' => 'decimal:2',
        'emergency_contact' => 'array',
        'family_members' => 'array',
    ];

    // Relationships
    public function society(): BelongsTo
    {
        return $this->belongsTo(Society::class);
    }

    public function flat(): BelongsTo
    {
        return $this->belongsTo(Flat::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
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

    public function getRentDueAttribute(): float
    {
        return $this->monthly_rent ?? 0;
    }

    public function getResidencyDurationAttribute(): int
    {
        $endDate = $this->move_out_date ?? now();
        return $this->move_in_date->diffInDays($endDate);
    }

    protected static function boot()
    {
        parent::boot();

        static::created(function ($tenant) {
            // Update flat status to on_rent for tenant-occupied properties
            if ($tenant->flat) {
                $tenant->flat->update(['status' => 'on_rent']);
            }
        });

        static::deleted(function ($tenant) {
            // Update flat status to vacant
            if ($tenant->flat) {
                $tenant->flat->update(['status' => 'vacant']);
            }
        });
    }
}
