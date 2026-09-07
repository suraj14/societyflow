<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Event extends Model
{
    use HasFactory;

    protected $fillable = [
        'society_id',
        'created_by',
        'event_name',
        'location',
        'description',
        'start_date',
        'end_date',
        'status',
        'is_role_based',
        'visible_roles',
        'visible_users',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'visible_roles' => 'array',
        'visible_users' => 'array',
        'is_role_based' => 'boolean',
    ];

    // Relationships
    public function society(): BelongsTo
    {
        return $this->belongsTo(Society::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Scopes
    public function scopeBySociety($query, $societyId)
    {
        return $query->where('society_id', $societyId);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    public function scopeUpcoming($query)
    {
        return $query->where('start_date', '>=', now());
    }

    public function scopeVisibleToUser($query, $user)
    {
        return $query->where(function ($q) use ($user) {
            // Show all events if user is admin
            if ($user->hasRole(['Super Admin', 'Admin'])) {
                return;
            }

            // Filter by role-based visibility
            $q->where(function ($subQ) use ($user) {
                $subQ->where('is_role_based', true)
                    ->where(function ($roleQ) use ($user) {
                        foreach ($user->getRoleNames() as $role) {
                            $roleQ->orWhereJsonContains('visible_roles', $role);
                        }
                    });
            })
            // Or filter by user-based visibility
            ->orWhere(function ($subQ) use ($user) {
                $subQ->where('is_role_based', false)
                    ->whereJsonContains('visible_users', $user->id);
            });
        });
    }

    // Helper methods
    public function isApproved(): bool
    {
        return $this->status === 'completed';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isUpcoming(): bool
    {
        return $this->start_date >= now();
    }

    public function hasEnded(): bool
    {
        return $this->end_date < now();
    }

    public function shouldBeCompleted(): bool
    {
        return $this->hasEnded() && $this->status === 'pending';
    }

    public function autoUpdateStatus(): bool
    {
        if ($this->shouldBeCompleted()) {
            $this->update(['status' => 'completed']);
            return true;
        }
        return false;
    }

    public static function updateExpiredEvents(): int
    {
        $expiredEvents = self::where('status', 'pending')
            ->where('end_date', '<', now())
            ->get();

        $updatedCount = 0;
        foreach ($expiredEvents as $event) {
            $event->update(['status' => 'completed']);
            $updatedCount++;
        }

        return $updatedCount;
    }

    public function isVisibleToUser($user): bool
    {
        if ($user->hasRole(['Super Admin', 'Admin'])) {
            return true;
        }

        if ($this->is_role_based) {
            $userRoles = $user->getRoleNames()->toArray();
            $visibleRoles = $this->visible_roles ?? [];
            return count(array_intersect($userRoles, $visibleRoles)) > 0;
        }

        $visibleUsers = $this->visible_users ?? [];
        return in_array($user->id, $visibleUsers);
    }
}
