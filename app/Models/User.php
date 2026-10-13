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
        'committee_designation',
        'status',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    // Converted from casts(): the casts() method is Laravel 11+;
    // on Laravel 10 it was never applied, so none of these casts
    // were active (dates stayed strings -> 500s in API formatters).
    protected $casts = [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'date_of_birth' => 'date',
            'last_login_at' => 'datetime',
        ];

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

    /** The reset link goes out as a queued, retried notification (see ResetPasswordNotification). */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new \App\Notifications\ResetPasswordNotification($token));
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
        return $this->hasAnyRole(['Owner', 'Apartment Owner', 'Villa Owner', 'Tenant']);
    }

    /**
     * Owner is the canonical business role; Villa Owner/Apartment Owner are
     * legacy names for the same role, kept only for users not yet migrated
     * by `php artisan users:migrate-to-owner-role`.
     */
    public function isOwner(): bool
    {
        return $this->hasAnyRole(['Owner', 'Apartment Owner', 'Villa Owner']);
    }

    public function isApartmentOwner(): bool
    {
        return $this->hasRole('Apartment Owner')
            || ($this->hasRole('Owner') && $this->ownedFlat?->property_type === 'apartment');
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
        return $this->hasRole('Villa Owner')
            || ($this->hasRole('Owner') && $this->ownedVilla !== null);
    }

    /**
     * Get the villa owned by this user (if villa owner).
     *
     * Previously branched on `if ($this->owner_id)`, returning the real
     * hasOneThrough only when true and an always-empty relation otherwise.
     * That branch reads instance state that does not exist yet whenever
     * Eloquent builds this relation without a loaded row first -
     * whereHas('ownedVilla'), whereDoesntHave('ownedVilla'), and
     * with('ownedVilla') on a query builder all call this method against a
     * throwaway, unhydrated User, where $this->owner_id is always null.
     * Every such call silently took the dead branch
     * (`...->whereRaw('1 = 0')`), on top of a join condition
     * (`flats.owner_id = users.id`) that was never correct either -
     * flats.owner_id references owners.id, not users.id (CLAUDE.md §5).
     * `$user->ownedVilla` on an already-loaded instance was never affected,
     * since $this->owner_id is the real value there - only query-time use
     * was silently broken. A single unconditional hasOneThrough is correct
     * for both cases: the through join naturally returns nothing when
     * users.owner_id is null, with no branch needed.
     */
    public function ownedVilla()
    {
        return $this->hasOneThrough(
            Flat::class,
            Owner::class,
            'id',        // Foreign key on owners table
            'owner_id',  // Foreign key on flats table
            'owner_id',  // Local key on users table
            'id'         // Local key on owners table
        )->where('flats.property_type', 'villa');
    }

    /**
     * Get the apartment owned by this user (if apartment owner). See
     * ownedVilla()'s docblock - identical fix, identical reason.
     */
    public function ownedFlat()
    {
        return $this->hasOneThrough(
            Flat::class,
            Owner::class,
            'id',        // Foreign key on owners table
            'owner_id',  // Foreign key on flats table
            'owner_id',  // Local key on users table
            'id'         // Local key on owners table
        )->where('flats.property_type', 'apartment');
    }

    /**
     * Get all properties owned by this user. See ownedVilla()'s docblock -
     * identical fix. This is the one with the widest blast radius: it is
     * queried via whereHas('ownedProperties', ...) from
     * EmailNotificationService::getRecipients(), EmailTriggerService, and
     * three event listeners (SendBillGeneratedEmail,
     * SendPaymentOverdueEmail, SendTenantOwnerAddedEmail) to resolve "which
     * users own this flat" for a notification - every one of those lookups
     * returned zero owners, unconditionally, until this fix.
     */
    public function ownedProperties()
    {
        return $this->hasManyThrough(
            Flat::class,
            Owner::class,
            'id',        // Foreign key on owners table
            'owner_id',  // Foreign key on flats table
            'owner_id',  // Local key on users table
            'id'         // Local key on owners table
        );
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

    /**
     * Browser-loadable URL of the stored picture, or null when the user has
     * none (the layouts then fall back to a generated initials badge). The
     * API's avatar_url below stays a root-relative path for the mobile app.
     */
    public function getAvatarWebUrlAttribute(): ?string
    {
        return \App\Support\ProfileImage::webUrl($this->avatar);
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