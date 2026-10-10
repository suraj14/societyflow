<?php

namespace App\Models;

use App\Support\SocietyClock;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
// The base Carbon\Carbon, not Illuminate\Support\Carbon - every moment
// this model compares against comes from SocietyClock, which returns the
// base class; a strict Illuminate\Support\Carbon type hint rejects it.
use Carbon\Carbon;

class Shift extends Model
{
    protected $fillable = [
        'society_id',
        'name',
        'start_time',
        'end_time',
        'grace_period_minutes',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function society(): BelongsTo
    {
        return $this->belongsTo(Society::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ShiftAssignment::class);
    }

    public function scopeBySociety($query, $societyId)
    {
        return $query->where('society_id', $societyId);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /** Crosses midnight - e.g. 22:00 start, 06:00 end. */
    public function isOvernight(): bool
    {
        return Carbon::parse($this->end_time)->lt(Carbon::parse($this->start_time));
    }

    /**
     * [start, end] as real Carbon instants for the shift that *starts* on
     * $date - an overnight shift's end lands on the next calendar day.
     */
    public function windowStartingOn(\DateTimeInterface|string $date): array
    {
        // "09:00" is 09:00 on the SOCIETY's clock. Parsed in the application
        // timezone (UTC on the live databases) it was 09:00 UTC = 14:30 IST,
        // so a guard on a 09:00-18:00 shift was "not on shift" until the
        // afternoon and the missed-shift sweep fired five and a half hours
        // early. Every moment this is compared with comes from SocietyClock.
        $day = $date instanceof \DateTimeInterface ? $date->format('Y-m-d') : Carbon::parse($date)->format('Y-m-d');
        $date = Carbon::createFromFormat('Y-m-d', $day, SocietyClock::timezoneFor($this->society_id))->startOfDay();
        $start = $date->copy()->setTimeFromTimeString($this->start_time);
        $end = $date->copy()->setTimeFromTimeString($this->end_time);

        if ($this->isOvernight()) {
            $end->addDay();
        }

        return [$start, $end];
    }

    /** Whether $moment falls inside the window for the shift starting on $date. */
    public function coversMoment(\DateTimeInterface|string $date, Carbon $moment): bool
    {
        [$start, $end] = $this->windowStartingOn($date);

        return $moment->gte($start) && $moment->lt($end);
    }

    public function graceDeadlineFor(\DateTimeInterface|string $date): Carbon
    {
        [$start] = $this->windowStartingOn($date);

        return $start->copy()->addMinutes($this->grace_period_minutes);
    }
}
