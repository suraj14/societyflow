<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\ComplaintCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Complaints: create → assign → in progress → resolve → close, and the
 * cross-society/cross-resident boundary around it. No test file covered
 * either before this (CLAUDE.md regression search found none).
 */
class ComplaintLifecycleAndIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function category($society): ComplaintCategory
    {
        return ComplaintCategory::create([
            'society_id' => $society->id,
            'name'       => 'Plumbing',
            'status'     => 'active',
        ]);
    }

    public function test_full_lifecycle_create_assign_progress_resolve_close(): void
    {
        $society  = $this->makeSociety();
        $flat     = $this->makeFlat($society, $this->makeBuilding($society));
        $resident = $this->makeResidentUser($society, $flat);
        $admin    = $this->makeUserWithRole($society, 'Admin');
        $staff    = $this->makeUserWithRole($society, 'Staff');
        $category = $this->category($society);

        // Create, as the resident.
        $this->actingAs($resident)->post('/complaints', [
            'category_id' => $category->id,
            'title'       => 'Leaking tap',
            'description' => 'Kitchen tap leaking since morning.',
            'priority'    => 'medium',
        ])->assertRedirect(route('complaints.index'));

        $complaint = Complaint::where('title', 'Leaking tap')->firstOrFail();
        $this->assertSame('open', $complaint->status);
        $this->assertSame($resident->id, $complaint->created_by);

        // Assign, as admin.
        $this->actingAs($admin)->post("/complaints/{$complaint->id}/assign", [
            'assigned_to' => $staff->id,
        ])->assertRedirect();
        $this->assertSame($staff->id, $complaint->fresh()->assigned_to);

        // Move to in progress, as the assigned staff member.
        $this->actingAs($staff)->post("/complaints/{$complaint->id}/update", [
            'message' => 'Plumber dispatched.',
            'type'    => 'status_change',
            'status'  => 'in_progress',
        ])->assertRedirect();
        $this->assertSame('in_progress', $complaint->fresh()->status);

        // Resolve, as staff.
        $this->actingAs($staff)->post("/complaints/{$complaint->id}/update", [
            'message' => 'Washer replaced.',
            'type'    => 'status_change',
            'status'  => 'resolved',
        ])->assertRedirect();
        $this->assertSame('resolved', $complaint->fresh()->status);
        $this->assertNotNull($complaint->fresh()->resolved_at);

        // Close, as admin.
        $this->actingAs($admin)->post("/complaints/{$complaint->id}/update", [
            'message' => 'Confirmed fixed, closing.',
            'type'    => 'status_change',
            'status'  => 'closed',
        ])->assertRedirect();
        $this->assertSame('closed', $complaint->fresh()->status);

        // The reporter sees every step.
        $this->assertSame(4, $complaint->fresh()->updates()->count());
    }

    public function test_a_resident_cannot_change_their_own_complaints_status(): void
    {
        $society  = $this->makeSociety();
        $flat     = $this->makeFlat($society, $this->makeBuilding($society));
        $resident = $this->makeResidentUser($society, $flat);
        $complaint = Complaint::create([
            'society_id' => $society->id, 'flat_id' => $flat->id,
            'created_by' => $resident->id, 'title' => 'Noise',
            'description' => 'Loud music at night.', 'priority' => 'low', 'status' => 'open',
        ]);

        $this->actingAs($resident)->post("/complaints/{$complaint->id}/update", [
            'message' => 'Still happening',
            'type'    => 'status_change',
            'status'  => 'resolved',
        ])->assertForbidden();

        $this->assertSame('open', $complaint->fresh()->status);
    }

    public function test_a_resident_cannot_view_another_residents_complaint(): void
    {
        $society = $this->makeSociety();
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));
        $a       = $this->makeResidentUser($society, $flat);
        $b       = $this->makeResidentUser($society, $flat);

        $complaint = Complaint::create([
            'society_id' => $society->id, 'flat_id' => $flat->id,
            'created_by' => $a->id, 'title' => 'Private issue',
            'description' => 'x', 'priority' => 'low', 'status' => 'open',
        ]);

        $this->actingAs($b)->get("/complaints/{$complaint->id}")->assertForbidden();
    }

    public function test_an_admin_in_another_society_cannot_view_update_or_assign(): void
    {
        $societyA = $this->makeSociety();
        $flatA    = $this->makeFlat($societyA, $this->makeBuilding($societyA));
        $residentA = $this->makeResidentUser($societyA, $flatA);
        $complaint = Complaint::create([
            'society_id' => $societyA->id, 'flat_id' => $flatA->id,
            'created_by' => $residentA->id, 'title' => 'SECRET Complaint A',
            'description' => 'x', 'priority' => 'low', 'status' => 'open',
        ]);

        $societyB = $this->makeSociety();
        $adminB   = $this->makeUserWithRole($societyB, 'Admin');
        $staffB   = $this->makeUserWithRole($societyB, 'Staff');

        $this->actingAs($adminB)->get("/complaints/{$complaint->id}")->assertForbidden();

        $this->actingAs($adminB)->post("/complaints/{$complaint->id}/assign", [
            'assigned_to' => $staffB->id,
        ])->assertForbidden();

        $this->actingAs($adminB)->post("/complaints/{$complaint->id}/update", [
            'message' => 'take this', 'type' => 'status_change', 'status' => 'closed',
        ])->assertForbidden();

        $this->actingAs($adminB)->delete("/complaints/{$complaint->id}")->assertForbidden();

        $this->assertSame('open', $complaint->fresh()->status);
        $this->assertSame('SECRET Complaint A', $complaint->fresh()->title);
    }

    public function test_the_complaint_list_never_shows_another_societys_complaints(): void
    {
        $societyA = $this->makeSociety();
        $flatA    = $this->makeFlat($societyA, $this->makeBuilding($societyA));
        $residentA = $this->makeResidentUser($societyA, $flatA);
        Complaint::create([
            'society_id' => $societyA->id, 'flat_id' => $flatA->id,
            'created_by' => $residentA->id, 'title' => 'SECRET Complaint For B Leak Test',
            'description' => 'x', 'priority' => 'low', 'status' => 'open',
        ]);

        $societyB = $this->makeSociety();
        $adminB   = $this->makeUserWithRole($societyB, 'Admin');

        $this->actingAs($adminB)->get('/complaints')
            ->assertOk()
            ->assertDontSee('SECRET Complaint For B Leak Test');
    }

    public function test_assigning_to_a_staff_member_of_another_society_is_rejected(): void
    {
        $societyA = $this->makeSociety();
        $flatA    = $this->makeFlat($societyA, $this->makeBuilding($societyA));
        $residentA = $this->makeResidentUser($societyA, $flatA);
        $adminA    = $this->makeUserWithRole($societyA, 'Admin');
        $complaint = Complaint::create([
            'society_id' => $societyA->id, 'flat_id' => $flatA->id,
            'created_by' => $residentA->id, 'title' => 'Leak',
            'description' => 'x', 'priority' => 'low', 'status' => 'open',
        ]);

        $societyB = $this->makeSociety();
        $staffB   = $this->makeUserWithRole($societyB, 'Staff');

        $this->actingAs($adminA)->post("/complaints/{$complaint->id}/assign", [
            'assigned_to' => $staffB->id,
        ])->assertSessionHasErrors('assigned_to');

        $this->assertNull($complaint->fresh()->assigned_to);
    }
}
