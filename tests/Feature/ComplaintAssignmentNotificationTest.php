<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\EmailLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Assigning a complaint moves it to "in progress" and gives it an owner. It used to tell nobody: no e-mail, bell or
 * WhatsApp to the resident who raised it, nothing to the person it was given to. It now uses the existing TicketUpdated
 * event for the resident (the same path as every other status change) and a bell entry for the assignee.
 */
class ComplaintAssignmentNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function setUpComplaint(): array
    {
        Mail::fake();
        $society  = $this->makeSociety();
        $flat     = $this->makeFlat($society, $this->makeBuilding($society));
        $resident = $this->makeResidentUser($society, $flat);
        $resident->assignRole($this->makeRole('Tenant')->name);
        $admin    = $this->makeUserWithRole($society, 'Admin');
        $staff    = $this->makeUserWithRole($society, 'Staff', ['name' => 'Plumber Pete']);
        $category = ComplaintCategory::create(['society_id' => $society->id, 'name' => 'Plumbing', 'status' => 'active']);

        $this->actingAs($resident)->post('/complaints', ['category_id' => $category->id, 'title' => 'Leaking tap', 'description' => 'Kitchen tap leaking since morning.', 'priority' => 'medium']);

        return [$society, $resident, $admin, $staff, Complaint::where('title', 'Leaking tap')->firstOrFail()];
    }

    private function titles($user): array
    {
        return $user->notifications()->orderBy('id')->get()->map(fn ($n) => $n->data['title'])->all();
    }

    public function test_the_resident_and_the_assignee_are_told_when_a_complaint_is_assigned(): void
    {
        [$society, $resident, $admin, $staff, $complaint] = $this->setUpComplaint();
        $this->assertSame([], $this->titles($staff));

        $this->actingAs($admin)->post("/complaints/{$complaint->id}/assign", ['assigned_to' => $staff->id])->assertRedirect();

        $this->assertSame('in_progress', $complaint->fresh()->status);
        $this->assertContains('Complaint updated', $this->titles($resident), 'the resident hears it is now in progress');
        $this->assertSame(['Complaint assigned to you'], $this->titles($staff));
        $this->assertSame(1, EmailLog::where('trigger_action', 'ticket_updated')->where('recipient_email', $resident->email)->where('status', 'sent')->count(), 'and by e-mail, through the existing template');
        $this->assertNotContains('Complaint assigned to you', $this->titles($admin), 'the person who assigned it is not told what they just did');
    }

    public function test_assigning_it_to_yourself_tells_you_nothing_and_re_assigning_does_not_repeat_the_status_message(): void
    {
        [$society, $resident, $admin, $staff, $complaint] = $this->setUpComplaint();

        $this->actingAs($admin)->post("/complaints/{$complaint->id}/assign", ['assigned_to' => $admin->id])->assertRedirect();
        $this->assertNotContains('Complaint assigned to you', $this->titles($admin));   // (the admin does have "New complaint" from when it was raised)
        $before = count($this->titles($resident));

        // already in progress: a different assignee is told, but the status did not change so the resident is not messaged again
        $this->actingAs($admin)->post("/complaints/{$complaint->id}/assign", ['assigned_to' => $staff->id])->assertRedirect();
        $this->assertSame($before, count($this->titles($resident)));
        $this->assertSame(['Complaint assigned to you'], $this->titles($staff));
    }

    public function test_another_society_cannot_be_assigned_or_notified(): void
    {
        [$society, $resident, $admin, $staff, $complaint] = $this->setUpComplaint();
        $outsider = $this->makeUserWithRole($this->makeSociety('Elsewhere'), 'Staff');

        $this->actingAs($admin)->post("/complaints/{$complaint->id}/assign", ['assigned_to' => $outsider->id])->assertSessionHasErrors('assigned_to');

        $this->assertSame([], $this->titles($outsider));
        $this->assertNull($complaint->fresh()->assigned_to);
    }
}
