<?php

namespace Tests\Feature;

use App\Models\Gate;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Services\GuardShiftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class GuardShiftAttendanceTest extends TestCase
{
    use RefreshDatabase;

    private function makeAssignment($society, $guard, string $start = '06:00', string $end = '14:00', string $date = '2026-01-10', int $grace = 15): ShiftAssignment
    {
        $gate = Gate::create(['society_id' => $society->id, 'name' => 'Main Gate', 'type' => 'main', 'is_active' => true]);
        $shift = Shift::create(['society_id' => $society->id, 'name' => 'Shift', 'start_time' => $start, 'end_time' => $end, 'grace_period_minutes' => $grace, 'is_active' => true]);

        return ShiftAssignment::create([
            'society_id' => $society->id, 'shift_id' => $shift->id, 'gate_id' => $gate->id,
            'guard_user_id' => $guard->id, 'date' => $date, 'status' => ShiftAssignment::STATUS_SCHEDULED,
        ]);
    }

    public function test_starting_a_shift_on_time_marks_present(): void
    {
        $society = $this->makeSociety();
        $guard = $this->makeUserWithRole($society, 'Guard');
        $assignment = $this->makeAssignment($society, $guard);

        $service = new GuardShiftService();
        $result = $service->startShift($assignment, Carbon::parse('2026-01-10 06:05:00', 'Asia/Kolkata'));

        $this->assertSame(ShiftAssignment::STATUS_PRESENT, $result->status);
        $this->assertNotNull($result->checked_in_at);
    }

    public function test_starting_a_shift_after_the_grace_period_marks_late(): void
    {
        $society = $this->makeSociety();
        $guard = $this->makeUserWithRole($society, 'Guard');
        $assignment = $this->makeAssignment($society, $guard, grace: 15);

        $service = new GuardShiftService();
        $result = $service->startShift($assignment, Carbon::parse('2026-01-10 06:20:00', 'Asia/Kolkata'));

        $this->assertSame(ShiftAssignment::STATUS_LATE, $result->status);
    }

    public function test_a_shift_cannot_be_started_twice(): void
    {
        $society = $this->makeSociety();
        $guard = $this->makeUserWithRole($society, 'Guard');
        $assignment = $this->makeAssignment($society, $guard);

        $service = new GuardShiftService();
        $service->startShift($assignment, Carbon::parse('2026-01-10 06:00:00', 'Asia/Kolkata'));

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $service->startShift($assignment->refresh(), Carbon::parse('2026-01-10 06:10:00', 'Asia/Kolkata'));
    }

    public function test_ending_a_shift_that_was_never_started_is_rejected(): void
    {
        $society = $this->makeSociety();
        $guard = $this->makeUserWithRole($society, 'Guard');
        $assignment = $this->makeAssignment($society, $guard);

        $service = new GuardShiftService();

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $service->endShift($assignment);
    }

    public function test_ending_a_started_shift_marks_checked_out(): void
    {
        $society = $this->makeSociety();
        $guard = $this->makeUserWithRole($society, 'Guard');
        $assignment = $this->makeAssignment($society, $guard);

        $service = new GuardShiftService();
        $service->startShift($assignment, Carbon::parse('2026-01-10 06:00:00', 'Asia/Kolkata'));
        $result = $service->endShift($assignment->refresh(), Carbon::parse('2026-01-10 14:00:00', 'Asia/Kolkata'));

        $this->assertSame(ShiftAssignment::STATUS_CHECKED_OUT, $result->status);
        $this->assertNotNull($result->checked_out_at);
    }

    /**
     * The exact bug class Access Pass was fixed for this session: an
     * overnight shift's "current assignment" lookup after midnight must
     * still resolve to the assignment that started the day before.
     */
    public function test_an_overnight_shift_is_the_current_assignment_after_midnight(): void
    {
        $society = $this->makeSociety();
        $guard = $this->makeUserWithRole($society, 'Guard');
        $this->makeAssignment($society, $guard, '22:00', '06:00', '2026-01-10');

        $service = new GuardShiftService();
        $current = $service->currentAssignmentFor($guard, Carbon::parse('2026-01-11 02:00:00', 'Asia/Kolkata'));

        $this->assertNotNull($current);
        $this->assertSame('2026-01-10', $current->date->toDateString());
    }

    /**
     * Regression: a shift's "09:00" was read in the application timezone
     * (UTC on the live databases) while "now" came from the society's clock
     * (IST). A guard on a 09:00-18:00 shift was "not on shift" until 14:30 and
     * a shift could be marked missed 5.5 hours early. The clock is set the way
     * production sees it: an instant in UTC, a society on Asia/Kolkata.
     */
    public function test_a_guard_is_on_shift_during_the_societys_working_hours_whatever_the_servers_timezone(): void
    {
        $society = $this->makeSociety();
        $guard = $this->makeUserWithRole($society, 'Guard');
        $assignment = $this->makeAssignment($society, $guard, '09:00', '18:00', '2026-01-10');
        $service = new GuardShiftService();

        try {
            Carbon::setTestNow(Carbon::parse('2026-01-10 03:00:00', 'UTC')); // 08:30 IST - not started
            $this->assertNull($service->currentAssignmentFor($guard), '08:30 IST is before a 09:00 shift');

            Carbon::setTestNow(Carbon::parse('2026-01-10 04:30:00', 'UTC')); // 10:00 IST
            $this->assertNotNull($service->currentAssignmentFor($guard), '10:00 IST is inside a 09:00-18:00 shift');

            Carbon::setTestNow(Carbon::parse('2026-01-10 12:00:00', 'UTC')); // 17:30 IST
            $this->assertNotNull($service->currentAssignmentFor($guard), '17:30 IST is still inside it');

            Carbon::setTestNow(Carbon::parse('2026-01-10 13:00:00', 'UTC')); // 18:30 IST - over
            $this->assertNull($service->currentAssignmentFor($guard), '18:30 IST is after it');

            // On-time vs late is also the society's clock: 09:10 IST is inside
            // the 15-minute grace, 09:20 IST is not.
            $this->assertSame(ShiftAssignment::STATUS_PRESENT, $service->startShift($assignment, Carbon::parse('2026-01-10 03:40:00', 'UTC'))->status);
            $second = $this->makeAssignment($society, $this->makeUserWithRole($society, 'Guard'), '09:00', '18:00', '2026-01-10');
            $this->assertSame(ShiftAssignment::STATUS_LATE, $service->startShift($second, Carbon::parse('2026-01-10 03:50:00', 'UTC'))->status);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_a_guard_has_no_current_assignment_outside_their_shift_window(): void
    {
        $society = $this->makeSociety();
        $guard = $this->makeUserWithRole($society, 'Guard');
        $this->makeAssignment($society, $guard, '06:00', '14:00', '2026-01-10');

        $service = new GuardShiftService();
        $current = $service->currentAssignmentFor($guard, Carbon::parse('2026-01-10 20:00:00', 'Asia/Kolkata'));

        $this->assertNull($current);
    }

    public function test_guard_shift_screen_only_shows_the_authenticated_guards_own_shift(): void
    {
        $society = $this->makeSociety();
        $guardA = $this->makeUserWithRole($society, 'Guard', ['name' => 'Guard Alpha']);
        $guardB = $this->makeUserWithRole($society, 'Guard', ['name' => 'Guard Bravo']);
        $this->makeAssignment($society, $guardA, '06:00', '14:00', \App\Support\SocietyClock::today($society->id)->toDateString());

        $response = $this->actingAs($guardB)->getJson(route('guard.shift.current'));

        $response->assertOk()->assertJson(['success' => true, 'data' => null]);
    }
}
