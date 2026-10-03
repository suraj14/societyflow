<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Per-society expense categories (the table is unique on society_id+name).
 *
 * A controlled default set is created the first time a society records an
 * expense (ensureDefaults) so every society's report groups spend the same
 * way; Admin can add to or deactivate that list from the Expenses screen.
 */
class ExpenseCategory extends Model
{
    use HasFactory;

    /**
     * The default category list. Deliberately short - enough for a society
     * admin to answer "where did the money go?", not an accounting chart.
     */
    public const DEFAULTS = [
        'Security',
        'Housekeeping',
        'Common Electricity',
        'Common Water',
        'Gardening',
        'Lift',
        'Generator',
        'Diesel / Fuel',
        'Pump',
        'Repairs',
        'Plumbing',
        'Electrical',
        'Cleaning',
        'Waste Management',
        'Pest Control',
        'CCTV / Security Systems',
        'Staff Salary',
        'Administration',
        'Insurance',
        'Common Facilities',
        'Professional Services',
        'Legal',
        'Events',
        'Other',
    ];

    protected $fillable = [
        'society_id',
        'name',
        'description',
        'status',
    ];

    /**
     * Make sure $societyId has the default categories. Idempotent and cheap
     * (one count query when nothing is missing), so it is safe to call from
     * the create form.
     */
    public static function ensureDefaults(int $societyId): void
    {
        $existing = static::where('society_id', $societyId)->pluck('name')->all();
        $missing  = array_diff(self::DEFAULTS, $existing);

        foreach ($missing as $name) {
            static::firstOrCreate(
                ['society_id' => $societyId, 'name' => $name],
                ['status' => 'active']
            );
        }
    }

    // Relationships
    public function society(): BelongsTo
    {
        return $this->belongsTo(Society::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeBySociety($query, $societyId)
    {
        return $query->where('society_id', $societyId);
    }

    // Helper methods
    public function getTotalExpenses(): float
    {
        return (float) $this->expenses()->where('status', 'approved')->sum('amount');
    }

    public function getExpensesCount(): int
    {
        return $this->expenses()->count();
    }

    public function getMonthlyExpenses(int $month = null, int $year = null): float
    {
        $month = $month ?? now()->month;
        $year = $year ?? now()->year;

        return (float) $this->expenses()
            ->where('status', 'approved')
            ->whereMonth('expense_date', $month)
            ->whereYear('expense_date', $year)
            ->sum('amount');
    }
}
