<?php

namespace Tests\Feature;

use App\Models\Society;
use App\Models\User;
use App\Models\Visitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Check-in/check-out respond with JSON when asked, so the gate dashboard can
 * update in place without a full page reload (brief §4/§18). The original
 * redirect-with-flash behaviour is untouched for a plain form POST - proven
 * separately by the existing GuardGateTest suite, which sends no Accept
 * header and still passes unmodified.
 */
class GuardInstantUpdateTest extends TestCase
{
    use RefreshDatabase;

    private Society $society;
    private User $guard;
    private int $buildingId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->society = $this->makeSociety('Instant Update Society');
        $this->guard   = $this->makeUserWithRole($this->society, 'Guard');
        $this->buildingId = $this->makeBuilding($this->society)->id;
    }

    private function visitorFor(array $overrides = []): Visitor
    {
        static $n = 0;
        $flat = $this->makeFlat($this->society, \App\Models\Building::find($this->buildingId), 'A-' . (++$n));

        return Visitor::create(array_merge([
            'society_id'          => $this->society->id,
            'flat_id'             => $flat->id,
            // host_user_id is NOT NULL on MariaDB (filed as P1-15 -
            // nullable on SQLite only); every real registration path sets
            // it, so the fixture does too, matching GuardGateTest.
            'host_user_id'        => $this->guard->id,
            'visitor_name'        => 'Test Visitor',
            'visitor_phone'       => '9876543210',
            'visitor_type'        => 'guest',
            'expected_entry_time' => now(),
            'approval_status'     => 'allowed',
            'entry_status'        => 'pending',
        ], $overrides));
    }

    public function test_check_in_returns_json_with_updated_stats_when_asked(): void
    {
        $visitor = $this->visitorFor();

        $response = $this->actingAs($this->guard)
            ->postJson(route('guard.visitors.check-in', $visitor))
            ->assertOk();

        $response->assertJson([
            'success' => true,
            'status'  => 'entered',
        ]);
        $this->assertSame($visitor->id, $response->json('visitor_id'));
        $this->assertSame(1, $response->json('stats.inside'));
        $this->assertSame(0, $response->json('stats.expected'));
    }

    public function test_check_out_returns_json_with_updated_stats(): void
    {
        $visitor = $this->visitorFor(['entry_status' => 'entered', 'actual_entry_time' => now()]);

        $response = $this->actingAs($this->guard)
            ->postJson(route('guard.visitors.check-out', $visitor))
            ->assertOk();

        $response->assertJson(['success' => true, 'status' => 'exited']);
        $this->assertSame(1, $response->json('stats.departed'));
        $this->assertSame(0, $response->json('stats.inside'));
    }

    /** An unapproved visitor is refused - JSON path, same rule as the redirect path. */
    public function test_an_unapproved_visitor_cannot_be_checked_in_via_json_either(): void
    {
        $visitor = $this->visitorFor(['approval_status' => 'pending']);

        $response = $this->actingAs($this->guard)
            ->postJson(route('guard.visitors.check-in', $visitor));

        $response->assertStatus(422);
        $this->assertFalse($response->json('success'));
        $this->assertSame('pending', $visitor->fresh()->entry_status);
    }

    /** Checking in twice must fail cleanly the second time, not double-count. */
    public function test_checking_in_twice_the_second_call_fails_and_stats_do_not_double_count(): void
    {
        $visitor = $this->visitorFor();

        $this->actingAs($this->guard)->postJson(route('guard.visitors.check-in', $visitor))->assertOk();

        $second = $this->actingAs($this->guard)->postJson(route('guard.visitors.check-in', $visitor));

        $second->assertStatus(422);
        $this->assertFalse($second->json('success'));
        $this->assertSame(1, $second->json('stats.inside'), 'the visitor must still be counted exactly once');
    }

    /** Cross-society isolation must hold on the JSON path exactly as it does on the redirect path. */
    public function test_a_foreign_societys_visitor_cannot_be_checked_in_via_json(): void
    {
        $other      = $this->makeSociety('Other Society');
        $flat       = $this->makeFlat($other, $this->makeBuilding($other));
        $otherGuard = $this->makeUserWithRole($other, 'Guard');
        $foreign = Visitor::create([
            'society_id' => $other->id, 'flat_id' => $flat->id, 'host_user_id' => $otherGuard->id,
            'visitor_name' => 'Foreign', 'visitor_phone' => '9000000000', 'visitor_type' => 'guest',
            'expected_entry_time' => now(), 'approval_status' => 'allowed', 'entry_status' => 'pending',
        ]);

        $this->actingAs($this->guard)
            ->postJson(route('guard.visitors.check-in', $foreign))
            ->assertForbidden();

        $this->assertSame('pending', $foreign->fresh()->entry_status);
    }

    /** A plain (non-JS) form submission is completely unaffected. */
    public function test_a_plain_form_post_still_redirects_with_a_flash_message(): void
    {
        $visitor = $this->visitorFor();

        $this->actingAs($this->guard)
            ->post(route('guard.visitors.check-in', $visitor))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('entered', $visitor->fresh()->entry_status);
    }
}
