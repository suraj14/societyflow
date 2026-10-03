<?php

namespace App\Support;

use App\Models\Society;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * "Now", "today" and calendar boundaries as a society experiences them.
 *
 * Storage stays in the application timezone (config app.timezone - UTC on
 * the existing databases, Asia/Kolkata for a fresh Indian deployment).
 * Each society carries its own timezone (societies.timezone, default
 * Asia/Kolkata), and every rule that depends on a calendar date - today's
 * visitors, this month's collections, whether a bill is overdue, whether an
 * access pass is inside its allowed hours - asks this class rather than
 * now()/today(), which answer in the storage timezone and are 5½ hours off
 * for an Indian society around midnight and at every month/year boundary.
 *
 * Instants (now()) are the same everywhere; only their wall-clock reading
 * differs. dayBounds()/monthBounds() therefore return the society's local
 * day/month expressed in the storage timezone, which is what a query on a
 * DATETIME column must compare against.
 */
class SocietyClock
{
    public const DEFAULT_TIMEZONE = 'Asia/Kolkata';

    /** Resolve the timezone for a society (model or id); null = the signed-in user's society. */
    public static function timezoneFor(Society|int|null $society = null): string
    {
        if ($society === null) {
            $society = auth()->user()?->society;
        } elseif (is_int($society)) {
            $society = Society::query()->select(['id', 'timezone'])->find($society);
        }

        $tz = $society?->timezone;

        return $tz && in_array($tz, timezone_identifiers_list(), true)
            ? $tz
            : (string) config('app.society_timezone', self::DEFAULT_TIMEZONE);
    }

    /** The storage timezone every DATETIME column is written in. */
    public static function storageTimezone(): string
    {
        return (string) config('app.timezone', 'UTC');
    }

    /** The current instant, read on the society's clock. */
    public static function now(Society|int|null $society = null): Carbon
    {
        return Carbon::now(self::timezoneFor($society));
    }

    /** Midnight at the start of the society's current day. */
    public static function today(Society|int|null $society = null): Carbon
    {
        return self::now($society)->startOfDay();
    }

    /** Any instant re-read on the society's clock (same moment, local wall time). */
    public static function local(CarbonInterface $at, Society|int|null $society = null): Carbon
    {
        return Carbon::instance($at)->setTimezone(self::timezoneFor($society));
    }

    /** A society-local instant expressed in the storage timezone, for DB comparison. */
    public static function toStorage(CarbonInterface $at): Carbon
    {
        return Carbon::instance($at)->setTimezone(self::storageTimezone());
    }

    /**
     * [start, end] of one society-local day as storage-timezone datetime
     * strings - the bounds a whereBetween() on a DATETIME column needs.
     *
     * @return array{0:string,1:string}
     */
    public static function dayBounds(Society|int|null $society = null, ?CarbonInterface $day = null): array
    {
        $day = $day ? self::local($day, $society) : self::now($society);

        return [
            self::toStorage($day->copy()->startOfDay())->toDateTimeString(),
            self::toStorage($day->copy()->endOfDay())->toDateTimeString(),
        ];
    }

    /**
     * [start, end] of a society-local calendar month in the storage timezone.
     *
     * @return array{0:string,1:string}
     */
    public static function monthBounds(Society|int|null $society = null, ?int $year = null, ?int $month = null): array
    {
        $now = self::now($society);
        $first = Carbon::create($year ?? $now->year, $month ?? $now->month, 1, 0, 0, 0, self::timezoneFor($society));

        return [
            self::toStorage($first->copy()->startOfMonth())->toDateTimeString(),
            self::toStorage($first->copy()->endOfMonth())->toDateTimeString(),
        ];
    }

    /** True when a DATE-only due date has passed on the society's calendar. */
    public static function dateHasPassed(CarbonInterface|string|null $date, Society|int|null $society = null): bool
    {
        if (!$date) {
            return false;
        }

        $date = $date instanceof CarbonInterface ? $date->toDateString() : Carbon::parse($date)->toDateString();

        return $date < self::today($society)->toDateString();
    }
}
