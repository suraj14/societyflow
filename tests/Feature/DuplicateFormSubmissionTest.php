<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\ComplaintCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A double click, a browser re-POST or a retried request must not create a
 * second record. Create forms carry a one-shot `_idempotency` token
 * (partials/idempotency); PreventDuplicateFormSubmission refuses a replay.
 */
class DuplicateFormSubmissionTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $society  = $this->makeSociety();
        $building = $this->makeBuilding($society);
        $flat     = $this->makeFlat($society, $building);
        $resident = $this->makeResidentUser($society, $flat);
        // The complaint notifier resolves society admins by role name.
        $this->makeRole('Admin');
        $category = ComplaintCategory::create(['society_id' => $society->id, 'name' => 'Plumbing', 'status' => 'active']);

        return compact('society', 'flat', 'resident', 'category');
    }

    public function test_the_same_token_creates_one_complaint_and_the_replay_is_refused(): void
    {
        $f = $this->fixture();
        $payload = [
            'category_id' => $f['category']->id, 'title' => 'Leak', 'description' => 'Kitchen tap leaks',
            'priority' => 'medium', '_idempotency' => 'tok-' . uniqid(),
        ];

        $first = $this->actingAs($f['resident'])->post('/complaints', $payload);
        $first->assertRedirect();
        $this->assertSame(1, Complaint::count(), 'first submission failed: ' . json_encode(session()->all()));

        $this->actingAs($f['resident'])->post('/complaints', $payload)
            ->assertRedirect()
            ->assertSessionHas('info');
        $this->assertSame(1, Complaint::count(), 'replay must not create a second complaint');

        // A fresh token (a new page render) is a new submission.
        $this->actingAs($f['resident'])->post('/complaints', ['_idempotency' => 'tok-' . uniqid()] + $payload)->assertRedirect();
        $this->assertSame(2, Complaint::count());
    }

    public function test_a_validation_failure_does_not_burn_the_token(): void
    {
        $f = $this->fixture();
        $token = 'tok-' . uniqid();

        $this->actingAs($f['resident'])->from('/complaints/create')->post('/complaints', [
            'category_id' => $f['category']->id, 'title' => '', 'description' => '', 'priority' => 'medium', '_idempotency' => $token,
        ])->assertSessionHasErrors();
        $this->assertSame(0, Complaint::count());

        $this->actingAs($f['resident'])->post('/complaints', [
            'category_id' => $f['category']->id, 'title' => 'Fixed up', 'description' => 'Now valid',
            'priority' => 'medium', '_idempotency' => $token,
        ])->assertRedirect();
        $this->assertSame(1, Complaint::count(), 'the corrected submission goes through');
    }

    public function test_create_forms_carry_the_token(): void
    {
        $f = $this->fixture();
        $html = $this->actingAs($f['resident'])->get('/complaints/create')->assertOk()->getContent();
        $this->assertStringContainsString('name="_idempotency"', $html);
    }
}
