<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Gate;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Services\GuardShiftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuardReplacementTest extends TestCase
{
    use RefreshDatabase;

    private function makeAssignment($society, $guard): ShiftAssignment
    {
        $gate = Gate::create(['society_id' => $society->id, 'name' => 'Main Gate', 'type' => 'main', 'is_active' => true]);
        $shift = Shift::create(['society_id' => $society->id, 'name' => 'Shift', 'start_time' => '06:00', 'end_time' => '14:00', 'grace_period_minutes' => 15, 'is_active' => true]);

        return ShiftAssignment::create([
            'society_id' => $society->id, 'shift_id' => $shift->id, 'gate_id' => $gate->id,
            'guard_user_id' => $guard->id, 'date' => '2026-01-10', 'status' => 'scheduled',
        ]);
    }

    public function test_admin_can_replace_a_guard_and_the_original_stays_a_separate_audited_row(): void
    {
        $society = $this->makeSociety();
        $admin = $this->makeUserWithRole($society, 'Admin');
        $original = $this->makeUserWithRole($society, 'Guard', ['name' => 'Original']);
        $replacement = $this->makeUserWithRole($society, 'Guard', ['name' => 'Replacement']);
        $assignment = $this->makeAssignment($society, $original);

        $this->actingAs($admin)->post(route('admin.shift-assignments.replace', $assignment), [
            'replacement_guard_id' => $replacement->id,
            'reason' => 'Original guard called in sick',
        ])->assertRedirect();

        $this->assertDatabaseHas('shift_assignments', [
            'id' => $assignment->id, 'status' => 'replaced', 'replaced_by_user_id' => $replacement->id,
        ]);
        $this->assertDatabaseHas('shift_assignments', [
            'guard_user_id' => $replacement->id, 'shift_id' => $assignment->shift_id,
            'gate_id' => $assignment->gate_id, 'date' => '2026-01-10 00:00:00', 'status' => 'scheduled',
        ]);
    }

    public function test_replacement_is_audited_with_who_what_and_why(): void
    {
        $society = $this->makeSociety();
        $admin = $this->makeUserWithRole($society, 'Admin');
        $original = $this->makeUserWithRole($society, 'Guard');
        $replacement = $this->makeUserWithRole($society, 'Guard');
        $assignment = $this->makeAssignment($society, $original);

        // ActivityLog::recordFor() reads auth()->id() - acting as the
        // admin here matches how this is actually reached in production
        // (the admin's own authenticated request), not a quirk of calling
        // the service directly in a test.
        $this->actingAs($admin);
        (new GuardShiftService())->replace($assignment, $replacement, $admin, 'Emergency replacement');

        $log = ActivityLog::where('model_type', ShiftAssignment::class)->where('model_id', $assignment->id)->first();

        $this->assertNotNull($log);
        $this->assertSame('guard_shift_replaced', $log->action);
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame($replacement->id, $log->new_values['replaced_by_user_id']);
        $this->assertSame('Emergency replacement', $log->new_values['reason']);
    }

    public function test_a_closed_assignment_cannot_be_replaced(): void
    {
        $society = $this->makeSociety();
        $admin = $this->makeUserWithRole($society, 'Admin');
        $original = $this->makeUserWithRole($society, 'Guard');
        $replacement = $this->makeUserWithRole($society, 'Guard');
        $assignment = $this->makeAssignment($society, $original);
        $assignment->update(['status' => 'checked_out']);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        (new GuardShiftService())->replace($assignment, $replacement, $admin, 'too late');
    }

    public function test_replacement_guard_must_belong_to_the_same_society(): void
    {
        $societyA = $this->makeSociety('Society A');
        $societyB = $this->makeSociety('Society B');
        $admin = $this->makeUserWithRole($societyA, 'Admin');
        $original = $this->makeUserWithRole($societyA, 'Guard');
        $outsider = $this->makeUserWithRole($societyB, 'Guard');
        $assignment = $this->makeAssignment($societyA, $original);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        (new GuardShiftService())->replace($assignment, $outsider, $admin, 'wrong society');
    }

    public function test_admin_cannot_replace_a_guard_on_another_societys_assignment(): void
    {
        $societyA = $this->makeSociety('Society A');
        $societyB = $this->makeSociety('Society B');
        $adminB = $this->makeUserWithRole($societyB, 'Admin');
        $original = $this->makeUserWithRole($societyA, 'Guard');
        $replacementCandidate = $this->makeUserWithRole($societyA, 'Guard');
        $assignment = $this->makeAssignment($societyA, $original);

        $this->actingAs($adminB)->post(route('admin.shift-assignments.replace', $assignment), [
            'replacement_guard_id' => $replacementCandidate->id,
            'reason' => 'hijack attempt',
        ])->assertForbidden();

        $this->assertDatabaseHas('shift_assignments', ['id' => $assignment->id, 'status' => 'scheduled']);
    }
}
