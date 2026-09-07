<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'society_id',
        'flat_id',
        'maintenance_bill_id',
        'user_id',
        'payment_id',
        'transaction_id',
        'amount',
        'payment_method',
        'payment_gateway',
        'gateway_transaction_id',
        'gateway_response',
        'status',
        'payment_date',
        'bill_type',
        'due_date',
        'notes',
        'receipt_number',
        'receipt_file_path',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'gateway_response' => 'array',
            'payment_date' => 'date',
            'due_date' => 'date',
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
    public function society(): BelongsTo
    {
        return $this->belongsTo(Society::class);
    }

    public function flat(): BelongsTo
    {
        return $this->belongsTo(Flat::class);
    }

    public function maintenanceBill(): BelongsTo
    {
        return $this->belongsTo(MaintenanceBill::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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

    public function scopeBySociety($query, $societyId)
    {
        return $query->where('society_id', $societyId);
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('payment_date', [$startDate, $endDate]);
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

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function markAsSuccess(array $gatewayData = []): void
    {
        $this->update([
            'status' => 'success',
            'gateway_response' => $gatewayData,
            'payment_date' => now(),
        ]);

        // Update maintenance bill
        $this->maintenanceBill->paid_amount += $this->amount;
        $this->maintenanceBill->save();
    }

    public function markAsFailed(array $gatewayData = []): void
    {
        $this->update([
            'status' => 'failed',
            'gateway_response' => $gatewayData,
        ]);
    }

    public function getReceiptUrl(): string
    {
        return route('payments.receipt', $this->payment_id);
    }

    public static function generatePaymentId(): string
    {
        $prefix = 'PAY';
        $timestamp = now()->format('YmdHis');
        $random = str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        return $prefix . $timestamp . $random;
    }

    public function generateReceiptNumber(): string
    {
        if ($this->receipt_number) {
            return $this->receipt_number;
        }

        $prefix = 'RCP';
        $societyCode = str_pad($this->society_id, 3, '0', STR_PAD_LEFT);
        $sequence = static::where('society_id', $this->society_id)
            ->where('status', 'success')
            ->count();
        $sequenceCode = str_pad($sequence, 6, '0', STR_PAD_LEFT);
        
        $receiptNumber = $prefix . $societyCode . date('Y') . $sequenceCode;
        
        $this->update(['receipt_number' => $receiptNumber]);
        
        return $receiptNumber;
    }
}