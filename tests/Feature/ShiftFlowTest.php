<?php

namespace Tests\Feature;

use App\Models\Gate;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\Society;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Admin > Shifts: edit and delete (neither was referenced by a test), plus society isolation. */
class ShiftFlowTest extends TestCase
{
    use RefreshDatabase;

    private Society $a;
    private Society $b;
    private User $adminA;
    private User $super;

    protected function setUp(): void
    {
        parent::setUp();
        $this->a = $this->makeSociety('Alpha');
        $this->b = $this->makeSociety('Beta');
        $this->adminA = $this->makeUserWithRole($this->a, 'Admin');
        $this->super = $this->makeUserWithRole($this->a, 'Super Admin', ['society_id' => null]);
    }

    private function shift(Society $s, string $name = 'Morning'): Shift
    {
        return Shift::create(['society_id' => $s->id, 'name' => $name, 'start_time' => '06:00', 'end_time' => '14:00', 'grace_period_minutes' => 10, 'is_active' => true]);
    }

    public function test_an_admin_creates_edits_deactivates_and_deletes_a_shift(): void
    {
        $this->actingAs($this->adminA)->post(route('admin.shifts.store'), ['name' => 'Night', 'start_time' => '22:00', 'end_time' => '06:00', 'grace_period_minutes' => 15])
            ->assertRedirect(route('admin.shifts.index'))->assertSessionHas('success');
        $shift = Shift::firstOrFail();
        $this->assertSame($this->a->id, (int) $shift->society_id);

        $this->actingAs($this->adminA)->put(route('admin.shifts.update', $shift), ['name' => 'Late night', 'start_time' => '23:00', 'end_time' => '07:00', 'grace_period_minutes' => 5, 'is_active' => 0])
            ->assertRedirect(route('admin.shifts.index'))->assertSessionHas('success');
        $shift->refresh();
        $this->assertSame('Late night', $shift->name);
        $this->assertFalse((bool) $shift->is_active);

        $this->actingAs($this->adminA)->delete(route('admin.shifts.destroy', $shift))->assertSessionHas('success');
        $this->assertNull($shift->fresh());
    }

    public function test_invalid_shift_times_and_limits_are_refused(): void
    {
        $shift = $this->shift($this->a);

        foreach ([['start_time' => '25:99'], ['end_time' => 'noon'], ['grace_period_minutes' => 500], ['name' => '']] as $bad) {
            $this->actingAs($this->adminA)->put(route('admin.shifts.update', $shift), array_merge(['name' => 'X', 'start_time' => '06:00', 'end_time' => '14:00', 'grace_period_minutes' => 10], $bad))
                ->assertSessionHasErrors(array_keys($bad));
        }
        $this->assertSame('Morning', $shift->fresh()->name);
    }

    public function test_a_shift_with_assignments_cannot_be_deleted(): void
    {
        $shift = $this->shift($this->a);
        $guard = $this->makeUserWithRole($this->a, 'Guard');
        $gate = Gate::create(['society_id' => $this->a->id, 'name' => 'Main gate', 'is_active' => true]);
        ShiftAssignment::create(['society_id' => $this->a->id, 'shift_id' => $shift->id, 'gate_id' => $gate->id, 'guard_user_id' => $guard->id, 'date' => now()->toDateString(), 'status' => 'scheduled', 'created_by' => $this->adminA->id]);

        $this->actingAs($this->adminA)->delete(route('admin.shifts.destroy', $shift))->assertSessionHas('error');

        $this->assertNotNull($shift->fresh());
    }

    public function test_an_admin_cannot_edit_or_delete_another_societys_shift(): void
    {
        $theirs = $this->shift($this->b, 'Theirs');

        $this->actingAs($this->adminA)->put(route('admin.shifts.update', $theirs), ['name' => 'Hijacked', 'start_time' => '01:00', 'end_time' => '02:00', 'grace_period_minutes' => 1])->assertStatus(403);
        $this->actingAs($this->adminA)->delete(route('admin.shifts.destroy', $theirs))->assertStatus(403);

        $this->assertSame('Theirs', $theirs->fresh()->name);
    }

    public function test_a_super_admin_can_manage_a_shift_but_cannot_create_a_society_less_one(): void
    {
        $theirs = $this->shift($this->b, 'Theirs');

        $this->actingAs($this->super)->put(route('admin.shifts.update', $theirs), ['name' => 'Renamed', 'start_time' => '06:00', 'end_time' => '14:00', 'grace_period_minutes' => 10])->assertSessionHas('success');
        $this->assertSame('Renamed', $theirs->fresh()->name);

        $response = $this->actingAs($this->super)->post(route('admin.shifts.store'), ['name' => 'Orphan', 'start_time' => '06:00', 'end_time' => '14:00', 'grace_period_minutes' => 10]);
        $this->assertLessThan(500, $response->getStatusCode());
        $this->assertSame(0, Shift::where('name', 'Orphan')->count(), 'no society-less shift');
    }
}
