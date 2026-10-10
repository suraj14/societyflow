<?php

namespace Tests\Feature;

use App\Models\Gate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GateManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_gate(): void
    {
        $society = $this->makeSociety();
        $admin = $this->makeUserWithRole($society, 'Admin');

        $this->actingAs($admin)->post(route('admin.gates.store'), [
            'name' => 'Main Gate',
            'type' => 'main',
        ])->assertRedirect(route('admin.gates.index'));

        $this->assertDatabaseHas('gates', ['name' => 'Main Gate', 'society_id' => $society->id, 'is_active' => 1]);
    }

    public function test_guard_cannot_create_a_gate(): void
    {
        $society = $this->makeSociety();
        $guard = $this->makeUserWithRole($society, 'Guard');

        $this->actingAs($guard)->post(route('admin.gates.store'), [
            'name' => 'Main Gate',
            'type' => 'main',
        ])->assertForbidden();
    }

    public function test_admin_cannot_edit_another_societys_gate(): void
    {
        $societyA = $this->makeSociety('Society A');
        $societyB = $this->makeSociety('Society B');
        $adminB = $this->makeUserWithRole($societyB, 'Admin');

        $gateA = Gate::create(['society_id' => $societyA->id, 'name' => 'Gate A', 'type' => 'main', 'is_active' => true]);

        $this->actingAs($adminB)->get(route('admin.gates.edit', $gateA))->assertForbidden();
        $this->actingAs($adminB)->put(route('admin.gates.update', $gateA), ['name' => 'Hijacked', 'type' => 'main'])->assertForbidden();
        $this->assertDatabaseHas('gates', ['id' => $gateA->id, 'name' => 'Gate A']);
    }

    public function test_gate_list_only_shows_the_admins_own_society(): void
    {
        $societyA = $this->makeSociety('Society A');
        $societyB = $this->makeSociety('Society B');
        $adminA = $this->makeUserWithRole($societyA, 'Admin');

        Gate::create(['society_id' => $societyA->id, 'name' => 'Gate A', 'type' => 'main', 'is_active' => true]);
        Gate::create(['society_id' => $societyB->id, 'name' => 'Gate B', 'type' => 'main', 'is_active' => true]);

        $html = $this->actingAs($adminA)->get(route('admin.gates.index'))->assertOk()->getContent();

        $this->assertStringContainsString('Gate A', $html);
        $this->assertStringNotContainsString('Gate B', $html);
    }

    public function test_a_gate_with_assignments_cannot_be_deleted(): void
    {
        $society = $this->makeSociety();
        $admin = $this->makeUserWithRole($society, 'Admin');
        $guard = $this->makeUserWithRole($society, 'Guard');
        $gate = Gate::create(['society_id' => $society->id, 'name' => 'Main Gate', 'type' => 'main', 'is_active' => true]);
        $shift = \App\Models\Shift::create([
            'society_id' => $society->id, 'name' => 'Morning', 'start_time' => '06:00', 'end_time' => '14:00', 'grace_period_minutes' => 15, 'is_active' => true,
        ]);
        \App\Models\ShiftAssignment::create([
            'society_id' => $society->id, 'shift_id' => $shift->id, 'gate_id' => $gate->id,
            'guard_user_id' => $guard->id, 'date' => now()->toDateString(), 'status' => 'scheduled',
        ]);

        $this->actingAs($admin)->delete(route('admin.gates.destroy', $gate))->assertRedirect();

        $this->assertDatabaseHas('gates', ['id' => $gate->id]);
    }
}
