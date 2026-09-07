<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaintenanceBill extends Model
{
    use HasFactory;

    protected $fillable = [
        'society_id',
        'flat_id',
        'bill_number',
        'month',
        'year',
        'bill_date',
        'due_date',
        'maintenance_amount',
        'water_charges',
        'electricity_charges',
        'parking_charges',
        'penalty_amount',
        'other_charges',
        'total_amount',
        'paid_amount',
        'balance_amount',
        'status',
        'notes',
    ];

    /**
     * Get fillable attributes dynamically based on existing columns
     */
    public function getFillable()
    {
        $fillable = $this->fillable;
        
        try {
            // Add bill_type if column exists
            if (\Illuminate\Support\Facades\Schema::hasColumn('maintenance_bills', 'bill_type')) {
                $fillable[] = 'bill_type';
            }
            
            // Add paid_date if column exists
            if (\Illuminate\Support\Facades\Schema::hasColumn('maintenance_bills', 'paid_date')) {
                $fillable[] = 'paid_date';
            }
        } catch (\Exception $e) {
            // If we can't check columns, just use the base fillable array
        }
        
        return $fillable;
    }

    protected function casts(): array
    {
        $casts = [
            'bill_date' => 'date',
            'due_date' => 'date',
            'maintenance_amount' => 'decimal:2',
            'water_charges' => 'decimal:2',
            'electricity_charges' => 'decimal:2',
            'parking_charges' => 'decimal:2',
            'penalty_amount' => 'decimal:2',
            'other_charges' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'balance_amount' => 'decimal:2',
        ];
        
        try {
            // Add paid_date cast only if column exists
            if (\Illuminate\Support\Facades\Schema::hasColumn('maintenance_bills', 'paid_date')) {
                $casts['paid_date'] = 'date';
            }
        } catch (\Exception $e) {
            // If we can't check columns, just use the base casts
        }
        
        return $casts;
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($bill) {
            if (empty($bill->bill_number)) {
                $bill->bill_number = static::generateBillNumber($bill->society_id);
            }
            
            $bill->calculateTotalAmount();
        });

        static::updating(function ($bill) {
            // Capture manually-set status before recalculation
            $manualStatus = $bill->isDirty('status') ? $bill->status : null;

            $bill->calculateTotalAmount();

            // If admin explicitly set a status, always restore it
            if ($manualStatus !== null) {
                $bill->status = $manualStatus;

                // If manually marked as paid, ensure balance is zeroed
                if ($manualStatus === 'paid') {
                    $bill->paid_amount   = $bill->total_amount;
                    $bill->balance_amount = 0;
                }
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

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', 'overdue');
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    public function scopeBySociety($query, $societyId)
    {
        return $query->where('society_id', $societyId);
    }

    public function scopeForMonth($query, $month, $year)
    {
        return $query->where('month', $month)->where('year', $year);
    }

    // Helper methods
    public function calculateTotalAmount(): void
    {
        $this->total_amount = $this->maintenance_amount + 
                             $this->water_charges + 
                             $this->electricity_charges + 
                             $this->parking_charges + 
                             $this->penalty_amount + 
                             $this->other_charges;

        $this->balance_amount = $this->total_amount - $this->paid_amount;
        
        $this->updateStatus();
    }

    public function updateStatus(): void
    {
        if ($this->balance_amount <= 0) {
            $this->status = 'paid';
        } elseif ($this->paid_amount > 0) {
            $this->status = 'partial';
        } elseif ($this->due_date < now()) {
            $this->status = 'overdue';
        } else {
            $this->status = 'pending';
        }
    }

    public function addPayment(float $amount, array $paymentData = []): Payment
    {
        $payment = $this->payments()->create(array_merge([
            'society_id' => $this->society_id,
            'user_id' => $this->flat->activeResident->user_id ?? null,
            'payment_id' => Payment::generatePaymentId(),
            'amount' => $amount,
            'payment_date' => now(),
            'status' => 'success',
        ], $paymentData));

        $this->paid_amount += $amount;
        $this->save();

        return $payment;
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function isOverdue(): bool
    {
        return $this->status === 'overdue';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isPartiallyPaid(): bool
    {
        return $this->status === 'partial';
    }

    public function getMonthYearAttribute(): string
    {
        return $this->month . ' ' . $this->year;
    }

    public function getAmountAttribute()
    {
        return $this->maintenance_amount ?? $this->total_amount ?? 0;
    }

    public static function generateBillNumber(int $societyId): string
    {
        $prefix = 'MB';
        $societyCode = str_pad($societyId, 3, '0', STR_PAD_LEFT);
        $sequence = static::where('society_id', $societyId)->count() + 1;
        $sequenceCode = str_pad($sequence, 6, '0', STR_PAD_LEFT);
        
        return $prefix . $societyCode . date('Y') . $sequenceCode;
    }
}