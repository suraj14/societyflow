<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VisitorLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'visitor_id',
        'user_id',
        'action',
        'notes',
    ];

    // Relationships
    public function visitor(): BelongsTo
    {
        return $this->belongsTo(Visitor::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Helper methods
    public function getActionColorAttribute(): string
    {
        return match ($this->action) {
            'created' => 'blue',
            'approved' => 'green',
            'rejected' => 'red',
            'entry' => 'purple',
            'exit' => 'gray',
            'updated' => 'yellow',
            default => 'gray',
        };
    }
}