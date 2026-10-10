<?php

namespace Tests\Feature;

use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Staff dashboard filtered events on a column that does not exist
 * (events.event_date; the table has start_date/end_date). SQLite reads an
 * unknown quoted identifier as a string, so the page rendered and always
 * counted zero; MariaDB threw and the whole dashboard 500'd for every Staff
 * user. Asserting the real count (not just "renders") is what catches it on
 * both engines.
 */
class StaffDashboardEventsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        \Carbon\Carbon::setTestNow();
        parent::tearDown();
    }

    private function event(int $societyId, string $name, $start, $end, string $status = 'pending'): Event
    {
        return Event::create([
            'society_id' => $societyId, 'event_name' => $name, 'location' => 'Hall', 'description' => 'x',
            'start_date' => $start, 'end_date' => $end, 'status' => $status, 'is_role_based' => false,
        ]);
    }

    public function test_staff_dashboard_counts_only_scheduled_events_happening_today_in_their_society(): void
    {
        // Frozen at noon society time: the fixtures are built from now() +/- hours, so the test used to change its answer in the last hour of the UTC day.
        \Carbon\Carbon::setTestNow(\Carbon\Carbon::parse('2026-10-10 06:30:00', 'UTC'));   // 12:00 IST
        $a = $this->makeSociety('A');
        $b = $this->makeSociety('B');
        $staff = $this->makeUserWithRole($a, 'Staff');

        $this->event($a->id, 'Today', now()->startOfDay()->addHours(10), now()->startOfDay()->addHours(12));
        $this->event($a->id, 'Multi-day, in progress', now()->subDay(), now()->addDay());
        $this->event($a->id, 'Tomorrow', now()->addDay(), now()->addDay()->addHour());
        $this->event($a->id, 'Yesterday', now()->subDay(), now()->subDay()->addHour());
        $this->event($a->id, 'Cancelled today', now()->startOfDay()->addHours(10), now()->startOfDay()->addHours(11), 'cancelled');
        $this->event($b->id, 'Other society today', now()->startOfDay()->addHours(10), now()->startOfDay()->addHours(11));

        $response = $this->actingAs($staff)->get('/dashboard');

        $response->assertOk();
        $this->assertSame(2, $response->viewData('stats')['todays_events']);
    }

    public function test_after_midnight_in_the_society_todays_events_follow_the_society_calendar_not_utc(): void
    {
        // 20:00 UTC on the 10th is 01:30 on the 11th in India: "today" for the society is the 11th.
        \Carbon\Carbon::setTestNow(\Carbon\Carbon::parse('2026-10-10 20:00:00', 'UTC'));
        $a = $this->makeSociety('A');
        $staff = $this->makeUserWithRole($a, 'Staff');

        // Phase 1: only an event that is over by the society's calendar (10th, 10:00 IST) - it is NOT today's.
        $yesterday = $this->event($a->id, 'Society-yesterday (10th, 10:00 IST)', '2026-10-10 04:30:00', '2026-10-10 06:30:00');
        $this->assertSame(0, $this->actingAs($staff)->get('/dashboard')->assertOk()->viewData('stats')['todays_events'], 'the society-yesterday event is not today');

        // Phase 2: add the event that is on the society's 11th - now exactly one counts, and it is the new one.
        $this->event($a->id, 'Society-today (11th, 10:00 IST)', '2026-10-11 04:30:00', '2026-10-11 06:30:00');
        $this->assertSame(1, $this->actingAs($staff)->get('/dashboard')->assertOk()->viewData('stats')['todays_events']);
        $this->assertNotNull($yesterday->id);
    }
}
