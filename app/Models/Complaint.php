<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Complaint extends Model
{
    use HasFactory;

    protected $fillable = [
        'society_id',
        'complaint_category_id',
        'flat_id',
        'created_by',
        'assigned_to',
        'complaint_number',
        'title',
        'description',
        'priority',
        'status',
        'attachments',
        'resolution_notes',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'attachments' => 'array',
            'resolved_at' => 'datetime',
        ];
    }

    // Mutators
    public function setAttachmentsAttribute($value)
    {
        if (is_array($value)) {
            $this->attributes['attachments'] = json_encode($value);
        } else {
            $this->attributes['attachments'] = $value;
        }
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($complaint) {
            if (empty($complaint->complaint_number)) {
                $complaint->complaint_number = static::generateComplaintNumber($complaint->society_id);
            }
        });
    }

    // Relationships
    public function society(): BelongsTo
    {
        return $this->belongsTo(Society::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ComplaintCategory::class, 'complaint_category_id');
    }

    public function flat(): BelongsTo
    {
        return $this->belongsTo(Flat::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function resident()
    {
        return $this->hasOneThrough(
            Resident::class,
            User::class,
            'id', // Foreign key on users table
            'user_id', // Foreign key on residents table
            'created_by', // Local key on complaints table
            'id' // Local key on users table
        )->where('residents.status', 'active');
    }

    public function updates(): HasMany
    {
        return $this->hasMany(ComplaintUpdate::class);
    }

    // Scopes
    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    public function scopeResolved($query)
    {
        return $query->where('status', 'resolved');
    }

    public function scopeClosed($query)
    {
        return $query->where('status', 'closed');
    }

    public function scopeByPriority($query, $priority)
    {
        return $query->where('priority', $priority);
    }

    public function scopeBySociety($query, $societyId)
    {
        return $query->where('society_id', $societyId);
    }

    public function scopeAssignedTo($query, $userId)
    {
        return $query->where('assigned_to', $userId);
    }

    public function scopeCreatedBy($query, $userId)
    {
        return $query->where('created_by', $userId);
    }

    // Helper methods
    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function isInProgress(): bool
    {
        return $this->status === 'in_progress';
    }

    public function isResolved(): bool
    {
        return $this->status === 'resolved';
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    public function assignTo(User $user, string $message = null): void
    {
        $this->update([
            'assigned_to' => $user->id,
            'status' => 'in_progress',
        ]);

        $this->addUpdate($user, $message ?? 'Complaint assigned', 'assignment');
    }

    public function resolve(User $user, string $resolutionNotes): void
    {
        $this->update([
            'status' => 'resolved',
            'resolution_notes' => $resolutionNotes,
            'resolved_at' => now(),
        ]);

        $this->addUpdate($user, $resolutionNotes, 'status_change');
    }

    public function close(User $user, string $message = null): void
    {
        $this->update(['status' => 'closed']);
        
        $this->addUpdate($user, $message ?? 'Complaint closed', 'status_change');
    }

    public function reopen(User $user, string $message = null): void
    {
        $this->update([
            'status' => 'open',
            'resolved_at' => null,
        ]);

        $this->addUpdate($user, $message ?? 'Complaint reopened', 'status_change');
    }

    public function addUpdate(User $user, string $message, string $type = 'comment', array $attachments = []): ComplaintUpdate
    {
        return $this->updates()->create([
            'user_id' => $user->id,
            'message' => $message,
            'type' => $type,
            'attachments' => $attachments,
        ]);
    }

    public function getPriorityColorAttribute(): string
    {
        return match ($this->priority) {
            'low' => 'green',
            'medium' => 'yellow',
            'high' => 'orange',
            'urgent' => 'red',
            default => 'gray',
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'open' => 'red',
            'in_progress' => 'yellow',
            'resolved' => 'green',
            'closed' => 'gray',
            'cancelled' => 'gray',
            default => 'gray',
        };
    }

    public function getResolutionTime(): ?int
    {
        if (!$this->resolved_at) {
            return null;
        }

        return $this->created_at->diffInHours($this->resolved_at);
    }

    public static function generateComplaintNumber(int $societyId): string
    {
        $prefix = 'CMP';
        $societyCode = str_pad($societyId, 3, '0', STR_PAD_LEFT);
        $sequence = static::where('society_id', $societyId)->count() + 1;
        $sequenceCode = str_pad($sequence, 6, '0', STR_PAD_LEFT);
        
        return $prefix . $societyCode . date('Y') . $sequenceCode;
    }
}