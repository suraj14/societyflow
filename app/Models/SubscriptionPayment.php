<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'subscription_id',
        'payment_id',
        'transaction_id',
        'amount',
        'payment_method',
        'gateway_transaction_id',
        'gateway_response',
        'status',
        'payment_date',
        'notes',
    ];

    // Converted from casts(): the casts() method is Laravel 11+;
    // on Laravel 10 it was never applied, so none of these casts
    // were active (dates stayed strings -> 500s in API formatters).
    protected $casts = [
            'amount' => 'decimal:2',
            'gateway_response' => 'array',
            'payment_date' => 'date',
        ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($payment) {
            if (empty($payment->payment_id)) {
                $payment->payment_id = static::generatePaymentId();
            }
        });
    }

    // Relationships
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * The paying society and the plan live on the subscription, not on the
     * payment row. The Super Admin billing screens asked for
     * `$payment->society` / `$payment->subscriptionPlan` directly, which did
     * not exist: they only looked fine because the table was empty (eager
     * loading nothing cannot fail), and would have crashed on the first payment.
     */
    public function society(): \Illuminate\Database\Eloquent\Relations\HasOneThrough
    {
        return $this->hasOneThrough(Society::class, Subscription::class, 'id', 'id', 'subscription_id', 'society_id');
    }

    public function subscriptionPlan(): \Illuminate\Database\Eloquent\Relations\HasOneThrough
    {
        return $this->hasOneThrough(SubscriptionPlan::class, Subscription::class, 'id', 'id', 'subscription_id', 'subscription_plan_id');
    }

    // The billing screens' vocabulary, derived from the subscription / the stored method.
    public function getBillingCycleAttribute(): ?string
    {
        return $this->subscription?->billing_cycle;
    }

    public function getNextPaymentDateAttribute()
    {
        return $this->subscription?->next_billing_date;
    }

    public function getPaymentGatewayAttribute(): ?string
    {
        return $this->payment_method;
    }

    // Scopes
    public function scopeSuccess($query)
    {
        return $query->where('status', 'success');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    // Helper methods
    public function isSuccess(): bool
    {
        return $this->status === 'success';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isRefunded(): bool
    {
        return $this->status === 'refunded';
    }

    public function markAsSuccess(array $gatewayData = []): void
    {
        $this->update([
            'status' => 'success',
            'gateway_response' => $gatewayData,
            'payment_date' => now(),
        ]);
    }

    public function markAsFailed(array $gatewayData = []): void
    {
        $this->update([
            'status' => 'failed',
            'gateway_response' => $gatewayData,
        ]);
    }

    public function refund(array $gatewayData = []): void
    {
        $this->update([
            'status' => 'refunded',
            'gateway_response' => array_merge($this->gateway_response ?? [], $gatewayData),
        ]);
    }

    public static function generatePaymentId(): string
    {
        $prefix = 'SUB';
        $timestamp = now()->format('YmdHis');
        $random = str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        return $prefix . $timestamp . $random;
    }
}