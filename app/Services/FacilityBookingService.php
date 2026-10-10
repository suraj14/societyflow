<?php

namespace App\Services;

use App\Exceptions\FacilityBookingException;
use App\Models\Facility;
use App\Models\FacilityBooking;
use App\Models\User;
use App\Support\SocietyClock;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The one definition of "can this facility be booked at this time", shared by
 * the web booking form and the mobile API so the two cannot drift.
 *
 * Before this existed the facility's own settings - opening hours, available
 * days, how far ahead it can be booked, the longest booking, whether it needs
 * approval, what it charges - were ignored by both clients: the app offered a
 * hard-coded 06:00-22:00 grid for every facility, never showed what was
 * already taken, always wrote an amount of 0 and always waited for approval.
 *
 * Times are wall-clock times at the facility ("09:00"), compared as minutes
 * since midnight. They are never parsed through a timezone: a 09:00 booking is
 * 09:00 on the society's clock. Only "is this slot already in the past" asks
 * the society's clock (SocietyClock), because that is the one question whose
 * answer depends on the real current time.
 */
class FacilityBookingService
{
    /** A booking in one of these states no longer occupies its slot. */
    public const FREE_STATUSES = ['cancelled', 'rejected'];

    /** Used when a facility has no opening/closing time set. */
    private const DEFAULT_OPEN = '06:00';
    private const DEFAULT_CLOSE = '22:00';

    private const STEP_MINUTES = 60;

    // ── time helpers ────────────────────────────────────────────────────────

    /** "09:30" / "09:30:00" -> 570. */
    public static function minutes(string $time): int
    {
        [$h, $m] = array_map('intval', array_pad(explode(':', $time), 2, '0'));

        return $h * 60 + $m;
    }

    /** 570 -> "09:30" (1440 reads as "24:00"; callers show it as midnight). */
    public static function clock(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    /** "H:i" or "H:i:s" -> "H:i:s", the one form stored for new bookings. */
    public static function normalizeTime(string $time): string
    {
        return self::clock(self::minutes($time)) . ':00';
    }

    /** 570 -> "9:30 AM" for messages. */
    public static function label(int $minutes): string
    {
        return Carbon::createFromTime(intdiv($minutes, 60) % 24, $minutes % 60)->format('g:i A');
    }

    /**
     * Opening and closing as [openMinute, closeMinute]. A closing time of
     * 00:00 means "until midnight"; an unusable pair falls back to the default
     * so a half-filled facility still has a sensible bookable window.
     *
     * @return array{0:int,1:int}
     */
    public static function window(Facility $facility): array
    {
        $open  = self::minutes($facility->opening_time ?: self::DEFAULT_OPEN);
        $close = self::minutes($facility->closing_time ?: self::DEFAULT_CLOSE);

        if ($close === 0) {
            $close = 1440;
        }
        if ($close <= $open) {
            return [self::minutes(self::DEFAULT_OPEN), self::minutes(self::DEFAULT_CLOSE)];
        }

        return [$open, $close];
    }

    private static function maxMinutes(Facility $facility): int
    {
        return max(1, (int) ($facility->max_booking_hours ?: 4)) * 60;
    }

    private static function dayName(string $date): string
    {
        return strtolower(Carbon::parse($date)->format('l'));
    }

    // ── occupancy ───────────────────────────────────────────────────────────

    /**
     * Bookings that currently hold a slot on $date, as [startMinute, endMinute].
     * Read in PHP rather than compared as SQL strings: stored times are mixed
     * ("10:00" from the web form, "10:00:00" from the app), and comparing
     * those lexicographically misjudges the boundaries.
     *
     * @return list<array{0:int,1:int}>
     */
    public static function occupied(Facility $facility, string $date, ?int $excludeBookingId = null): array
    {
        return FacilityBooking::where('facility_id', $facility->id)
            ->whereDate('booking_date', $date)
            ->whereNotIn('status', self::FREE_STATUSES)
            ->when($excludeBookingId, fn ($q) => $q->where('id', '!=', $excludeBookingId))
            ->get(['start_time', 'end_time'])
            ->map(fn ($b) => [self::minutes($b->start_time->format('H:i')), self::minutes($b->end_time->format('H:i'))])
            ->all();
    }

    public static function overlaps(Facility $facility, string $date, int $start, int $end, ?int $excludeBookingId = null): bool
    {
        foreach (self::occupied($facility, $date, $excludeBookingId) as [$s, $e]) {
            // Two ranges overlap unless one ends at or before the other starts.
            if ($start < $e && $s < $end) {
                return true;
            }
        }

        return false;
    }

    /**
     * Why $booking cannot be moved to $newStatus without double-booking its
     * slot - or null when it can. Creating and editing a booking already
     * refuse a taken slot; the decision step did not, so a request that was
     * filed before those checks existed (or any pending request that overlaps
     * one that was approved) could be approved on top of it. Only the moves
     * that HOLD the slot are checked: rejecting, cancelling and completing
     * always succeed, and a no-op is never a conflict.
     */
    public static function transitionConflict(FacilityBooking $booking, string $newStatus): ?string
    {
        if ($newStatus === $booking->status || !in_array($newStatus, ['approved', 'pending'], true)) {
            return null;
        }

        $facility = $booking->facility ?: Facility::find($booking->facility_id);
        if (!$facility) {
            return null;
        }

        $date  = Carbon::parse($booking->booking_date)->toDateString();
        $start = self::minutes(Carbon::parse($booking->start_time)->format('H:i'));
        $end   = self::minutes(Carbon::parse($booking->end_time)->format('H:i'));

        if (self::overlaps($facility, $date, $start, $end, $booking->id)) {
            return "Another booking already holds {$facility->name} at that time on " . Carbon::parse($date)->format('d M Y')
                . '. Reject or cancel the other booking first, or reject this one.';
        }

        return null;
    }

    // ── rules ───────────────────────────────────────────────────────────────

    /**
     * Why the whole day cannot be booked at all (closed that weekday, in the
     * past, too far ahead) - null when it can.
     */
    public static function dayClosedReason(Facility $facility, string $date): ?string
    {
        $today = SocietyClock::today($facility->society_id)->toDateString();
        $days  = $facility->available_days;

        if ($date < $today) {
            return 'Choose today or a later date.';
        }

        $advance = (int) ($facility->advance_booking_days ?: 30);
        if ($date > Carbon::parse($today)->addDays($advance)->toDateString()) {
            return "{$facility->name} can be booked up to {$advance} days in advance.";
        }

        if (is_array($days) && $days !== [] && !in_array(self::dayName($date), array_map('strtolower', $days), true)) {
            return "{$facility->name} is not available on " . Carbon::parse($date)->format('l') . 's.';
        }

        return null;
    }

    /**
     * The first rule a resident's request breaks, in words for the resident -
     * or null. (Overlap is checked separately, under the facility lock.)
     */
    public static function violation(Facility $facility, string $date, string $start, string $end): ?string
    {
        $s = self::minutes($start);
        $e = self::minutes($end);

        if ($e <= $s) {
            return 'The end time must be after the start time.';
        }

        if ($reason = self::dayClosedReason($facility, $date)) {
            return $reason;
        }

        [$open, $close] = self::window($facility);
        if ($s < $open || $e > $close) {
            return "{$facility->name} is open from " . self::label($open) . ' to ' . self::label($close) . '.';
        }

        $max = self::maxMinutes($facility);
        if ($e - $s > $max) {
            $hours = intdiv($max, 60);

            return "A booking can be at most {$hours} hour" . ($hours === 1 ? '' : 's') . '.';
        }

        $now = SocietyClock::now($facility->society_id);
        if ($date === $now->toDateString() && $s <= $now->hour * 60 + $now->minute) {
            return 'That time has already passed. Choose a later time.';
        }

        return null;
    }

    /** "pending" when the facility needs the office's approval, else "approved". */
    public static function initialStatus(Facility $facility): string
    {
        return $facility->requires_approval ? 'pending' : 'approved';
    }

    // ── availability (what the app shows before booking) ────────────────────

    /**
     * One day of a facility as a booking grid: hourly start slots from opening
     * to closing, each saying whether it can be booked and for how many hours
     * from there (limited by the facility's maximum and the next booking).
     *
     * @return array<string,mixed>
     */
    public static function availability(Facility $facility, string $date): array
    {
        [$open, $close] = self::window($facility);
        $closed   = self::dayClosedReason($facility, $date);
        $occupied = $closed ? [] : self::occupied($facility, $date);
        $maxHours = intdiv(self::maxMinutes($facility), 60);

        $now      = SocietyClock::now($facility->society_id);
        $isToday  = $date === $now->toDateString();
        $nowMinute = $now->hour * 60 + $now->minute;

        $taken = function (int $from, int $to) use ($occupied): bool {
            foreach ($occupied as [$s, $e]) {
                if ($from < $e && $s < $to) {
                    return true;
                }
            }

            return false;
        };

        $slots = [];
        for ($m = $open; $m + self::STEP_MINUTES <= $close; $m += self::STEP_MINUTES) {
            $reason = null;
            $hours  = 0;

            if ($closed) {
                $reason = 'closed';
            } elseif ($isToday && $m <= $nowMinute) {
                $reason = 'past';
            } elseif ($taken($m, $m + self::STEP_MINUTES)) {
                $reason = 'booked';
            } else {
                while ($hours < $maxHours
                    && $m + ($hours + 1) * self::STEP_MINUTES <= $close
                    && !$taken($m + $hours * self::STEP_MINUTES, $m + ($hours + 1) * self::STEP_MINUTES)) {
                    $hours++;
                }
            }

            $slots[] = [
                'start'     => self::clock($m),
                'end'       => self::clock($m + self::STEP_MINUTES),
                'available' => $reason === null,
                'max_hours' => $hours,
                'reason'    => $reason,
            ];
        }

        return [
            'date'              => $date,
            'open'              => self::clock($open),
            'close'             => self::clock($close),
            'max_hours'         => $maxHours,
            'charge'            => (float) ($facility->booking_charge ?? 0),
            'requires_approval' => (bool) $facility->requires_approval,
            'bookable'          => $closed === null,
            'message'           => $closed,
            'slots'             => $slots,
        ];
    }

    // ── booking ─────────────────────────────────────────────────────────────

    /**
     * Make a booking, atomically.
     *
     * The facility row is locked so two requests for one slot are serialised:
     * the second one's overlap check runs after the first has committed and
     * sees its row. (SQLite has no row locks but serialises writers itself, so
     * the guarantee holds on both engines.)
     *
     * $enforceRules is false only for an office user booking on someone's
     * behalf (manage_facilities), who may book outside the facility's usual
     * hours; a taken slot is refused for everyone.
     *
     * @param array{booking_date:string,start_time:string,end_time:string,purpose?:?string,expected_guests?:?int} $data
     * @throws FacilityBookingException
     */
    public static function create(int $societyId, User $user, int $facilityId, ?int $flatId, array $data, bool $enforceRules = true): FacilityBooking
    {
        return DB::transaction(function () use ($societyId, $user, $facilityId, $flatId, $data, $enforceRules) {
            // A facility from another society is "not found", never bookable.
            $facility = Facility::where('id', $facilityId)
                ->where('society_id', $societyId)
                ->where('status', 'active')
                ->lockForUpdate()
                ->first();

            if (!$facility) {
                throw new FacilityBookingException('Facility not found.', 404);
            }

            $date  = Carbon::parse($data['booking_date'])->toDateString();
            $start = self::normalizeTime($data['start_time']);
            $end   = self::normalizeTime($data['end_time']);

            if ($enforceRules && ($message = self::violation($facility, $date, $start, $end))) {
                throw new FacilityBookingException($message, 422);
            }

            if (self::overlaps($facility, $date, self::minutes($start), self::minutes($end))) {
                throw new FacilityBookingException('This time slot is already booked. Please choose a different time.', 422);
            }

            $status = self::initialStatus($facility);

            return FacilityBooking::create([
                'society_id'       => $societyId,
                'facility_id'      => $facility->id,
                'user_id'          => $user->id,
                'flat_id'          => $flatId,
                'booking_date'     => $date,
                'start_time'       => $start,
                'end_time'         => $end,
                'purpose'          => $data['purpose'] ?? null,
                'expected_guests'  => $data['expected_guests'] ?? 0,
                'booking_amount'   => $facility->booking_charge ?? 0,
                'security_deposit' => 0,
                'payment_status'   => 'pending',
                'status'           => $status,
                'approved_at'      => $status === 'approved' ? now() : null,
            ]);
        });
    }
}
