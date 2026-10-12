<?php

namespace Tests\Feature;

use App\Models\ComplaintCategory;
use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * What the mobile API is allowed to show a resident.
 */
class ApiContentVisibilityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * These tests exercise the Events API, which - like the web - answers 403 until a society switches the Events
     * module on (Settings -> Features, default off). Societies made here have it on; the 403 itself is pinned in
     * MobileCapabilitiesTest.
     */
    protected function makeSociety(string $name = 'Test Society'): \App\Models\Society
    {
        $society = parent::makeSociety($name);
        \App\Models\SystemSetting::set('features', 'enable_events', true, 'boolean', $society->id);

        return $society;
    }

    private function makeEvent(int $societyId, array $overrides = []): Event
    {
        return Event::create(array_merge([
            'society_id'    => $societyId,
            'event_name'    => 'Society Meeting',
            'location'      => 'Community Hall',
            'description'   => 'Annual general meeting',
            'start_date'    => now()->addWeek(),
            'end_date'      => now()->addWeek()->addHours(2),
            'status'        => 'pending',
            'is_role_based' => true,
            'visible_roles' => ['Tenant', 'Apartment Owner'],
        ], $overrides));
    }

    public function test_cancelled_events_are_not_listed(): void
    {
        $society = $this->makeSociety();
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));
        $user    = $this->makeResidentUser($society, $flat);
        $user->assignRole($this->makeRole('Tenant')->name);

        $this->makeEvent($society->id, ['event_name' => 'Live Event']);
        $this->makeEvent($society->id, ['event_name' => 'Called Off', 'status' => 'cancelled']);

        Sanctum::actingAs($user->fresh());

        $titles = collect($this->getJson('/api/v1/events')->assertOk()->json('data'))
            ->pluck('title')->all();

        $this->assertContains('Live Event', $titles);
        $this->assertNotContains('Called Off', $titles, 'a cancelled event was served to a resident');
    }

    /**
     * Events carry per-role visibility that the web honours via
     * Event::isVisibleToUser. The API ignored it, so an event restricted to
     * one role was listed for every resident in the society.
     */
    public function test_role_restricted_events_are_hidden_from_other_roles(): void
    {
        $society = $this->makeSociety();
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));

        $this->makeRole('Tenant');
        $this->makeRole('Apartment Owner');

        $tenant = $this->makeResidentUser($society, $flat);
        $tenant->assignRole('Tenant');

        $owner = $this->makeResidentUser($society, $flat);
        $owner->assignRole('Apartment Owner');

        $this->makeEvent($society->id, [
            'event_name'    => 'Owners Only',
            'is_role_based' => true,
            'visible_roles' => ['Apartment Owner'],
        ]);

        Sanctum::actingAs($owner->fresh());
        $ownerTitles = collect($this->getJson('/api/v1/events')->assertOk()->json('data'))->pluck('title')->all();
        $this->assertContains('Owners Only', $ownerTitles);

        Sanctum::actingAs($tenant->fresh());
        $tenantTitles = collect($this->getJson('/api/v1/events')->assertOk()->json('data'))->pluck('title')->all();
        $this->assertNotContains('Owners Only', $tenantTitles, 'a role-restricted event leaked to another role');
    }

    public function test_fetching_a_hidden_event_by_id_is_a_404(): void
    {
        $society = $this->makeSociety();
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));

        $this->makeRole('Apartment Owner');
        $tenant = $this->makeResidentUser($society, $flat);
        $tenant->assignRole($this->makeRole('Tenant')->name);

        $event = $this->makeEvent($society->id, [
            'event_name'    => 'Owners Only',
            'is_role_based' => true,
            'visible_roles' => ['Apartment Owner'],
        ]);

        Sanctum::actingAs($tenant->fresh());
        $this->getJson("/api/v1/events/{$event->id}")->assertStatus(404);
    }

    /**
     * The event payload must actually carry data.
     *
     * EventApiController::formatEvent read $e->title, ->event_date,
     * ->start_time, ->end_time and ->venue - none of which are columns on
     * events (they are event_name, start_date, end_date and location). Every
     * one serialised as null, so the app's Events screen rendered rows with
     * no title, date or venue. The JSON keys are the installed app's
     * contract, so they stay; only the mapping was wrong.
     */
    public function test_event_payload_is_populated_from_the_real_columns(): void
    {
        $society = $this->makeSociety();
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));
        $user    = $this->makeResidentUser($society, $flat);
        $user->assignRole($this->makeRole('Tenant')->name);

        $start = now()->addWeek()->setTime(18, 30);
        $end   = (clone $start)->addHours(3);

        $this->makeEvent($society->id, [
            'event_name' => 'Diwali Celebration',
            'location'   => 'Central Lawn',
            'start_date' => $start,
            'end_date'   => $end,
        ]);

        Sanctum::actingAs($user->fresh());

        $event = $this->getJson('/api/v1/events')->assertOk()->json('data.0');

        $this->assertSame('Diwali Celebration', $event['title']);
        $this->assertSame('Central Lawn', $event['venue']);
        $this->assertSame($start->toDateString(), $event['event_date']);
        $this->assertSame('18:30', $event['start_time']);
        $this->assertSame('21:30', $event['end_time']);

        // 'pending' in the database is a future event to the app, which only
        // renders labels for upcoming/ongoing/completed.
        $this->assertSame('upcoming', $event['status']);
    }

    public function test_a_finished_event_reports_as_completed(): void
    {
        $society = $this->makeSociety();
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));
        $user    = $this->makeResidentUser($society, $flat);
        $user->assignRole($this->makeRole('Tenant')->name);

        $this->makeEvent($society->id, [
            'event_name' => 'Last Month Meetup',
            'start_date' => now()->subMonth(),
            'end_date'   => now()->subMonth()->addHours(2),
        ]);

        Sanctum::actingAs($user->fresh());

        $this->assertSame('completed', $this->getJson('/api/v1/events')->assertOk()->json('data.0.status'));
    }

    /**
     * Same defect class as the event payload: formatFacility read
     * $f->charges, ->open_time and ->close_time, none of which are columns
     * (booking_charge, opening_time, closing_time), so every facility
     * reached the app showing no fee and no opening hours.
     */
    public function test_facility_payload_is_populated_from_the_real_columns(): void
    {
        $society = $this->makeSociety();
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));
        $user    = $this->makeResidentUser($society, $flat);

        \App\Models\Facility::create([
            'society_id'     => $society->id,
            'name'           => 'Clubhouse',
            'type'           => 'clubhouse',
            'booking_charge' => 750.50,
            'capacity'       => 80,
            'opening_time'   => '06:00',
            'closing_time'   => '22:00',
            'status'         => 'active',
        ]);

        Sanctum::actingAs($user);

        $facility = $this->getJson('/api/v1/facilities')->assertOk()->json('data.0');

        $this->assertSame('Clubhouse', $facility['name']);
        $this->assertSame(750.5, $facility['charges']);
        $this->assertSame(80, $facility['capacity']);
        $this->assertNotNull($facility['open_time'], 'opening hours were dropped');
        $this->assertNotNull($facility['close_time'], 'closing hours were dropped');
    }

    // ── Complaint category matching ──────────────────────────────────────────

    /**
     * The hint was matched with LIKE '%hint%', so a single character matched
     * an arbitrary category and the complaint was filed under it.
     */
    public function test_a_one_character_category_hint_does_not_match_a_category(): void
    {
        $society = $this->makeSociety();
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));
        $user    = $this->makeResidentUser($society, $flat);

        ComplaintCategory::create([
            'society_id' => $society->id, 'name' => 'Plumbing', 'status' => 'active',
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/complaints', [
            'title'       => 'Vague',
            'description' => 'Something is wrong',
            'category'    => 'l', // appears inside "Plumbing"
        ])->assertStatus(201);

        $this->assertDatabaseHas('complaints', [
            'title'                 => 'Vague',
            'complaint_category_id' => null,
        ]);
    }

    public function test_an_exact_category_name_still_matches(): void
    {
        $society  = $this->makeSociety();
        $flat     = $this->makeFlat($society, $this->makeBuilding($society));
        $user     = $this->makeResidentUser($society, $flat);
        $category = ComplaintCategory::create([
            'society_id' => $society->id, 'name' => 'Plumbing', 'status' => 'active',
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/complaints', [
            'title'       => 'Exact',
            'description' => 'Tap leaking',
            'category'    => 'plumbing', // different case
        ])->assertStatus(201);

        $this->assertDatabaseHas('complaints', [
            'title'                 => 'Exact',
            'complaint_category_id' => $category->id,
        ]);
    }

    public function test_a_category_from_another_society_is_never_matched(): void
    {
        $societyA = $this->makeSociety('A');
        $societyB = $this->makeSociety('B');
        $flatA    = $this->makeFlat($societyA, $this->makeBuilding($societyA));
        $userA    = $this->makeResidentUser($societyA, $flatA);

        ComplaintCategory::create([
            'society_id' => $societyB->id, 'name' => 'Plumbing', 'status' => 'active',
        ]);

        Sanctum::actingAs($userA);

        $this->postJson('/api/v1/complaints', [
            'title'       => 'Cross society',
            'description' => 'Tap leaking',
            'category'    => 'Plumbing',
        ])->assertStatus(201);

        $this->assertDatabaseHas('complaints', [
            'title'                 => 'Cross society',
            'complaint_category_id' => null,
        ]);
    }
}
