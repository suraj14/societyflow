<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Visitor extends Model
{
    use HasFactory;

    protected $fillable = [
        'society_id',
        'flat_id',
        'host_user_id',
        'approved_by',
        'visitor_name',
        'visitor_phone',
        'visitor_id_proof',
        'visitor_id_number',
        'visitor_type',
        'purpose',
        'expected_count',
        'vehicle_number',
        'expected_entry_time',
        'expected_exit_time',
        'actual_entry_time',
        'actual_exit_time',
        'approval_status',
        'entry_status',
        'rejection_reason',
        'security_notes',
        'photo',
    ];

    protected function casts(): array
    {
        return [
            'expected_entry_time' => 'datetime',
            'expected_exit_time' => 'datetime',
            'actual_entry_time' => 'datetime',
            'actual_exit_time' => 'datetime',
        ];
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

    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_user_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(VisitorLog::class);
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('approval_status', 'pending');
    }

    public function scopeAllowed($query)
    {
        return $query->where('approval_status', 'allowed');
    }

    public function scopeDenied($query)
    {
        return $query->where('approval_status', 'denied');
    }

    public function scopeEntered($query)
    {
        return $query->where('entry_status', 'entered');
    }

    public function scopeExited($query)
    {
        return $query->where('entry_status', 'exited');
    }

    public function scopeBySociety($query, $societyId)
    {
        return $query->where('society_id', $societyId);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('visitor_type', $type);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('expected_entry_time', now()->toDateString());
    }

    // Helper methods
    public function isPending(): bool
    {
        return $this->approval_status === 'pending';
    }

    public function isAllowed(): bool
    {
        return $this->approval_status === 'allowed';
    }

    public function isDenied(): bool
    {
        return $this->approval_status === 'denied';
    }

    public function hasEntered(): bool
    {
        return $this->entry_status === 'entered';
    }

    public function hasExited(): bool
    {
        return $this->entry_status === 'exited';
    }

    public function allow(User $approver): void
    {
        $this->update([
            'approval_status' => 'allowed',
            'approved_by' => $approver->id,
        ]);

        $this->addLog($approver, 'allowed', 'Visitor allowed');
    }

    public function deny(User $denier, string $reason): void
    {
        $this->update([
            'approval_status' => 'denied',
            'rejection_reason' => $reason,
        ]);

        $this->addLog($denier, 'denied', $reason);
    }

    public function markEntry(User $security, string $notes = null): void
    {
        $this->update([
            'entry_status' => 'entered',
            'actual_entry_time' => now(),
            'security_notes' => $notes,
        ]);

        $this->addLog($security, 'entry', $notes ?? 'Visitor entered');
    }

    public function markExit(User $security, string $notes = null): void
    {
        $this->update([
            'entry_status' => 'exited',
            'actual_exit_time' => now(),
        ]);

        $this->addLog($security, 'exit', $notes ?? 'Visitor exited');
    }

    public function addLog(User $user, string $action, string $notes = null): VisitorLog
    {
        return $this->logs()->create([
            'user_id' => $user->id,
            'action' => $action,
            'notes' => $notes,
        ]);
    }

    public function getVisitDuration(): ?int
    {
        if (!$this->actual_entry_time || !$this->actual_exit_time) {
            return null;
        }

        return $this->actual_entry_time->diffInMinutes($this->actual_exit_time);
    }

    public function isOverdue(): bool
    {
        return $this->hasEntered() && 
               !$this->hasExited() && 
               $this->expected_exit_time && 
               $this->expected_exit_time < now();
    }

    public function canEnter(): bool
    {
        return $this->isAllowed() && 
               $this->entry_status === 'pending' &&
               $this->expected_entry_time <= now()->addHours(2);
    }

    public function canExit(): bool
    {
        return $this->hasEntered() && !$this->hasExited();
    }

    public function getApprovalStatusColorAttribute(): string
    {
        return match ($this->approval_status) {
            'pending' => 'yellow',
            'allowed' => 'green',
            'denied' => 'red',
            default => 'gray',
        };
    }

    public function getEntryStatusColorAttribute(): string
    {
        return match ($this->entry_status) {
            'pending' => 'yellow',
            'entered' => 'green',
            'exited' => 'blue',
            default => 'gray',
        };
    }

    public function getTypeIconAttribute(): string
    {
        return match ($this->visitor_type) {
            'guest' => 'user',
            'delivery' => 'truck',
            'cab' => 'car',
            'service' => 'wrench',
            default => 'users',
        };
    }
}