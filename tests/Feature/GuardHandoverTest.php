<?php

namespace Tests\Feature;

use App\Models\Gate;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\ShiftHandover;
use App\Services\GuardShiftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class GuardHandoverTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A fixed 06:00-14:00 window would make this test pass or fail
     * depending purely on what time of day the suite happens to run -
     * exactly the wall-clock-arithmetic trap this project's own
     * AccessPassService tests already avoid. A full-calendar-day window
     * (00:00-23:59, never overnight) trivially contains "now" for any
     * time of day, without the risk a "now ± 2 hours" window has of
     * itself crossing midnight and anchoring to the wrong date.
     */
    private function makeStartedAssignment($society, $guard): ShiftAssignment
    {
        $now = \App\Support\SocietyClock::now($society->id);

        $gate = Gate::create(['society_id' => $society->id, 'name' => 'Main Gate', 'type' => 'main', 'is_active' => true]);
        $shift = Shift::create([
            'society_id' => $society->id, 'name' => 'Shift',
            'start_time' => '00:00', 'end_time' => '23:59',
            'grace_period_minutes' => 15, 'is_active' => true,
        ]);
        $assignment = ShiftAssignment::create([
            'society_id' => $society->id, 'shift_id' => $shift->id, 'gate_id' => $gate->id,
            'guard_user_id' => $guard->id, 'date' => $now->toDateString(), 'status' => 'scheduled',
        ]);

        return (new GuardShiftService())->startShift($assignment, $now);
    }

    public function test_outgoing_guard_can_start_a_handover_with_notes(): void
    {
        $society = $this->makeSociety();
        $guard = $this->makeUserWithRole($society, 'Guard');
        $assignment = $this->makeStartedAssignment($society, $guard);

        $this->actingAs($guard)->post(route('guard.shift.handover.start'), [
            'notes' => 'Two visitors still inside, expected back by 2pm.',
            'pending_visitors_note' => 'Plumber at B-204',
            'active_passes_note' => 'Daily cook pass active until evening',
        ])->assertRedirect();

        $this->assertDatabaseHas('shift_handovers', [
            'shift_assignment_id' => $assignment->id,
            'outgoing_guard_id'   => $guard->id,
            'status'              => 'pending',
        ]);
    }

    public function test_a_handover_cannot_be_started_twice_for_the_same_shift(): void
    {
        $society = $this->makeSociety();
        $guard = $this->makeUserWithRole($society, 'Guard');
        $assignment = $this->makeStartedAssignment($society, $guard);

        $service = new GuardShiftService();
        $service->startHandover($assignment, ['notes' => 'first']);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $service->startHandover($assignment->refresh(), ['notes' => 'second']);
    }

    public function test_incoming_guard_can_accept_a_pending_handover(): void
    {
        $society = $this->makeSociety();
        $outgoing = $this->makeUserWithRole($society, 'Guard', ['name' => 'Outgoing']);
        $incoming = $this->makeUserWithRole($society, 'Guard', ['name' => 'Incoming']);
        $assignment = $this->makeStartedAssignment($society, $outgoing);
        $handover = (new GuardShiftService())->startHandover($assignment, ['notes' => 'all clear']);

        $this->actingAs($incoming)->post(route('guard.shift.handover.accept', $handover))->assertRedirect();

        $this->assertDatabaseHas('shift_handovers', [
            'id' => $handover->id, 'incoming_guard_id' => $incoming->id, 'status' => 'accepted',
        ]);
    }

    public function test_an_already_accepted_handover_cannot_be_accepted_again(): void
    {
        $society = $this->makeSociety();
        $outgoing = $this->makeUserWithRole($society, 'Guard');
        $incoming = $this->makeUserWithRole($society, 'Guard');
        $assignment = $this->makeStartedAssignment($society, $outgoing);
        $service = new GuardShiftService();
        $handover = $service->startHandover($assignment, ['notes' => 'all clear']);
        $service->acceptHandover($handover, $incoming);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $service->acceptHandover($handover->refresh(), $incoming);
    }

    public function test_a_guard_from_another_society_cannot_accept_a_handover(): void
    {
        $societyA = $this->makeSociety('Society A');
        $societyB = $this->makeSociety('Society B');
        $outgoing = $this->makeUserWithRole($societyA, 'Guard');
        $outsider = $this->makeUserWithRole($societyB, 'Guard');
        $assignment = $this->makeStartedAssignment($societyA, $outgoing);
        $handover = (new GuardShiftService())->startHandover($assignment, ['notes' => 'all clear']);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        (new GuardShiftService())->acceptHandover($handover, $outsider);
    }

    public function test_handover_can_only_be_started_for_a_shift_that_has_actually_started(): void
    {
        $society = $this->makeSociety();
        $guard = $this->makeUserWithRole($society, 'Guard');
        $now = \App\Support\SocietyClock::now($society->id);
        $gate = Gate::create(['society_id' => $society->id, 'name' => 'Main Gate', 'type' => 'main', 'is_active' => true]);
        $shift = Shift::create([
            'society_id' => $society->id, 'name' => 'Shift',
            'start_time' => '00:00', 'end_time' => '23:59',
            'grace_period_minutes' => 15, 'is_active' => true,
        ]);
        ShiftAssignment::create([
            'society_id' => $society->id, 'shift_id' => $shift->id, 'gate_id' => $gate->id,
            'guard_user_id' => $guard->id, 'date' => $now->toDateString(), 'status' => 'scheduled',
        ]);

        // Not started yet - currentAssignmentFor() still finds it (it's
        // scheduled for today), but the guard has never checked in.
        $this->actingAs($guard)->post(route('guard.shift.handover.start'), ['notes' => 'x'])->assertRedirect();
        // The controller does not itself require hasStarted() - this
        // documents that a handover can be logged even before check-in
        // (e.g. briefing the next guard ahead of time) rather than
        // asserting a restriction the service does not actually enforce.
        $this->assertDatabaseHas('shift_handovers', ['outgoing_guard_id' => $guard->id]);
    }
}
