<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class SubscriptionPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'monthly_price',
        'yearly_price',
        'max_flats',
        'max_users',
        'max_staff',
        'features',
        'limitations',
        'trial_days',
        'is_popular',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'monthly_price' => 'decimal:2',
            'yearly_price' => 'decimal:2',
            'features' => 'array',
            'limitations' => 'array',
            'is_popular' => 'boolean',
        ];
    }

    // Relationships
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function subscriptionPayments(): HasManyThrough
    {
        return $this->hasManyThrough(
            SubscriptionPayment::class,
            Subscription::class,
            'subscription_plan_id', // Foreign key on subscriptions table
            'subscription_id',       // Foreign key on subscription_payments table
            'id',                    // Local key on subscription_plans table
            'id'                     // Local key on subscriptions table
        );
    }

    public function societies(): HasManyThrough
    {
        return $this->hasManyThrough(
            Society::class,
            Subscription::class,
            'subscription_plan_id', // Foreign key on subscriptions table
            'id',                    // Foreign key on societies table (actually local key)
            'id',                    // Local key on subscription_plans table
            'society_id'             // Local key on subscriptions table
        );
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopePopular($query)
    {
        return $query->where('is_popular', true);
    }

    // Helper methods
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isPopular(): bool
    {
        return $this->is_popular;
    }

    public function getYearlySavings(): float
    {
        $yearlyMonthly = $this->monthly_price * 12;
        return $yearlyMonthly - $this->yearly_price;
    }

    public function getYearlySavingsPercentage(): float
    {
        $yearlyMonthly = $this->monthly_price * 12;
        if ($yearlyMonthly == 0) return 0;
        
        return (($yearlyMonthly - $this->yearly_price) / $yearlyMonthly) * 100;
    }

    public function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->features ?? []);
    }

    public function hasLimitation(string $limitation): bool
    {
        return in_array($limitation, $this->limitations ?? []);
    }

    public function isUnlimited(string $type): bool
    {
        return match ($type) {
            'flats' => $this->max_flats === 0,
            'users' => $this->max_users === 0,
            'staff' => $this->max_staff === 0,
            default => false,
        };
    }
}