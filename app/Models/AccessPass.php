<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A recurring-staff or temporary-service-provider gate authorisation.
 *
 * Deliberately thin: every actual entry/exit is an ordinary `visitors` row
 * (see AccessPassService::checkIn()), so this model only answers "is this
 * pass allowed through the gate right now" - it never duplicates the
 * visitor/attendance/history machinery that already exists.
 */
class AccessPass extends Model
{
    protected $fillable = [
        'society_id',
        'flat_id',
        'person_name',
        'person_phone',
        'person_photo',
        'category',
        'pass_type',
        'access_scope',
        'token',
        'allowed_days',
        'start_time',
        'end_time',
        'valid_from',
        'valid_until',
        'blocked_dates',
        'status',
        'created_by',
        'revoked_at',
        'last_used_at',
        'currently_inside',
        'last_entry_at',
        'last_exit_at',
    ];

    protected $casts = [
        'allowed_days'      => 'array',
        'blocked_dates'     => 'array',
        'valid_from'        => 'date',
        'valid_until'       => 'date',
        'revoked_at'        => 'datetime',
        'last_used_at'      => 'datetime',
        'currently_inside'  => 'boolean',
        'last_entry_at'     => 'datetime',
        'last_exit_at'      => 'datetime',
    ];

    protected $hidden = ['token'];

    public function society(): BelongsTo
    {
        return $this->belongsTo(Society::class);
    }

    public function flat(): BelongsTo
    {
        return $this->belongsTo(Flat::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function visits(): HasMany
    {
        return $this->hasMany(Visitor::class, 'access_pass_id');
    }

    public function scopeBySociety($query, $societyId)
    {
        return $query->where('society_id', $societyId);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /** A fresh, unguessable, opaque token - never derived from person data. */
    public static function generateToken(): string
    {
        do {
            $token = Str::random(40);
        } while (self::where('token', $token)->exists());

        return $token;
    }

    public function isRevoked(): bool
    {
        return $this->status === 'revoked';
    }

    /** A society-wide employee (common-area cleaner, society electrician, ...) - no single flat to check. */
    public function isSocietyWide(): bool
    {
        return $this->access_scope === 'society';
    }

    /** Blocked for one specific date without touching the standing pass. */
    public function isBlockedOn(Carbon $date): bool
    {
        $dates = $this->blocked_dates ?? [];

        return in_array($date->toDateString(), $dates, true);
    }

    public function blockToday(): void
    {
        $today = now()->toDateString();
        $dates = $this->blocked_dates ?? [];

        if (!in_array($today, $dates, true)) {
            $dates[] = $today;
        }

        // Blocked dates are a rolling window, not permanent history - drop
        // anything before today so the column does not grow forever.
        $dates = array_values(array_filter($dates, fn ($d) => $d >= $today));

        $this->update(['blocked_dates' => $dates]);
    }

    public function revoke(): void
    {
        $this->update(['status' => 'revoked', 'revoked_at' => now()]);
    }

    /**
     * Whether $moment falls inside this pass's day/time window.
     * Temporary passes carry no allowed_days - the valid_from/valid_until
     * date range alone bounds them, and start_time/end_time (if set) still
     * apply within that single window.
     */
    public function isWithinSchedule(Carbon $moment): bool
    {
        if ($this->pass_type === 'recurring_staff' && !empty($this->allowed_days)) {
            if (!in_array((int) $moment->dayOfWeek, array_map('intval', $this->allowed_days), true)) {
                return false;
            }
        }

        if ($this->start_time && $this->end_time) {
            $time = $moment->format('H:i:s');
            $start = Carbon::parse($this->start_time)->format('H:i:s');
            $end = Carbon::parse($this->end_time)->format('H:i:s');

            if ($time < $start || $time > $end) {
                return false;
            }
        }

        return true;
    }

    public function isWithinValidity(Carbon $moment): bool
    {
        if ($moment->toDateString() < $this->valid_from->toDateString()) {
            return false;
        }

        if ($this->valid_until && $moment->toDateString() > $this->valid_until->toDateString()) {
            return false;
        }

        return true;
    }
}
