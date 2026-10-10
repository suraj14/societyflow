<?php

namespace Tests\Feature;

use App\Models\Facility;
use App\Models\FacilityBooking;
use App\Models\Society;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The advance-booking window and the "not in the past" rule, on BOTH channels (web form and mobile API), at the
 * boundaries and across the society's timezone. The rule is the facility's own `advance_booking_days`
 * (default 30) - it is not invented here - and lives in FacilityBookingService, so the two channels must agree.
 *
 * Found by the web audit: the web form validated `after_or_equal:today` in the *application* timezone, which
 * refuses a perfectly valid same-day booking for a society whose local date is behind UTC's.
 */
class FacilityAdvanceWindowTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function facility(Society $s, array $over = []): Facility
    {
        return Facility::create(array_merge([
            'society_id' => $s->id, 'name' => 'Clubhouse', 'type' => 'clubhouse', 'booking_charge' => 0,
            'opening_time' => '08:00:00', 'closing_time' => '20:00:00', 'max_booking_hours' => 3,
            'advance_booking_days' => 14, 'requires_approval' => true, 'status' => 'active',
        ], $over));
    }

    private function owner(Society $s): User
    {
        $flat = $this->makeFlat($s, $this->makeBuilding($s), 'F' . substr(uniqid(), -6));
        $user = $this->makeResidentUser($s, $flat);
        $this->makeRole('Apartment Owner');
        $user->assignRole('Apartment Owner');

        return $user;
    }

    /** @return array{0: int, 1: string} [status, message] of a booking attempt on the given channel */
    private function attempt(string $channel, User $user, Facility $f, string $date, string $start = '10:00', string $end = '11:00'): array
    {
        if ($channel === 'api') {
            Sanctum::actingAs($user);
            $r = $this->postJson('/api/v1/facility-bookings', ['facility_id' => $f->id, 'booking_date' => $date, 'start_time' => $start, 'end_time' => $end]);

            return [$r->getStatusCode(), (string) ($r->json('message') ?? '') . json_encode($r->json('errors') ?? [])];
        }

        $flatId = \App\Support\UserFlatResolver::flatIdsFor($user)[0];
        $r = $this->actingAs($user)->from(route('facility-bookings.create'))->post(route('facility-bookings.store'), [
            'facility_id' => $f->id, 'flat_id' => $flatId, 'booking_date' => $date, 'start_time' => $start, 'end_time' => $end,
        ]);
        $msg = (string) (session('error') ?? '') . json_encode(session('errors') ? session('errors')->getBag('default')->all() : []);

        return [$r->getStatusCode() === 302 && str_contains((string) $r->headers->get('Location'), 'facility-bookings/create') ? 422 : 201, $msg];
    }

    /** @dataProvider channels */
    public function test_the_window_boundaries_are_the_same_on_web_and_api(string $channel): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-10 04:30:00', 'UTC'));        // Sat 10:00 IST
        $s = $this->makeSociety('Alpha');
        $f = $this->facility($s);                                                // 14 days ahead
        $user = $this->owner($s);

        [$ok] = $this->attempt($channel, $user, $f, '2026-10-24');               // today + 14: the last allowed day
        $this->assertContains($ok, [200, 201], "$channel: the last day of the window is bookable");
        $this->assertSame(1, FacilityBooking::count());

        [$tooFar, $msg] = $this->attempt($channel, $user, $f, '2026-10-25');     // today + 15
        $this->assertNotContains($tooFar, [200, 201], "$channel: one day beyond the window is refused");
        $this->assertStringContainsString('14 days in advance', $msg);

        [$past, $pastMsg] = $this->attempt($channel, $user, $f, '2026-10-09');
        $this->assertNotContains($past, [200, 201], "$channel: yesterday is refused");
        $this->assertNotSame('', trim($pastMsg), "$channel: the refusal carries a message");

        $this->assertSame(1, FacilityBooking::count(), "$channel: refused attempts create nothing");
    }

    /** @dataProvider channels */
    public function test_today_is_bookable_and_garbage_dates_are_refused(string $channel): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-10 04:30:00', 'UTC'));
        $s = $this->makeSociety('Alpha');
        $f = $this->facility($s);
        $user = $this->owner($s);

        [$today] = $this->attempt($channel, $user, $f, '2026-10-10', '15:00', '16:00');
        $this->assertContains($today, [200, 201], "$channel: today is bookable");

        foreach (['not-a-date', '2026-13-45', ''] as $bad) {
            [$status] = $this->attempt($channel, $user, $f, $bad, '17:00', '18:00');
            $this->assertNotContains($status, [200, 201], "$channel: '$bad' is refused");
        }
        $this->assertSame(1, FacilityBooking::count());
    }

    /** @dataProvider channels */
    public function test_the_society_calendar_decides_today_when_it_differs_from_utc(string $channel): void
    {
        // 2026-10-10 20:00 UTC is already Sunday 2026-10-11 01:30 in India: yesterday-in-UTC is "the past" here.
        Carbon::setTestNow(Carbon::parse('2026-10-10 20:00:00', 'UTC'));
        $s = $this->makeSociety('Alpha');
        $f = $this->facility($s);
        $user = $this->owner($s);

        [$utcToday] = $this->attempt($channel, $user, $f, '2026-10-10');
        $this->assertNotContains($utcToday, [200, 201], "$channel: the society's yesterday is refused even though it is still 'today' in UTC");

        [$localToday] = $this->attempt($channel, $user, $f, '2026-10-11', '09:00', '10:00');
        $this->assertContains($localToday, [200, 201], "$channel: the society's today is accepted");
    }

    /** @dataProvider channels */
    public function test_a_society_behind_utc_can_still_book_its_own_today(string $channel): void
    {
        // 02:00 UTC on the 11th is 22:00 on the 10th in New York. That evening is "today" for the society.
        Carbon::setTestNow(Carbon::parse('2026-10-11 02:00:00', 'UTC'));
        $s = $this->makeSociety('Gotham');
        $s->forceFill(['timezone' => 'America/New_York'])->save();
        $f = $this->facility($s, ['opening_time' => '00:00:00', 'closing_time' => '00:00:00', 'max_booking_hours' => 2]);
        $user = $this->owner($s);

        [$status, $msg] = $this->attempt($channel, $user, $f, '2026-10-10', '23:00', '23:30');

        $this->assertContains($status, [200, 201], "$channel: refused a valid same-day booking: $msg");
    }

    public static function channels(): array
    {
        return ['web form' => ['web'], 'mobile API' => ['api']];
    }
}
