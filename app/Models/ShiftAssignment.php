<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * One guard, one shift, one gate, one date - attendance and status live
 * directly on this row rather than a separate attendance table, since
 * there is exactly one attendance record per assignment.
 */
class ShiftAssignment extends Model
{
    public const STATUS_SCHEDULED  = 'scheduled';
    public const STATUS_PRESENT    = 'present';
    public const STATUS_LATE       = 'late';
    public const STATUS_CHECKED_OUT = 'checked_out';
    public const STATUS_ABSENT     = 'absent';
    public const STATUS_MISSED     = 'missed';
    public const STATUS_REPLACED   = 'replaced';

    protected $fillable = [
        'society_id',
        'shift_id',
        'gate_id',
        'guard_user_id',
        'date',
        'status',
        'checked_in_at',
        'checked_out_at',
        'replaced_by_user_id',
        'replacement_reason',
        'replaced_by_admin_id',
        'replaced_at',
        'created_by',
    ];

    protected $casts = [
        'date'           => 'date',
        'checked_in_at'  => 'datetime',
        'checked_out_at' => 'datetime',
        'replaced_at'    => 'datetime',
    ];

    public function society(): BelongsTo
    {
        return $this->belongsTo(Society::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function gate(): BelongsTo
    {
        return $this->belongsTo(Gate::class);
    }

    /**
     * Named guardUser(), not guard() - Eloquent's base Model class already
     * declares a guard() method (mass-assignment guarding, unrelated),
     * and overriding it with an incompatible signature is a fatal error,
     * not a silent override.
     */
    public function guardUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guard_user_id');
    }

    public function replacedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'replaced_by_user_id');
    }

    public function handover(): HasOne
    {
        return $this->hasOne(ShiftHandover::class);
    }

    public function scopeBySociety($query, $societyId)
    {
        return $query->where('society_id', $societyId);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [self::STATUS_SCHEDULED, self::STATUS_PRESENT, self::STATUS_LATE], true);
    }

    public function hasStarted(): bool
    {
        return $this->checked_in_at !== null;
    }

    public function hasEnded(): bool
    {
        return $this->checked_out_at !== null;
    }
}
