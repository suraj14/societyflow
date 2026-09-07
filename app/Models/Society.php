<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Society extends Model
{
    use HasFactory;

    protected $fillable = [
        'admin_id',
        'subscription_plan_id',
        'name',
        'slug',
        'subdomain',
        'address',
        'city',
        'state',
        'country',
        'pincode',
        'phone',
        'email',
        'website',
        'description',
        'logo',
        'settings',
        'status',
        'trial_ends_at',
        'subscription_ends_at',
    ];

    protected $casts = [
        'settings' => 'array',
        'trial_ends_at' => 'datetime',
        'subscription_ends_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($society) {
            if (empty($society->slug)) {
                $society->slug = Str::slug($society->name);
            }
        });
    }

    // Relationships
    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function subscriptionPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function buildings(): HasMany
    {
        return $this->hasMany(Building::class);
    }

    public function flats(): HasMany
    {
        return $this->hasMany(Flat::class);
    }

    public function residents(): HasMany
    {
        return $this->hasMany(Resident::class);
    }

    public function staff(): HasMany
    {
        return $this->hasMany(Staff::class);
    }

    public function maintenanceBills(): HasMany
    {
        return $this->hasMany(MaintenanceBill::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function expenseCategories(): HasMany
    {
        return $this->hasMany(ExpenseCategory::class);
    }

    public function complaints(): HasMany
    {
        return $this->hasMany(Complaint::class);
    }

    public function complaintCategories(): HasMany
    {
        return $this->hasMany(ComplaintCategory::class);
    }

    public function notices(): HasMany
    {
        return $this->hasMany(Notice::class);
    }

    public function facilities(): HasMany
    {
        return $this->hasMany(Facility::class);
    }

    public function facilityBookings(): HasMany
    {
        return $this->hasMany(FacilityBooking::class);
    }

    public function visitors(): HasMany
    {
        return $this->hasMany(Visitor::class);
    }

    public function villaAreas(): HasMany
    {
        return $this->hasMany(VillaArea::class);
    }

    public function villas(): HasMany
    {
        return $this->hasMany(Flat::class)->where('property_type', 'villa');
    }

    public function apartments(): HasMany
    {
        return $this->hasMany(Flat::class)->where('property_type', 'apartment');
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->latest();
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function settings(): HasMany
    {
        return $this->hasMany(Setting::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeWithActiveSubscription($query)
    {
        return $query->whereHas('subscription', function ($q) {
            $q->where('status', 'active')
              ->where('end_date', '>=', now());
        });
    }

    // Helper methods
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function hasActiveSubscription(): bool
    {
        return $this->subscription && 
               $this->subscription->status === 'active' && 
               $this->subscription->end_date >= now();
    }

    public function isOnTrial(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at > now();
    }

    public function hasAdmin(): bool
    {
        return $this->admin_id && $this->admin;
    }

    public function canAssignAdmin(): bool
    {
        return !$this->hasAdmin();
    }

    public function assignAdmin(User $user): void
    {
        if (!$this->canAssignAdmin()) {
            throw new \Exception('Society already has an admin assigned.');
        }

        $this->update(['admin_id' => $user->id]);
        $user->assignRole('Admin');
        $user->update(['society_id' => $this->id]);
    }

    public function removeAdmin(): void
    {
        if ($this->admin) {
            $this->admin->removeRole('Admin');
            $this->admin->update(['society_id' => null]);
        }
        
        $this->update(['admin_id' => null]);
    }

    public function resetAdminPassword(string $newPassword): void
    {
        if (!$this->admin) {
            throw new \Exception('No admin assigned to this society.');
        }

        $this->admin->update(['password' => bcrypt($newPassword)]);
    }

    public function getLogoUrlAttribute(): string
    {
        if ($this->logo) {
            return asset('storage/' . $this->logo);
        }
        
        return asset('images/default-society-logo.png');
    }

    public function getSetting(string $key, $default = null)
    {
        $setting = $this->settings()->where('key', $key)->first();
        
        if (!$setting) {
            return $default;
        }

        return match ($setting->type) {
            'boolean' => (bool) $setting->value,
            'integer' => (int) $setting->value,
            'json', 'array' => json_decode($setting->value, true),
            default => $setting->value,
        };
    }

    public function setSetting(string $key, $value, string $type = 'string'): void
    {
        $this->settings()->updateOrCreate(
            ['key' => $key],
            [
                'value' => is_array($value) || is_object($value) ? json_encode($value) : $value,
                'type' => $type,
            ]
        );
    }
}