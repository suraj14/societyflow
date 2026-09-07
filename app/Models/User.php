<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles, HasApiTokens;

    protected $fillable = [
        'society_id',
        'owner_id',
        'tenant_id',
        'name',
        'email',
        'password',
        'phone',
        'date_of_birth',
        'gender',
        'address',
        'avatar',
        'status',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'date_of_birth' => 'date',
            'last_login_at' => 'datetime',
        ];
    }

    // Relationships
    public function society(): BelongsTo
    {
        return $this->belongsTo(Society::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function resident(): HasOne
    {
        return $this->hasOne(Resident::class)->where('status', 'active');
    }

    public function residents(): HasMany
    {
        return $this->hasMany(Resident::class);
    }

    public function staff(): HasOne
    {
        return $this->hasOne(Staff::class);
    }

    public function maintenanceBills(): HasMany
    {
        return $this->hasMany(MaintenanceBill::class, 'flat_id', 'id')
            ->through('resident');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function complaints(): HasMany
    {
        return $this->hasMany(Complaint::class, 'created_by');
    }

    public function assignedComplaints(): HasMany
    {
        return $this->hasMany(Complaint::class, 'assigned_to');
    }

    public function facilityBookings(): HasMany
    {
        return $this->hasMany(FacilityBooking::class);
    }

    public function visitors(): HasMany
    {
        return $this->hasMany(Visitor::class, 'host_user_id');
    }

    public function notices(): HasMany
    {
        return $this->hasMany(Notice::class, 'created_by');
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
    public function isSuperAdmin(): bool
    {
        return $this->hasRole('Super Admin');
    }

    public function isAdmin(): bool
    {
        return $this->hasAnyRole(['Super Admin', 'Admin']);
    }

    public function isSocietyAdmin(): bool
    {
        return $this->hasRole('Admin');
    }

    public function isResident(): bool
    {
        return $this->hasAnyRole(['Apartment Owner', 'Villa Owner', 'Tenant']);
    }

    public function isOwner(): bool
    {
        return $this->hasAnyRole(['Apartment Owner', 'Villa Owner']);
    }

    public function isApartmentOwner(): bool
    {
        return $this->hasRole('Apartment Owner');
    }

    public function isTenant(): bool
    {
        return $this->hasRole('Tenant');
    }

    public function isStaff(): bool
    {
        return $this->hasRole('Staff');
    }

    public function isVillaOwner(): bool
    {
        return $this->hasRole('Villa Owner');
    }

    /**
     * Get the villa owned by this user (if villa owner)
     */
    public function ownedVilla()
    {
        // Since flats.owner_id references owners.id, we need to go through the owner relationship
        if ($this->owner_id) {
            return $this->hasOneThrough(
                Flat::class,
                Owner::class,
                'id',        // Foreign key on owners table
                'owner_id',  // Foreign key on flats table
                'owner_id',  // Local key on users table
                'id'         // Local key on owners table
            )->where('flats.property_type', 'villa');
        }
        
        return $this->hasOne(Flat::class, 'owner_id')->where('flats.property_type', 'villa')->whereRaw('1 = 0'); // Return empty relation
    }

    /**
     * Get the apartment owned by this user (if apartment owner)
     */
    public function ownedFlat()
    {
        // Since flats.owner_id references owners.id, we need to go through the owner relationship
        if ($this->owner_id) {
            return $this->hasOneThrough(
                Flat::class,
                Owner::class,
                'id',        // Foreign key on owners table
                'owner_id',  // Foreign key on flats table
                'owner_id',  // Local key on users table
                'id'         // Local key on owners table
            )->where('flats.property_type', 'apartment');
        }
        
        return $this->hasOne(Flat::class, 'owner_id')->where('flats.property_type', 'apartment')->whereRaw('1 = 0'); // Return empty relation
    }

    /**
     * Get all properties owned by this user
     */
    public function ownedProperties()
    {
        // Since flats.owner_id references owners.id, we need to go through the owner relationship
        if ($this->owner_id) {
            return $this->hasManyThrough(
                Flat::class,
                Owner::class,
                'id',        // Foreign key on owners table
                'owner_id',  // Foreign key on flats table
                'owner_id',  // Local key on users table
                'id'         // Local key on owners table
            );
        }
        
        return $this->hasMany(Flat::class, 'owner_id')->whereRaw('1 = 0'); // Return empty relation
    }

    /**
     * Get all flats the user is associated with through residents table
     */
    public function flats()
    {
        return $this->hasManyThrough(
            Flat::class,
            Resident::class,
            'user_id', // Foreign key on residents table
            'id',      // Foreign key on flats table
            'id',      // Local key on users table
            'flat_id'  // Local key on residents table
        )->where('residents.status', 'active')
         ->select('flats.*'); // Explicitly select from flats table to avoid ambiguity
    }

    public function getFullNameAttribute(): string
    {
        return $this->name;
    }

    public function getAvatarUrlAttribute(): string
    {
        if ($this->avatar) {
            // Return relative path — Flutter resolves with server base URL
            return '/storage/' . ltrim($this->avatar, '/');
        }
        
        return 'https://ui-avatars.com/api/?name=' . urlencode($this->name) . '&background=4F46E5&color=fff&size=128';
    }
}