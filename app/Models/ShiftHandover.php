<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftHandover extends Model
{
    public const STATUS_PENDING  = 'pending';
    public const STATUS_ACCEPTED = 'accepted';

    protected $fillable = [
        'shift_assignment_id',
        'outgoing_guard_id',
        'incoming_guard_id',
        'notes',
        'pending_visitors_note',
        'active_passes_note',
        'status',
        'accepted_at',
    ];

    protected $casts = [
        'accepted_at' => 'datetime',
    ];

    public function shiftAssignment(): BelongsTo
    {
        return $this->belongsTo(ShiftAssignment::class);
    }

    public function outgoingGuard(): BelongsTo
    {
        return $this->belongsTo(User::class, 'outgoing_guard_id');
    }

    public function incomingGuard(): BelongsTo
    {
        return $this->belongsTo(User::class, 'incoming_guard_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
