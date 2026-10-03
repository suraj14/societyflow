<?php

namespace App\Models;

use App\Models\Concerns\GeneratesDocumentNumber;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaintenanceBill extends Model
{
    use HasFactory, GeneratesDocumentNumber;

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
        // Both added by 2026_02_02_120000_add_bill_type_and_paid_date_to_
        // maintenance_bills, so they are unconditionally present.
        //
        // These used to be appended by an overridden getFillable() that ran
        // Schema::hasColumn() at runtime. Laravel calls getFillable() very
        // frequently, and each call issued two PRAGMA table_info queries: a
        // 12-bill API response executed 90 introspection queries out of 94
        // total, and the count grew linearly with the rows returned. Listing
        // them statically removes the introspection entirely.
        'bill_type',
        'paid_date',
    ];

    // Converted from casts(): the casts() method is Laravel 11+;
    // on Laravel 10 it was never applied. Casting a column that doesn't
    // exist on a row is inert, so paid_date is listed unconditionally.
    protected $casts = [
        'bill_date' => 'date',
        'due_date' => 'date',
        'paid_date' => 'date',
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

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($bill) {
            if (empty($bill->bill_number)) {
                $bill->bill_number = static::generateBillNumber($bill->society_id);
            }
            
            $bill->calculateTotalAmount();
        });

        // Status is always a function of the money: it used to honour a
        // manually submitted 'paid' by silently setting paid_amount to the
        // total - which is how 20 bills in the dev database came to say
        // "paid" with no payment behind them. A bill is settled by recording
        // a Payment (which syncFromLedger() picks up), never by editing its
        // status.
        static::updating(function ($bill) {
            $bill->calculateTotalAmount();
        });
    }

    /**
     * Recompute paid / balance / status / paid_date from the payments
     * ledger. Only `success` payments count: a tenant's submitted-but-
     * unverified payment is pending and must not reduce the balance until an
     * admin approves it. Called from the Payment model whenever a linked
     * payment is created, changed or deleted, so the bill can never say
     * something its ledger does not.
     */
    public function syncFromLedger(): void
    {
        $success = $this->payments()->where('status', 'success');

        $paid = (float) $success->sum('amount');
        $latest = (clone $success)->max('payment_date');

        $this->paid_amount = $paid;
        $this->calculateTotalAmount(); // balance + status
        $this->paid_date = $this->balance_amount <= 0 && $latest ? $latest : null;

        $this->save();
    }

    /** True when paid_amount matches the success payments behind it. */
    public function ledgerAgrees(): bool
    {
        return abs((float) $this->paid_amount - (float) $this->payments()->where('status', 'success')->sum('amount')) < 0.005;
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
        } elseif (\App\Support\SocietyClock::dateHasPassed($this->due_date, $this->society_id)) {
            $this->status = 'overdue';
        } else {
            $this->status = 'pending';
        }
    }

    public function addPayment(float $amount, array $paymentData = []): Payment
    {
        $payment = $this->payments()->create(array_merge([
            'society_id' => $this->society_id,
            'flat_id' => $this->flat_id,
            'user_id' => $this->flat->activeResident->user_id ?? null,
            'payment_id' => Payment::generatePaymentId(),
            'amount' => $amount,
            'payment_date' => now(),
            'bill_type' => $this->bill_type ?: 'maintenance',
            'due_date' => $this->due_date,
            'status' => 'success',
        ], $paymentData));

        // The Payment model's saved hook already re-derived this bill's
        // paid/balance/status from the ledger; pick that up.
        $this->refresh();

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
        return static::nextDocumentNumber($societyId, 'MB', 'bill_number');
    }
}