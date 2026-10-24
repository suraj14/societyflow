<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A festival / celebration with its own voluntary-contribution fund. Never a maintenance bill, never a society expense. */
class Festival extends Model
{
    public const STATUSES = ['draft', 'published', 'closed', 'cancelled'];

    protected $fillable = [
        'society_id', 'name', 'description', 'festival_date', 'end_date', 'venue', 'expected_attendance', 'organizer_id', 'status',
        'estimated_budget', 'approved_budget', 'contribution_target', 'approval_limit', 'opening_balance', 'opening_balance_note',
        'separate_approver', 'allow_anonymous', 'arrangements', 'series_key', 'event_id', 'announced_at', 'created_by',
    ];

    protected $casts = [
        'festival_date' => 'date', 'end_date' => 'date', 'announced_at' => 'datetime', 'settled_at' => 'datetime',
        'estimated_budget' => 'decimal:2', 'approved_budget' => 'decimal:2', 'contribution_target' => 'decimal:2',
        'approval_limit' => 'decimal:2', 'opening_balance' => 'decimal:2',
        'separate_approver' => 'boolean', 'allow_anonymous' => 'boolean',
    ];

    public function society(): BelongsTo { return $this->belongsTo(Society::class); }
    public function organizer(): BelongsTo { return $this->belongsTo(User::class, 'organizer_id'); }
    public function budgetItems(): HasMany { return $this->hasMany(FestivalBudgetItem::class); }
    public function members(): HasMany { return $this->hasMany(FestivalMember::class); }
    public function updates(): HasMany { return $this->hasMany(FestivalUpdate::class)->latest(); }
    public function contributions(): HasMany { return $this->hasMany(FestivalContribution::class); }
    public function expenses(): HasMany { return $this->hasMany(FestivalExpense::class); }

    public function isSettled(): bool { return $this->settlement_status === 'settled'; }

    /** New money may still move (contributions, expenses). A settled, closed or cancelled festival is read-only. */
    public function isOpen(): bool { return !$this->isSettled() && in_array($this->status, ['draft', 'published'], true); }
}