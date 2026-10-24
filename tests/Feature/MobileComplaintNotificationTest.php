<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * A complaint raised from the mobile app notifies the office exactly like one raised on the website (it used to notify nobody).
 */
class MobileComplaintNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function titles($user): array
    {
        return $user->notifications()->orderBy('id')->get()->map(fn ($n) => $n->data['title'])->all();
    }

    public function test_a_complaint_filed_from_the_app_tells_this_societys_admin_and_nobody_elses(): void
    {
        Mail::fake();
        $society = $this->makeSociety('Royal');
        $other = $this->makeSociety('Other');
        $admin = $this->makeUserWithRole($society, 'Admin');
        $strangerAdmin = $this->makeUserWithRole($other, 'Admin');
        $resident = $this->makeUserWithRole($society, 'Tenant');

        Sanctum::actingAs($resident);
        $this->postJson('/api/v1/complaints', ['title' => 'Leaking tap', 'description' => 'Kitchen tap leaking since morning.'])->assertCreated();

        $this->assertContains('New complaint', $this->titles($admin), 'the society\'s Admin is told, as on the website');
        $this->assertSame([], $this->titles($strangerAdmin), 'another society\'s Admin is never told');
        $this->assertSame([], $this->titles($resident), 'the person who filed it is not told about their own complaint');
        $this->assertDatabaseHas('complaints', ['society_id' => $society->id, 'created_by' => $resident->id, 'title' => 'Leaking tap']);
    }

    public function test_a_failure_while_notifying_never_fails_the_submission(): void
    {
        $society = $this->makeSociety('Royal');
        $resident = $this->makeUserWithRole($society, 'Tenant');
        // no Admin at all, and e-mail is blocked: nobody to notify, and the complaint must still be saved
        Sanctum::actingAs($resident);

        $this->postJson('/api/v1/complaints', ['title' => 'Noise', 'description' => 'Loud music after midnight.'])->assertCreated()->assertJsonPath('success', true);
        $this->assertDatabaseHas('complaints', ['title' => 'Noise']);
    }
}
