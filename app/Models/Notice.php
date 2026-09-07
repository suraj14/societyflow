<?php

namespace App\Models;

use App\Events\NoticePublished;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Notice extends Model
{
    use HasFactory;

    protected $fillable = [
        'society_id',
        'created_by',
        'approved_by',
        'title',
        'content',
        'type',
        'priority',
        'publish_date',
        'expiry_date',
        'target_audience',
        'attachments',
        'image',
        'send_email',
        'send_sms',
        'status',
        'approved_at',
        'rejection_reason',
    ];

    protected $casts = [
        'publish_date' => 'date',
        'expiry_date' => 'date',
        'approved_at' => 'datetime',
        'target_audience' => 'array',
        'attachments' => 'array',
        'send_email' => 'boolean',
        'send_sms' => 'boolean',
    ];

    // Relationships
    public function society(): BelongsTo
    {
        return $this->belongsTo(Society::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function reads(): HasMany
    {
        return $this->hasMany(NoticeRead::class);
    }

    // Scopes
    public function scopePublished($query)
    {
        return $query->where('status', 'published')
            ->where('publish_date', '<=', now())
            ->where(function ($q) {
                $q->whereNull('expiry_date')
                  ->orWhere('expiry_date', '>=', now());
            });
    }

    public function scopePendingApproval($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeExpired($query)
    {
        return $query->where('expiry_date', '<', now());
    }

    public function scopeBySociety($query, $societyId)
    {
        return $query->where('society_id', $societyId);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    // Helper methods
    public function isPublished(): bool
    {
        return $this->status === 'published' && 
               $this->publish_date <= now() && 
               ($this->expiry_date === null || $this->expiry_date >= now());
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isExpired(): bool
    {
        return $this->expiry_date && $this->expiry_date < now();
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function approve(User $approver): void
    {
        $this->update([
            'status' => 'published',
            'approved_by' => $approver->id,
            'approved_at' => now(),
            'publish_date' => $this->publish_date ?? now(),
        ]);

        // Dispatch event to trigger email notifications
        NoticePublished::dispatch($this);
    }

    public function reject(User $rejector, string $reason): void
    {
        $this->update([
            'status' => 'rejected',
            'approved_by' => $rejector->id,
            'rejection_reason' => $reason,
        ]);
    }

    public function publish(): void
    {
        $this->update([
            'status' => 'published',
            'publish_date' => $this->publish_date ?? now(),
        ]);
    }

    public function markAsRead(User $user): void
    {
        $this->reads()->firstOrCreate([
            'user_id' => $user->id,
        ], [
            'read_at' => now(),
        ]);
    }

    public function isReadBy(User $user): bool
    {
        return $this->reads()->where('user_id', $user->id)->exists();
    }

    public function getReadCount(): int
    {
        return $this->reads()->count();
    }

    public function getPriorityColorAttribute(): string
    {
        return match ($this->priority) {
            'low' => 'green',
            'medium' => 'yellow',
            'high' => 'red',
            default => 'gray',
        };
    }

    public function getTypeColorAttribute(): string
    {
        return match ($this->type) {
            'general' => 'blue',
            'urgent' => 'red',
            'maintenance' => 'orange',
            'event' => 'purple',
            'meeting' => 'green',
            default => 'gray',
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'draft' => 'gray',
            'pending' => 'yellow',
            'published' => 'green',
            'rejected' => 'red',
            'expired' => 'gray',
            default => 'gray',
        };
    }
}
