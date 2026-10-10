<?php

namespace Tests\Feature;

use App\Models\Gate;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Services\GuardShiftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuardShiftAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private function makeGate($society): Gate
    {
        return Gate::create(['society_id' => $society->id, 'name' => 'Main Gate', 'type' => 'main', 'is_active' => true]);
    }

    private function makeShift($society, string $start = '06:00', string $end = '14:00'): Shift
    {
        return Shift::create([
            'society_id' => $society->id, 'name' => 'Shift', 'start_time' => $start, 'end_time' => $end,
            'grace_period_minutes' => 15, 'is_active' => true,
        ]);
    }

    public function test_admin_can_assign_a_guard_to_a_shift_and_gate(): void
    {
        $society = $this->makeSociety();
        $admin = $this->makeUserWithRole($society, 'Admin');
        $guard = $this->makeUserWithRole($society, 'Guard');
        $gate = $this->makeGate($society);
        $shift = $this->makeShift($society);

        $this->actingAs($admin)->post(route('admin.shift-assignments.store'), [
            'guard_user_id' => $guard->id,
            'shift_id'      => $shift->id,
            'gate_id'       => $gate->id,
            'date'          => '2026-01-10',
        ])->assertRedirect();

        $this->assertDatabaseHas('shift_assignments', [
            'guard_user_id' => $guard->id, 'shift_id' => $shift->id, 'gate_id' => $gate->id, 'date' => '2026-01-10 00:00:00',
        ]);
    }

    public function test_assigning_the_same_guard_to_an_overlapping_shift_is_rejected(): void
    {
        $society = $this->makeSociety();
        $admin = $this->makeUserWithRole($society, 'Admin');
        $guard = $this->makeUserWithRole($society, 'Guard');
        $gate = $this->makeGate($society);
        $morning = $this->makeShift($society, '06:00', '14:00');
        $overlapping = $this->makeShift($society, '10:00', '18:00');

        $service = new GuardShiftService();
        $service->assign([
            'society_id' => $society->id, 'shift_id' => $morning->id, 'gate_id' => $gate->id,
            'guard_user_id' => $guard->id, 'date' => '2026-01-10',
        ]);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $service->assign([
            'society_id' => $society->id, 'shift_id' => $overlapping->id, 'gate_id' => $gate->id,
            'guard_user_id' => $guard->id, 'date' => '2026-01-10',
        ]);
    }

    public function test_an_overnight_shift_blocks_a_morning_shift_the_next_day_for_the_same_guard(): void
    {
        $society = $this->makeSociety();
        $guard = $this->makeUserWithRole($society, 'Guard');
        $gate = $this->makeGate($society);
        $night = $this->makeShift($society, '22:00', '06:00');
        $morning = $this->makeShift($society, '05:00', '13:00');

        $service = new GuardShiftService();
        // Night shift starting 2026-01-10 22:00 runs until 2026-01-11 06:00.
        $service->assign([
            'society_id' => $society->id, 'shift_id' => $night->id, 'gate_id' => $gate->id,
            'guard_user_id' => $guard->id, 'date' => '2026-01-10',
        ]);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        // Morning shift on 2026-01-11 05:00-13:00 overlaps the night
        // shift's 05:00-06:00 tail.
        $service->assign([
            'society_id' => $society->id, 'shift_id' => $morning->id, 'gate_id' => $gate->id,
            'guard_user_id' => $guard->id, 'date' => '2026-01-11',
        ]);
    }

    public function test_cannot_assign_a_guard_from_a_different_society(): void
    {
        $societyA = $this->makeSociety('Society A');
        $societyB = $this->makeSociety('Society B');
        $guardB = $this->makeUserWithRole($societyB, 'Guard');
        $gate = $this->makeGate($societyA);
        $shift = $this->makeShift($societyA);

        $service = new GuardShiftService();

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $service->assign([
            'society_id' => $societyA->id, 'shift_id' => $shift->id, 'gate_id' => $gate->id,
            'guard_user_id' => $guardB->id, 'date' => '2026-01-10',
        ]);
    }

    public function test_cannot_assign_a_non_guard_user(): void
    {
        $society = $this->makeSociety();
        $resident = $this->makeUserWithRole($society, 'Tenant');
        $gate = $this->makeGate($society);
        $shift = $this->makeShift($society);

        $service = new GuardShiftService();

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $service->assign([
            'society_id' => $society->id, 'shift_id' => $shift->id, 'gate_id' => $gate->id,
            'guard_user_id' => $resident->id, 'date' => '2026-01-10',
        ]);
    }

    public function test_roster_list_is_scoped_to_the_admins_own_society(): void
    {
        $societyA = $this->makeSociety('Society A');
        $societyB = $this->makeSociety('Society B');
        $adminA = $this->makeUserWithRole($societyA, 'Admin');
        $guardA = $this->makeUserWithRole($societyA, 'Guard', ['name' => 'Guard Alpha']);
        $guardB = $this->makeUserWithRole($societyB, 'Guard', ['name' => 'Guard Bravo']);
        $gateA = $this->makeGate($societyA);
        $gateB = $this->makeGate($societyB);
        $shiftA = $this->makeShift($societyA);
        $shiftB = $this->makeShift($societyB);

        ShiftAssignment::create(['society_id' => $societyA->id, 'shift_id' => $shiftA->id, 'gate_id' => $gateA->id, 'guard_user_id' => $guardA->id, 'date' => '2026-01-10', 'status' => 'scheduled']);
        ShiftAssignment::create(['society_id' => $societyB->id, 'shift_id' => $shiftB->id, 'gate_id' => $gateB->id, 'guard_user_id' => $guardB->id, 'date' => '2026-01-10', 'status' => 'scheduled']);

        $html = $this->actingAs($adminA)->get(route('admin.shift-assignments.index', ['date' => '2026-01-10']))->assertOk()->getContent();

        $this->assertStringContainsString('Guard Alpha', $html);
        $this->assertStringNotContainsString('Guard Bravo', $html);
    }
}
