<?php

namespace Tests\Feature;

use App\Models\Society;
use App\Models\User;
use App\Models\Visitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `VisitorApiController`'s allow/deny/checkIn/checkOut endpoints (the mobile
 * app's visitor-approval path) had no test at all before this - P1-34 fixed
 * the `visitor_logs.action` enum those methods write to, blind to whether
 * the endpoints themselves actually round-trip correctly end to end.
 */
class VisitorApiApprovalTest extends TestCase
{
    use RefreshDatabase;

    private Society $society;
    private User $resident;
    private User $guard;
    private Visitor $visitor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->society = $this->makeSociety();
        $flat = $this->makeFlat($this->society, $this->makeBuilding($this->society));
        $this->resident = $this->makeResidentUser($this->society, $flat);
        $this->resident->assignRole($this->makeRole('Tenant')->name);
        $this->guard = $this->makeUserWithRole($this->society, 'Guard');

        $this->visitor = Visitor::create([
            'society_id' => $this->society->id, 'flat_id' => $flat->id,
            'host_user_id' => $this->resident->id, 'visitor_name' => 'API Guest',
            'visitor_phone' => '9123456780', 'visitor_type' => 'guest',
            'expected_entry_time' => now(), 'approval_status' => 'pending', 'entry_status' => 'pending',
        ]);
    }

    public function test_resident_can_approve_own_visitor_via_api(): void
    {
        $this->actingAs($this->resident)->postJson("/api/v1/visitors/{$this->visitor->id}/approve")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.approval_status', 'allowed');

        $this->assertSame('allowed', $this->visitor->fresh()->approval_status);
        $this->assertDatabaseHas('visitor_logs', ['visitor_id' => $this->visitor->id, 'action' => 'allowed']);
    }

    public function test_resident_can_deny_own_visitor_via_api(): void
    {
        $this->actingAs($this->resident)->postJson("/api/v1/visitors/{$this->visitor->id}/reject", ['reason' => 'Not expecting anyone'])
            ->assertOk()
            ->assertJsonPath('data.approval_status', 'denied');

        $this->assertSame('denied', $this->visitor->fresh()->approval_status);
        $this->assertDatabaseHas('visitor_logs', ['visitor_id' => $this->visitor->id, 'action' => 'denied']);
    }

    public function test_resident_cannot_approve_another_residents_visitor(): void
    {
        $otherFlat = $this->makeFlat($this->society, $this->makeBuilding($this->society, 'Tower B'), '201');
        $other = $this->makeResidentUser($this->society, $otherFlat);
        $other->assignRole($this->makeRole('Tenant')->name);

        $this->actingAs($other)->postJson("/api/v1/visitors/{$this->visitor->id}/approve")
            ->assertStatus(404);

        $this->assertSame('pending', $this->visitor->fresh()->approval_status);
    }

    public function test_check_in_and_check_out_round_trip_via_api(): void
    {
        $this->visitor->update(['approval_status' => 'allowed']);

        $this->actingAs($this->guard)->postJson("/api/v1/visitors/{$this->visitor->id}/check-in")
            ->assertOk()
            ->assertJsonPath('data.entry_status', 'entered');
        $this->assertSame('entered', $this->visitor->fresh()->entry_status);
        $this->assertDatabaseHas('visitor_logs', ['visitor_id' => $this->visitor->id, 'action' => 'entry']);

        $this->actingAs($this->guard)->postJson("/api/v1/visitors/{$this->visitor->id}/check-out")
            ->assertOk()
            ->assertJsonPath('data.entry_status', 'exited');
        $this->assertSame('exited', $this->visitor->fresh()->entry_status);
        $this->assertDatabaseHas('visitor_logs', ['visitor_id' => $this->visitor->id, 'action' => 'exit']);
    }

    public function test_check_in_rejected_before_approval(): void
    {
        $this->actingAs($this->guard)->postJson("/api/v1/visitors/{$this->visitor->id}/check-in")
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertSame('pending', $this->visitor->fresh()->entry_status);
    }

    public function test_resident_cannot_check_in_another_residents_visitor(): void
    {
        $otherFlat = $this->makeFlat($this->society, $this->makeBuilding($this->society, 'Tower C'), '301');
        $other = $this->makeResidentUser($this->society, $otherFlat);
        $other->assignRole($this->makeRole('Tenant')->name);
        $this->visitor->update(['approval_status' => 'allowed']);

        $this->actingAs($other)->postJson("/api/v1/visitors/{$this->visitor->id}/check-in")
            ->assertStatus(403);

        $this->assertSame('pending', $this->visitor->fresh()->entry_status);
    }

    public function test_approve_and_check_in_are_scoped_to_the_users_own_society(): void
    {
        $otherSociety = $this->makeSociety('Other');
        $otherGuard = $this->makeUserWithRole($otherSociety, 'Guard');

        $this->actingAs($otherGuard)->postJson("/api/v1/visitors/{$this->visitor->id}/check-in")
            ->assertStatus(404);
    }
}
