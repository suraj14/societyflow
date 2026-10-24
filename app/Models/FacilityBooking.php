<?php

namespace App\Models;

use App\Models\Concerns\GeneratesDocumentNumber;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacilityBooking extends Model
{
    use HasFactory, GeneratesDocumentNumber;

    protected $fillable = [
        'society_id',
        'facility_id',
        'user_id',
        'flat_id',
        'booking_number',
        'booking_date',
        'start_time',
        'end_time',
        'purpose',
        'expected_guests',
        'booking_amount',
        'security_deposit',
        'payment_status',
        'status',
        'admin_notes',
        'cancellation_reason',
        'approved_at',
        'cancelled_at',
    ];

    protected $casts = [
        'booking_date' => 'date',
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
        'booking_amount' => 'decimal:2',
        'security_deposit' => 'decimal:2',
        'approved_at' => 'datetime',
        'cancelled_at' => 'datetime',
        // money actually received / paid back - written only by recordPayment() / recordRefund(), never mass-assigned
        'paid_amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'refunded_amount' => 'decimal:2',
        'refunded_at' => 'datetime',
    ];

    /** Methods a facility payment can be recorded with (the same set as payments.payment_method, without unrecorded: a payment with no method is not evidence). */
    public const PAYMENT_METHODS = ['cash', 'cheque', 'online', 'upi', 'card', 'bank_transfer'];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($booking) {
            if (empty($booking->booking_number)) {
                $booking->booking_number = static::generateBookingNumber($booking->society_id);
            }
        });
    }

    // Relationships
    public function society(): BelongsTo
    {
        return $this->belongsTo(Society::class);
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function flat(): BelongsTo
    {
        return $this->belongsTo(Flat::class);
    }

    // Scopes
    /**
     * THE definition of "owed to the society": a booking that will happen or has happened (approved / completed), that has a charge,
     * and whose payment has not been recorded. Pending, rejected and cancelled bookings are never receivables. The Facility Bookings
     * summary and the Financial Report both read this one scope, so they cannot disagree.
     */
    public function scopeAwaitingPayment($query)
    {
        return $query->whereIn('status', ['approved', 'completed'])->where('payment_status', 'pending')->where('booking_amount', '>', 0);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeBySociety($query, $societyId)
    {
        return $query->where('society_id', $societyId);
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForDate($query, $date)
    {
        return $query->where('booking_date', $date);
    }

    public function scopeUpcoming($query)
    {
        return $query->where('booking_date', '>=', now()->toDateString());
    }

    // Helper methods
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function approve(User $approver, string $notes = null): void
    {
        $this->update([
            'status' => 'approved',
            'approved_at' => now(),
            'admin_notes' => $notes,
        ]);
    }

    public function reject(User $rejector, string $reason): void
    {
        $this->update([
            'status' => 'rejected',
            'admin_notes' => $reason,
        ]);
    }

    public function cancel(User $canceller, string $reason): void
    {
        $this->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
        ]);
    }

    public function complete(): void
    {
        $this->update(['status' => 'completed']);
    }

    /**
     * Record that the booking's charge was actually received. A booking that is merely approved has NOT paid, and counts as
     * income nowhere until this runs.
     *
     * Safe against every race, on every engine: the row is read under a lock (`FOR UPDATE` on MySQL / MariaDB; SQLite
     * serialises writers), AND the UPDATE itself repeats every condition - the payment still pending, the booking still
     * approved or completed, a charge to pay. So a cancellation that lands between any earlier check and this write wins:
     * the UPDATE matches no row, nothing is recorded, and the caller gets `false`. The same compare-and-swap makes a double
     * submit, a retried request or a repeated gateway callback record the money once. The unique (society_id,
     * payment_reference) index stops one transaction reference being claimed by two bookings.
     *
     * @throws \Illuminate\Database\QueryException when the reference was already recorded for another booking
     */
    public function recordPayment(User $by, string $method, ?string $reference, ?\Carbon\Carbon $when = null): bool
    {
        $recorded = \Illuminate\Support\Facades\DB::transaction(function () use ($by, $method, $reference, $when) {
            $row = static::query()->whereKey($this->id)->lockForUpdate()->first();
            if (!$row || $row->payment_status !== 'pending' || !in_array($row->status, ['approved', 'completed'], true) || (float) $row->booking_amount <= 0) {
                return 0;
            }

            return static::query()->whereKey($this->id)
                ->where('payment_status', 'pending')
                ->whereIn('status', ['approved', 'completed'])
                ->where('booking_amount', '>', 0)
                ->update([
                    'payment_status'      => 'paid',
                    'paid_amount'         => $row->booking_amount,
                    // the society's own calendar (naive local time), like payments.payment_date: a payment at 00:30 IST belongs to
                    // that day's report, not to the previous UTC day's
                    'paid_at'             => $when ?? \App\Support\SocietyClock::now($this->society_id),
                    'payment_method'      => $method,
                    'payment_reference'   => $reference !== null && trim($reference) !== '' ? trim($reference) : null,
                    'payment_recorded_by' => $by->id,
                ]);
        });
        $this->refresh();

        return $recorded === 1;
    }

    /** Why a payment cannot be recorded for this booking right now, in words (null when it can). Reads the stored row. */
    public function paymentBlockReason(): ?string
    {
        $row = static::query()->find($this->id);
        if (!$row) {
            return 'This booking no longer exists.';
        }
        if ($row->payment_status !== 'pending') {
            return 'A payment is already recorded for this booking (' . $row->payment_status . '). Nothing was changed.';
        }
        if (!in_array($row->status, ['approved', 'completed'], true)) {
            return 'This booking is ' . $row->status . ', so no payment was recorded. Only an approved or completed booking can take a payment.';
        }
        if ((float) $row->booking_amount <= 0) {
            return 'This booking has no charge to pay.';
        }

        return null;
    }

    /** Record money paid back for a booking that had been paid. Only once (paid to refunded), never more than was received. */
    public function recordRefund(User $by, float $amount, string $reason): bool
    {
        $refunded = \Illuminate\Support\Facades\DB::transaction(function () use ($amount, $reason) {
            $row = static::query()->whereKey($this->id)->lockForUpdate()->first();
            if (!$row || $row->payment_status !== 'paid') {
                return 0;
            }

            return static::query()->whereKey($this->id)->where('payment_status', 'paid')
                // compared in whole paise as integers: a bound float against a decimal column is compared as text on SQLite
                ->whereRaw('CAST(ROUND(COALESCE(paid_amount, booking_amount) * 100) AS INTEGER) >= ?', [\App\Support\Money::paise($amount)])
                ->update(['payment_status' => 'refunded', 'refunded_amount' => round($amount, 2), 'refunded_at' => \App\Support\SocietyClock::now($this->society_id), 'refund_reason' => mb_substr(trim($reason), 0, 255)]);
        });
        $this->refresh();

        return $refunded === 1;
    }
    public function getDurationInHours(): float
    {
        $start = \Carbon\Carbon::createFromFormat('H:i', $this->start_time);
        $end = \Carbon\Carbon::createFromFormat('H:i', $this->end_time);
        
        return $end->diffInHours($start);
    }

    public function getTotalAmount(): float
    {
        return $this->booking_amount + $this->security_deposit;
    }

    public function canBeCancelled(): bool
    {
        return $this->isPending() || $this->isApproved();
    }

    public function canBeModified(): bool
    {
        return $this->isPending() && $this->booking_date > now()->addDay();
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'yellow',
            'approved' => 'green',
            'rejected' => 'red',
            'cancelled' => 'gray',
            'completed' => 'blue',
            default => 'gray',
        };
    }

    public static function generateBookingNumber(int $societyId): string
    {
        return static::nextDocumentNumber($societyId, 'BKG', 'booking_number');
    }
}