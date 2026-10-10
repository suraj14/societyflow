<?php

namespace Tests\Feature;

use App\Models\Facility;
use App\Models\FacilityBooking;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * GET /api/v1/facility-bookings used to cost 2 extra queries per row.
 *
 * FacilityApiController::hasStarted() called SocietyClock::now($booking->society_id)
 * twice per booking, and SocietyClock::timezoneFor() runs a fresh
 * `select id, timezone from societies` for every int id. Every row on a page
 * shares one society, so a 20-row page paid 40 identical queries (measured
 * 9 -> 44 against the 2-row baseline). "Now" is now resolved once per request.
 *
 * QueryPerformanceTest only compares 2 rows with 20. These cases pin the same
 * guarantee at 0, 1, 5 and 20 rows, and pin the behaviour the refactor had to
 * keep identical: the response shape, can_cancel's society-local clock,
 * pagination, and who can see which bookings.
 */
class FacilityBookingListQueryTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function resident($society, $flat): User
    {
        $user = $this->makeResidentUser($society, $flat);
        $user->assignRole($this->makeRole('Tenant'));

        return $user;
    }

    private function facility($society, string $name = 'Gym'): Facility
    {
        return Facility::create([
            'society_id' => $society->id, 'name' => $name, 'type' => 'gym', 'status' => 'active',
        ]);
    }

    private function booking($society, $facility, $flat, User $user, int $daysAhead, array $over = []): FacilityBooking
    {
        return FacilityBooking::create(array_merge([
            'society_id'   => $society->id,
            'facility_id'  => $facility->id,
            'user_id'      => $user->id,
            'flat_id'      => $flat->id,
            'booking_date' => now()->addDays($daysAhead)->toDateString(),
            'start_time'   => '10:00',
            'end_time'     => '11:00',
            'status'       => 'pending',
        ], $over));
    }

    private function queries(callable $fn): int
    {
        $count = 0;
        DB::listen(function () use (&$count) {
            $count++;
        });
        $fn();

        return $count;
    }

    private function world(): array
    {
        $society  = $this->makeSociety();
        $flat     = $this->makeFlat($society, $this->makeBuilding($society));
        $user     = $this->resident($society, $flat);

        return [$society, $flat, $user, $this->facility($society)];
    }

    // ------------------------------------------------------------ query count

    public function test_query_count_is_flat_at_zero_one_five_and_twenty_rows(): void
    {
        [$society, $flat, $user, $facility] = $this->world();
        Sanctum::actingAs($user);

        // Warm-up request: absorbs one-off bootstrap queries so every
        // measurement below is a like-for-like steady-state request.
        $this->getJson('/api/v1/facility-bookings')->assertOk();

        $counts = [];
        $made   = 0;
        foreach ([0, 1, 5, 20] as $rows) {
            for (; $made < $rows; $made++) {
                $this->booking($society, $facility, $flat, $user, $made + 1);
            }
            $counts[$rows] = $this->queries(fn () => $this->getJson('/api/v1/facility-bookings')->assertOk());
        }

        // 0 rows skips the eager loads, so allow that single step; after the
        // first row the cost must not move at all as rows grow.
        $this->assertSame($counts[1], $counts[5], 'query count moved between 1 and 5 rows: ' . json_encode($counts));
        $this->assertSame($counts[1], $counts[20], 'query count moved between 1 and 20 rows: ' . json_encode($counts));
        $this->assertLessThanOrEqual($counts[1], $counts[0] + 3, 'empty list should not cost more than a populated one: ' . json_encode($counts));
        $this->assertLessThanOrEqual(12, $counts[20], 'absolute ceiling for a 20-row page: ' . json_encode($counts));
    }

    // ------------------------------------------------------- shape / pagination

    public function test_an_empty_list_is_a_valid_empty_page(): void
    {
        [, , $user] = $this->world();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/facility-bookings')
            ->assertOk()
            ->assertJson(['success' => true, 'data' => [], 'meta' => ['current_page' => 1, 'last_page' => 1, 'total' => 0]]);
    }

    public function test_a_single_booking_keeps_the_exact_response_shape(): void
    {
        [$society, $flat, $user, $facility] = $this->world();
        $booking = $this->booking($society, $facility, $flat, $user, 3, ['purpose' => 'Birthday', 'expected_guests' => 12]);
        Sanctum::actingAs($user);

        $res = $this->getJson('/api/v1/facility-bookings')->assertOk();

        $row = $res->json('data.0');
        $this->assertSame(
            ['id', 'booking_number', 'facility', 'property', 'booking_date', 'start_time', 'end_time', 'purpose',
             'expected_guests', 'amount', 'payment_status', 'status', 'admin_notes', 'can_cancel', 'created_at'],
            array_keys($row)
        );
        $this->assertSame($booking->id, $row['id']);
        $this->assertSame(['id' => $facility->id, 'name' => 'Gym'], $row['facility']);
        $this->assertSame($flat->flat_number, $row['property']);
        $this->assertSame('10:00', $row['start_time']);
        $this->assertSame('11:00', $row['end_time']);
        $this->assertSame(12, $row['expected_guests']);
        $this->assertSame('pending', $row['status']);
        $this->assertTrue($row['can_cancel']);
        $this->assertSame(1, $res->json('meta.total'));
    }

    public function test_pagination_splits_at_twenty_newest_first(): void
    {
        [$society, $flat, $user, $facility] = $this->world();
        for ($i = 1; $i <= 25; $i++) {
            $this->booking($society, $facility, $flat, $user, $i);
        }
        Sanctum::actingAs($user);

        $page1 = $this->getJson('/api/v1/facility-bookings')->assertOk();
        $page2 = $this->getJson('/api/v1/facility-bookings?page=2')->assertOk();

        $this->assertCount(20, $page1->json('data'));
        $this->assertCount(5, $page2->json('data'));
        $this->assertSame(['current_page' => 1, 'last_page' => 2, 'total' => 25], $page1->json('meta'));
        $this->assertSame(2, $page2->json('meta.current_page'));

        $dates = array_merge(array_column($page1->json('data'), 'booking_date'), array_column($page2->json('data'), 'booking_date'));
        $sorted = $dates;
        rsort($sorted);
        $this->assertSame($sorted, $dates, 'bookings must stay ordered newest booking_date first across pages');
        $this->assertCount(25, array_unique($dates));
    }

    // ----------------------------------------------- can_cancel (the refactor)

    public function test_can_cancel_still_reads_the_societys_local_clock(): void
    {
        [$society, $flat, $user, $facility] = $this->world();
        DB::table('societies')->where('id', $society->id)->update(['timezone' => 'Asia/Kolkata']);

        // 12:00 UTC is 17:30 in Kolkata. A 17:00 start has begun, 18:00 has not.
        Carbon::setTestNow(Carbon::parse('2026-10-09 12:00:00', 'UTC'));
        $started  = $this->booking($society, $facility, $flat, $user, 0, ['booking_date' => '2026-10-09', 'start_time' => '17:00', 'end_time' => '18:00']);
        $upcoming = $this->booking($society, $facility, $flat, $user, 0, ['booking_date' => '2026-10-09', 'start_time' => '18:00', 'end_time' => '19:00']);
        $future   = $this->booking($society, $facility, $flat, $user, 0, ['booking_date' => '2026-10-10', 'start_time' => '09:00', 'end_time' => '10:00']);
        $done     = $this->booking($society, $facility, $flat, $user, 0, ['booking_date' => '2026-10-12', 'start_time' => '09:00', 'end_time' => '10:00', 'status' => 'cancelled']);
        Sanctum::actingAs($user);

        $byId = collect($this->getJson('/api/v1/facility-bookings')->assertOk()->json('data'))->keyBy('id');

        $this->assertFalse($byId[$started->id]['can_cancel'], 'a booking whose start has passed locally cannot be cancelled');
        $this->assertTrue($byId[$upcoming->id]['can_cancel'], 'later today (local) is still cancellable');
        $this->assertTrue($byId[$future->id]['can_cancel']);
        $this->assertFalse($byId[$done->id]['can_cancel'], 'a cancelled booking is never cancellable');
    }

    public function test_booking_and_cancelling_still_work_through_the_shared_clock(): void
    {
        [$society, $flat, $user, $facility] = $this->world();
        Sanctum::actingAs($user);

        $id = $this->postJson('/api/v1/facility-bookings', [
            'facility_id'  => $facility->id,
            'booking_date' => now()->addDay()->toDateString(),
            'start_time'   => '10:00',
            'end_time'     => '11:00',
        ])->assertStatus(201)->assertJsonPath('data.can_cancel', true)->json('data.id');

        $this->postJson("/api/v1/facility-bookings/{$id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.can_cancel', false);
    }

    public function test_a_booking_that_has_started_cannot_be_cancelled(): void
    {
        [$society, $flat, $user, $facility] = $this->world();
        DB::table('societies')->where('id', $society->id)->update(['timezone' => 'Asia/Kolkata']);
        Carbon::setTestNow(Carbon::parse('2026-10-09 12:00:00', 'UTC'));
        $started = $this->booking($society, $facility, $flat, $user, 0, ['booking_date' => '2026-10-09', 'start_time' => '17:00', 'end_time' => '18:00']);
        Sanctum::actingAs($user);

        $this->postJson("/api/v1/facility-bookings/{$started->id}/cancel")
            ->assertStatus(422)
            ->assertJsonPath('message', 'This booking has already started.');
        $this->assertSame('pending', $started->fresh()->status);
    }

    // ------------------------------------------------------ isolation / auth

    public function test_the_list_shows_only_the_callers_own_bookings(): void
    {
        [$society, $flat, $user, $facility] = $this->world();
        $neighbour = $this->resident($society, $flat);
        $mine      = $this->booking($society, $facility, $flat, $user, 1);
        $theirs    = $this->booking($society, $facility, $flat, $neighbour, 2);

        $societyB  = $this->makeSociety('Society B');
        $flatB     = $this->makeFlat($societyB, $this->makeBuilding($societyB));
        $userB     = $this->resident($societyB, $flatB);
        $foreign   = $this->booking($societyB, $this->facility($societyB, 'B Gym'), $flatB, $userB, 3);

        Sanctum::actingAs($user);
        $ids = collect($this->getJson('/api/v1/facility-bookings')->assertOk()->json('data'))->pluck('id');

        $this->assertSame([$mine->id], $ids->all());
        $this->assertFalse($ids->contains($theirs->id), 'a same-society neighbour\'s booking leaked');
        $this->assertFalse($ids->contains($foreign->id), 'another society\'s booking leaked');

        Sanctum::actingAs($userB);
        $this->assertSame([$foreign->id], collect($this->getJson('/api/v1/facility-bookings')->json('data'))->pluck('id')->all());
    }

    public function test_the_list_requires_authentication(): void
    {
        $this->getJson('/api/v1/facility-bookings')->assertStatus(401);
    }

    public function test_cancelling_another_residents_booking_is_still_a_404(): void
    {
        [$society, $flat, $user, $facility] = $this->world();
        $neighbour = $this->resident($society, $flat);
        $theirs    = $this->booking($society, $facility, $flat, $neighbour, 2);

        Sanctum::actingAs($user);
        $this->postJson("/api/v1/facility-bookings/{$theirs->id}/cancel")->assertStatus(404);
        $this->assertSame('pending', $theirs->fresh()->status);
    }
}
