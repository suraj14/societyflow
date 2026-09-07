<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NoticeRead extends Model
{
    use HasFactory;

    protected $fillable = [
        'notice_id',
        'user_id',
        'read_at',
    ];

    // Converted from casts(): the casts() method is Laravel 11+;
    // on Laravel 10 it was never applied, so none of these casts
    // were active (dates stayed strings -> 500s in API formatters).
    protected $casts = [
            'read_at' => 'datetime',
        ];

    public $timestamps = false;

    // Relationships
    public function notice(): BelongsTo
    {
        return $this->belongsTo(Notice::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}