<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A generic audit trail row.
 *
 * The `activity_logs` table has existed since the original schema
 * (2024_01_01_000016_create_settings_table) but had no model and nothing
 * wrote to it. Impersonation is the first writer; kept deliberately thin so
 * other actions can log through it later without a new table.
 */
class ActivityLog extends Model
{
    protected $fillable = [
        'society_id',
        'user_id',
        'action',
        'model_type',
        'model_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function society(): BelongsTo
    {
        return $this->belongsTo(Society::class);
    }

    /**
     * Record one event. `new_values` is the payload - never put a password,
     * token or credential in it, since these rows are never redacted for
     * display.
     */
    public static function record(string $action, array $newValues = [], ?int $societyId = null, ?int $userId = null): self
    {
        return static::create([
            'society_id' => $societyId ?? auth()->user()?->society_id,
            'user_id'    => $userId ?? auth()->id(),
            'action'     => $action,
            'new_values' => $newValues,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);
    }
}
