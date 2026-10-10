<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A resident's request for a society service provider (maid, cook, driver,
 * etc.) - as opposed to [ServiceProvider], which is the vendor directory
 * itself. One request moves: pending -> assigned -> in_progress ->
 * completed, or pending/assigned -> cancelled.
 */
class ServiceRequest extends Model
{
    use HasFactory;

    public const STATUSES = ['pending', 'assigned', 'in_progress', 'completed', 'cancelled'];

    /** Statuses in which the request is still open work. */
    public const OPEN_STATUSES = ['pending', 'assigned', 'in_progress'];

    protected $fillable = [
        'society_id',
        'user_id',
        'service_id',
        'description',
        'preferred_date',
        'status',
        'assigned_provider_id',
        'assigned_by',
        'assigned_at',
        'started_at',
        'completed_at',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected $casts = [
        'preferred_date' => 'date',
        'assigned_at'    => 'datetime',
        'started_at'     => 'datetime',
        'completed_at'   => 'datetime',
        'cancelled_at'   => 'datetime',
    ];

    public function society(): BelongsTo
    {
        return $this->belongsTo(Society::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function assignedProvider(): BelongsTo
    {
        return $this->belongsTo(ServiceProvider::class, 'assigned_provider_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }
}
