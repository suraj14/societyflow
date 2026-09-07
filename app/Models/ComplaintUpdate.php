<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComplaintUpdate extends Model
{
    use HasFactory;

    protected $fillable = [
        'complaint_id',
        'user_id',
        'message',
        'attachments',
        'type',
    ];

    protected function casts(): array
    {
        return [
            'attachments' => 'array',
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

    // Relationships
    public function complaint(): BelongsTo
    {
        return $this->belongsTo(Complaint::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeComments($query)
    {
        return $query->where('type', 'comment');
    }

    public function scopeStatusChanges($query)
    {
        return $query->where('type', 'status_change');
    }

    public function scopeAssignments($query)
    {
        return $query->where('type', 'assignment');
    }

    // Helper methods
    public function isComment(): bool
    {
        return $this->type === 'comment';
    }

    public function isStatusChange(): bool
    {
        return $this->type === 'status_change';
    }

    public function isAssignment(): bool
    {
        return $this->type === 'assignment';
    }

    public function getTypeColorAttribute(): string
    {
        return match ($this->type) {
            'comment' => 'blue',
            'status_change' => 'green',
            'assignment' => 'purple',
            default => 'gray',
        };
    }
}