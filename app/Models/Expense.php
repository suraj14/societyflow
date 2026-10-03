<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One expense paid out, either by the society as a whole or on behalf of
 * one property.
 *
 * `scope` is the single most important column: only `society` rows are
 * society expenses and only they feed the financial report's expense totals
 * and category breakdown. `property` rows are shown separately and never
 * added to that total (see FinancialReportService).
 */
class Expense extends Model
{
    use HasFactory;

    public const SCOPE_SOCIETY  = 'society';
    public const SCOPE_PROPERTY = 'property';

    public const SCOPES = [self::SCOPE_SOCIETY, self::SCOPE_PROPERTY];

    /** Who bears the cost. Not derived from scope - recorded per row. */
    public const PAID_BY = ['society', 'owner', 'tenant', 'property'];

    public const PAYMENT_METHODS = ['cash', 'cheque', 'online', 'upi', 'card'];

    protected $fillable = [
        'society_id',
        'scope',
        'flat_id',
        'paid_by',
        'expense_category_id',
        'created_by',
        'title',
        'description',
        'amount',
        'expense_date',
        'vendor_name',
        'bill_number',
        'receipt',
        'payment_method',
        'status',
        'remarks',
    ];

    // Converted from casts(): the casts() method is Laravel 11+;
    // on Laravel 10 it was never applied, so none of these casts
    // were active (dates stayed strings -> 500s in API formatters).
    protected $casts = [
        'amount' => 'decimal:2',
        'expense_date' => 'date',
    ];

    // Relationships
    public function society(): BelongsTo
    {
        return $this->belongsTo(Society::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function flat(): BelongsTo
    {
        return $this->belongsTo(Flat::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Scopes
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

    public function scopeBySociety($query, $societyId)
    {
        return $query->where('society_id', $societyId);
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('expense_date', [$startDate, $endDate]);
    }

    public function scopeByCategory($query, $categoryId)
    {
        return $query->where('expense_category_id', $categoryId);
    }

    /** Society/common expenses only - the ones that count as society spend. */
    public function scopeSocietyScope($query)
    {
        return $query->where('scope', self::SCOPE_SOCIETY);
    }

    /** Expenses belonging to one property. */
    public function scopePropertyScope($query)
    {
        return $query->where('scope', self::SCOPE_PROPERTY);
    }

    // Helper methods
    public function isSocietyExpense(): bool
    {
        return $this->scope === self::SCOPE_SOCIETY;
    }

    public function isPropertyExpense(): bool
    {
        return $this->scope === self::SCOPE_PROPERTY;
    }

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

    public function approve(string $remarks = null): void
    {
        $this->update([
            'status' => 'approved',
            'remarks' => $remarks,
        ]);
    }

    public function reject(string $remarks): void
    {
        $this->update([
            'status' => 'rejected',
            'remarks' => $remarks,
        ]);
    }

    public function getReceiptUrlAttribute(): ?string
    {
        if ($this->receipt) {
            return asset('storage/' . $this->receipt);
        }

        return null;
    }

    public function getScopeLabelAttribute(): string
    {
        return $this->isPropertyExpense() ? 'Property' : 'Society / Common';
    }

    public function getPaidByLabelAttribute(): string
    {
        return ucfirst($this->paid_by ?? 'society');
    }

    /** "Villa 160" / "Tower A - 101", or "Entire Society" for a common expense. */
    public function getPropertyLabelAttribute(): string
    {
        if (!$this->isPropertyExpense() || !$this->flat) {
            return 'Entire Society';
        }

        $flat = $this->flat;

        if ($flat->property_type === 'villa') {
            return 'Villa ' . $flat->flat_number;
        }

        return ($flat->building->name ?? 'Building') . ' - ' . $flat->flat_number;
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'yellow',
            'approved' => 'green',
            'rejected' => 'red',
            default => 'gray',
        };
    }
}
