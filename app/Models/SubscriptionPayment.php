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

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'gateway_response' => 'array',
            'payment_date' => 'date',
        ];
    }

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