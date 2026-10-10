<?php

namespace Tests\Feature;

use App\Models\Visitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The guard's "Waiting" tile must count the same queue as the list beneath
 * it and the API's `pending` figure: every visitor still awaiting a decision,
 * from any day. Before this, the web tile counted only today, so it could
 * read 3 while its own list showed 11 and the mobile app showed 11.
 */
class GuardWaitingCountConsistencyTest extends TestCase
{
    use RefreshDatabase;

    private function visitor(int $societyId, int $flatId, string $approval, string $entry, $when): void
    {
        Visitor::create([
            'society_id'          => $societyId,
            'flat_id'             => $flatId,
            'visitor_name'        => 'Consistency Test',
            'visitor_phone'       => '9000000000',
            'visitor_type'        => 'guest',
            'expected_entry_time' => $when,
            'approval_status'     => $approval,
            'entry_status'        => $entry,
        ]);
    }

    public function test_waiting_tile_matches_the_list_and_the_api_pending_count(): void
    {
        $society = $this->makeSociety();
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));
        $guard   = $this->makeUserWithRole($society, 'Guard');

        $this->visitor($society->id, $flat->id, 'pending', 'pending', now());              // waiting, today
        $this->visitor($society->id, $flat->id, 'pending', 'pending', now()->subDays(2));  // waiting, older
        $this->visitor($society->id, $flat->id, 'allowed', 'pending', now());              // expected, not waiting

        $web = $this->actingAs($guard)->get('/guard');
        $web->assertOk();
        $this->assertSame(2, $web->viewData('stats')['waiting']);

        Sanctum::actingAs($guard);
        $api = $this->getJson('/api/v1/visitors/dashboard')->assertOk();

        $this->assertSame(2, $api->json('data.pending'));
        $this->assertSame($api->json('data.pending'), $web->viewData('stats')['waiting']);
    }

    public function test_waiting_excludes_denied_visitors(): void
    {
        $society = $this->makeSociety();
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));
        $guard   = $this->makeUserWithRole($society, 'Guard');

        $this->visitor($society->id, $flat->id, 'denied', 'pending', now());

        $web = $this->actingAs($guard)->get('/guard');
        $web->assertOk();
        $this->assertSame(0, $web->viewData('stats')['waiting']);
    }
}
