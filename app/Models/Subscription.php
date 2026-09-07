<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'society_id',
        'subscription_plan_id',
        'billing_cycle',
        'amount',
        'start_date',
        'end_date',
        'next_billing_date',
        'status',
        'auto_renew',
        'cancellation_reason',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'start_date' => 'date',
            'end_date' => 'date',
            'next_billing_date' => 'date',
            'auto_renew' => 'boolean',
            'cancelled_at' => 'datetime',
        ];
    }

    // Relationships
    public function society(): BelongsTo
    {
        return $this->belongsTo(Society::class);
    }

    public function subscriptionPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SubscriptionPayment::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeExpired($query)
    {
        return $query->where('status', 'expired');
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    public function scopeExpiringSoon($query, int $days = 7)
    {
        return $query->where('status', 'active')
            ->where('end_date', '<=', now()->addDays($days));
    }

    // Helper methods
    public function isActive(): bool
    {
        return $this->status === 'active' && $this->end_date >= now();
    }

    public function isExpired(): bool
    {
        return $this->status === 'expired' || $this->end_date < now();
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isExpiringSoon(int $days = 7): bool
    {
        return $this->isActive() && $this->end_date <= now()->addDays($days);
    }

    public function getDaysRemaining(): int
    {
        if (!$this->isActive()) {
            return 0;
        }

        return now()->diffInDays($this->end_date);
    }

    public function cancel(string $reason = null): void
    {
        $this->update([
            'status' => 'cancelled',
            'auto_renew' => false,
            'cancellation_reason' => $reason,
            'cancelled_at' => now(),
        ]);
    }

    public function renew(int $months = null): void
    {
        $months = $months ?? ($this->billing_cycle === 'yearly' ? 12 : 1);
        
        $this->update([
            'start_date' => $this->end_date,
            'end_date' => $this->end_date->addMonths($months),
            'next_billing_date' => $this->billing_cycle === 'yearly' 
                ? $this->end_date->addYear() 
                : $this->end_date->addMonth(),
            'status' => 'active',
        ]);
    }

    public function suspend(): void
    {
        $this->update(['status' => 'suspended']);
    }

    public function reactivate(): void
    {
        if ($this->end_date >= now()) {
            $this->update(['status' => 'active']);
        }
    }

    public function getTotalPaid(): float
    {
        return $this->payments()->where('status', 'success')->sum('amount');
    }

    public function getLastPayment()
    {
        return $this->payments()->where('status', 'success')->latest()->first();
    }

    public function isMonthly(): bool
    {
        return $this->billing_cycle === 'monthly';
    }

    public function isYearly(): bool
    {
        return $this->billing_cycle === 'yearly';
    }
}