<?php

namespace Tests\Feature;

use App\Models\Building;
use App\Models\Facility;
use App\Models\FacilityBooking;
use App\Models\Flat;
use App\Models\Society;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Mobile facility booking. The app used to offer a hard-coded 06:00-22:00
 * grid for every facility, show nothing about what was already taken, write a
 * booking amount of 0 whatever the facility charged, ignore its opening hours,
 * available days, advance window and maximum length, and let any signed-in
 * role (a Guard, a Staff member) call the booking endpoints. The booking list
 * also leaked Carbon casts as ISO timestamps ("2026-10-08T03:30:00Z") where
 * the app expected "09:30".
 *
 * Fixed "now" is Saturday 2026-10-10 10:00 IST (04:30 UTC).
 */
class FacilityBookingApiTest extends TestCase
{
    use RefreshDatabase;

    private const TODAY = '2026-10-10';

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-10 04:30:00', 'UTC'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function facility(Society $society, array $overrides = []): Facility
    {
        return Facility::create(array_merge([
            'society_id'           => $society->id,
            'name'                 => 'Clubhouse',
            'type'                 => 'clubhouse',
            'booking_charge'       => 500,
            'opening_time'         => '08:00:00',
            'closing_time'         => '20:00:00',
            'max_booking_hours'    => 3,
            'advance_booking_days' => 14,
            'requires_approval'    => true,
            'status'               => 'active',
        ], $overrides));
    }

    /** An owner living in a unit of $society. */
    private function owner(Society $society, ?Flat $flat = null): User
    {
        $building = Building::where('society_id', $society->id)->first() ?? $this->makeBuilding($society);
        $flat ??= $this->makeFlat($society, $building, 'F' . substr(uniqid(), -6));
        $user = $this->makeResidentUser($society, $flat);
        $this->makeRole('Apartment Owner');
        $user->assignRole('Apartment Owner');

        return $user;
    }

    private function payload(Facility $f, array $over = []): array
    {
        return array_merge([
            'facility_id'  => $f->id,
            'booking_date' => '2026-10-12',
            'start_time'   => '10:00:00',
            'end_time'     => '12:00:00',
            'purpose'      => 'Birthday',
        ], $over);
    }

    // ── booking ─────────────────────────────────────────────────────────────

    public function test_an_owner_books_a_slot_with_the_facilitys_charge_and_their_own_unit(): void
    {
        $society = $this->makeSociety();
        $owner = $this->owner($society);
        $facility = $this->facility($society);
        $admin = $this->makeUserWithRole($society, 'Admin');

        Sanctum::actingAs($owner);
        $r = $this->postJson('/api/v1/facility-bookings', $this->payload($facility))->assertCreated();

        $r->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.amount', 500)
            ->assertJsonPath('data.start_time', '10:00')
            ->assertJsonPath('data.end_time', '12:00')
            ->assertJsonPath('data.facility.name', 'Clubhouse');
        $this->assertNotEmpty($r->json('data.booking_number'));

        $booking = FacilityBooking::firstOrFail();
        $this->assertSame($owner->id, $booking->user_id);
        $this->assertSame($society->id, $booking->society_id);
        $this->assertNotNull($booking->flat_id);
        $this->assertSame('500.00', (string) $booking->booking_amount);

        $this->assertSame(['Facility booking request'], $admin->fresh()->notifications()->pluck('data')->map(fn ($d) => $d['title'])->all());
    }

    public function test_a_tenant_books_through_their_tenancy(): void
    {
        $society = $this->makeSociety();
        $flat = $this->makeFlat($society, $this->makeBuilding($society));
        $tenant = $this->makeUserWithRole($society, 'Tenant');
        Tenant::create(['society_id' => $society->id, 'flat_id' => $flat->id, 'user_id' => $tenant->id, 'name' => 'T', 'email' => 't' . uniqid() . '@t.local', 'phone' => '9000000099', 'move_in_date' => now(), 'status' => 'active']);
        $facility = $this->facility($society);

        Sanctum::actingAs($tenant);
        $this->postJson('/api/v1/facility-bookings', $this->payload($facility))->assertCreated();

        $this->assertSame($flat->id, FacilityBooking::firstOrFail()->flat_id);
    }

    public function test_a_facility_that_does_not_need_approval_confirms_immediately(): void
    {
        $society = $this->makeSociety();
        $owner = $this->owner($society);
        $facility = $this->facility($society, ['requires_approval' => false]);

        Sanctum::actingAs($owner);
        $this->postJson('/api/v1/facility-bookings', $this->payload($facility))
            ->assertCreated()->assertJsonPath('data.status', 'approved');
        $this->assertNotNull(FacilityBooking::firstOrFail()->approved_at);
    }

    public function test_a_resident_with_no_unit_is_told_to_contact_the_office(): void
    {
        $society = $this->makeSociety();
        $user = $this->makeUserWithRole($society, 'Tenant');
        $facility = $this->facility($society);

        Sanctum::actingAs($user);
        $this->postJson('/api/v1/facility-bookings', $this->payload($facility))
            ->assertStatus(422)->assertJsonPath('success', false);
        $this->assertSame(0, FacilityBooking::count());
    }

    // ── who may book ────────────────────────────────────────────────────────

    /** @dataProvider nonResidentRoles */
    public function test_gate_finance_and_office_accounts_cannot_book(string $role): void
    {
        $society = $this->makeSociety();
        $user = $this->makeUserWithRole($society, $role);
        $facility = $this->facility($society);

        Sanctum::actingAs($user);
        $this->postJson('/api/v1/facility-bookings', $this->payload($facility))->assertForbidden();
        $this->assertSame(0, FacilityBooking::count());
    }

    public static function nonResidentRoles(): array
    {
        return ['guard' => ['Guard'], 'staff' => ['Staff'], 'accountant' => ['Accountant'], 'admin' => ['Admin']];
    }

    public function test_a_facility_of_another_society_is_not_found(): void
    {
        $mine = $this->makeSociety('Mine');
        $other = $this->makeSociety('Other');
        $owner = $this->owner($mine);
        $theirs = $this->facility($other);

        Sanctum::actingAs($owner);
        $this->postJson('/api/v1/facility-bookings', $this->payload($theirs))->assertNotFound();
        $this->getJson("/api/v1/facilities/{$theirs->id}/availability?date=2026-10-12")->assertNotFound();
        $this->assertSame(0, FacilityBooking::count());
    }

    public function test_a_resident_cannot_book_for_a_neighbours_unit(): void
    {
        $society = $this->makeSociety();
        $building = $this->makeBuilding($society);
        $mine = $this->owner($society, $this->makeFlat($society, $building, '101'));
        $neighbour = $this->makeFlat($society, $building, '102');
        $facility = $this->facility($society);

        Sanctum::actingAs($mine);
        $this->postJson('/api/v1/facility-bookings', $this->payload($facility, ['flat_id' => $neighbour->id]))->assertForbidden();
        $this->assertSame(0, FacilityBooking::count());
    }

    // ── the facility's rules ────────────────────────────────────────────────

    /** @dataProvider brokenRules */
    public function test_a_request_that_breaks_the_facilitys_rules_is_refused_with_the_reason(array $override, string $message): void
    {
        $society = $this->makeSociety();
        $owner = $this->owner($society);
        $facility = $this->facility($society, ['available_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday']]);

        Sanctum::actingAs($owner);
        $r = $this->postJson('/api/v1/facility-bookings', $this->payload($facility, $override))->assertStatus(422);

        $this->assertStringContainsString($message, $r->json('message'));
        $this->assertSame(0, FacilityBooking::count());
    }

    public static function brokenRules(): array
    {
        return [
            'before opening'      => [['start_time' => '07:00:00', 'end_time' => '09:00:00'], 'open from 8:00 AM to 8:00 PM'],
            'after closing'       => [['start_time' => '19:00:00', 'end_time' => '21:00:00'], 'open from 8:00 AM to 8:00 PM'],
            'longer than allowed' => [['start_time' => '09:00:00', 'end_time' => '13:00:00'], 'at most 3 hours'],
            'too far ahead'       => [['booking_date' => '2026-10-30'], 'up to 14 days in advance'],
            'closed that weekday' => [['booking_date' => '2026-10-11'], 'not available on Sundays'],
            'in the past'         => [['booking_date' => '2026-10-09'], 'today or a later date'],
            'earlier today'       => [['booking_date' => self::TODAY, 'start_time' => '09:00:00', 'end_time' => '10:00:00'], 'already passed'],
        ];
    }

    public function test_a_later_slot_today_can_still_be_booked(): void
    {
        $society = $this->makeSociety();
        $owner = $this->owner($society);
        $facility = $this->facility($society);

        Sanctum::actingAs($owner);
        $this->postJson('/api/v1/facility-bookings', $this->payload($facility, ['booking_date' => self::TODAY, 'start_time' => '15:00:00', 'end_time' => '16:00:00']))->assertCreated();
    }

    public function test_the_end_must_be_after_the_start(): void
    {
        $society = $this->makeSociety();
        $owner = $this->owner($society);
        $facility = $this->facility($society);

        Sanctum::actingAs($owner);
        $this->postJson('/api/v1/facility-bookings', $this->payload($facility, ['start_time' => '12:00:00', 'end_time' => '10:00:00']))->assertStatus(422);
    }

    // ── no double booking ───────────────────────────────────────────────────

    public function test_an_overlapping_request_is_refused_but_back_to_back_bookings_are_fine(): void
    {
        $society = $this->makeSociety();
        $owner = $this->owner($society);
        $facility = $this->facility($society);
        // Stored the way the web form stores them ("10:00", no seconds): the
        // old string comparison called an adjacent 11:00:00 booking a clash.
        FacilityBooking::create([
            'society_id' => $society->id, 'facility_id' => $facility->id, 'user_id' => $owner->id, 'flat_id' => null,
            'booking_date' => '2026-10-12', 'start_time' => '10:00', 'end_time' => '11:00', 'status' => 'approved',
        ]);

        Sanctum::actingAs($owner);
        $this->postJson('/api/v1/facility-bookings', $this->payload($facility, ['start_time' => '10:30:00', 'end_time' => '11:30:00']))
            ->assertStatus(422)->assertJsonPath('message', 'This time slot is already booked. Please choose a different time.');
        $this->postJson('/api/v1/facility-bookings', $this->payload($facility, ['start_time' => '09:00:00', 'end_time' => '10:00:00']))->assertCreated();
        $this->postJson('/api/v1/facility-bookings', $this->payload($facility, ['start_time' => '11:00:00', 'end_time' => '12:00:00']))->assertCreated();
    }

    public function test_a_cancelled_or_rejected_booking_frees_its_slot(): void
    {
        $society = $this->makeSociety();
        $owner = $this->owner($society);
        $facility = $this->facility($society);

        Sanctum::actingAs($owner);
        $id = $this->postJson('/api/v1/facility-bookings', $this->payload($facility))->assertCreated()->json('data.id');
        $this->postJson('/api/v1/facility-bookings', $this->payload($facility))->assertStatus(422);

        $this->postJson("/api/v1/facility-bookings/{$id}/cancel")->assertOk()->assertJsonPath('data.status', 'cancelled');

        $this->postJson('/api/v1/facility-bookings', $this->payload($facility))->assertCreated();
    }

    // ── availability ────────────────────────────────────────────────────────

    public function test_availability_shows_the_taken_hours_and_how_long_each_start_can_run(): void
    {
        $society = $this->makeSociety();
        $owner = $this->owner($society);
        $facility = $this->facility($society);
        FacilityBooking::create([
            'society_id' => $society->id, 'facility_id' => $facility->id, 'user_id' => $owner->id, 'flat_id' => null,
            'booking_date' => '2026-10-12', 'start_time' => '10:00:00', 'end_time' => '12:00:00', 'status' => 'pending',
        ]);
        FacilityBooking::create([ // a cancelled one does not hold its hours
            'society_id' => $society->id, 'facility_id' => $facility->id, 'user_id' => $owner->id, 'flat_id' => null,
            'booking_date' => '2026-10-12', 'start_time' => '14:00:00', 'end_time' => '15:00:00', 'status' => 'cancelled',
        ]);

        Sanctum::actingAs($owner);
        $r = $this->getJson("/api/v1/facilities/{$facility->id}/availability?date=2026-10-12")->assertOk();

        $slots = collect($r->json('data.slots'))->keyBy('start');
        $this->assertSame('08:00', $r->json('data.open'));
        $this->assertSame('20:00', $r->json('data.close'));
        $this->assertCount(12, $slots, '08:00 to 20:00 in hourly starts');
        $this->assertTrue($r->json('data.bookable'));
        $this->assertSame(500.0, (float) $r->json('data.charge'));
        $this->assertTrue($r->json('data.requires_approval'));

        $this->assertSame(2, $slots['08:00']['max_hours'], 'runs into the 10:00 booking');
        $this->assertSame(1, $slots['09:00']['max_hours']);
        $this->assertFalse($slots['10:00']['available']);
        $this->assertSame('booked', $slots['10:00']['reason']);
        $this->assertSame('booked', $slots['11:00']['reason']);
        $this->assertSame(3, $slots['12:00']['max_hours'], 'capped by the facility maximum');
        $this->assertTrue($slots['14:00']['available'], 'the cancelled booking is free again');
    }

    public function test_availability_for_today_hides_hours_that_have_passed(): void
    {
        $society = $this->makeSociety();
        $owner = $this->owner($society);
        $facility = $this->facility($society);

        Sanctum::actingAs($owner);
        $slots = collect($this->getJson("/api/v1/facilities/{$facility->id}/availability?date=" . self::TODAY)->assertOk()->json('data.slots'))->keyBy('start');

        $this->assertSame('past', $slots['09:00']['reason']);
        $this->assertSame('past', $slots['10:00']['reason'], 'it is already 10:00');
        $this->assertTrue($slots['11:00']['available']);
    }

    public function test_availability_explains_a_closed_day(): void
    {
        $society = $this->makeSociety();
        $owner = $this->owner($society);
        $facility = $this->facility($society, ['available_days' => ['monday']]);

        Sanctum::actingAs($owner);
        $r = $this->getJson("/api/v1/facilities/{$facility->id}/availability?date=2026-10-13")->assertOk();
        $this->assertFalse($r->json('data.bookable'));
        $this->assertStringContainsString('not available on Tuesdays', $r->json('data.message'));
        $this->assertSame([], array_filter($r->json('data.slots'), fn ($s) => $s['available']));
    }

    public function test_availability_needs_a_date(): void
    {
        $society = $this->makeSociety();
        $owner = $this->owner($society);
        $facility = $this->facility($society);

        Sanctum::actingAs($owner);
        $this->getJson("/api/v1/facilities/{$facility->id}/availability")->assertStatus(422);
        $this->getJson("/api/v1/facilities/{$facility->id}/availability?date=tomorrow")->assertStatus(422);
    }

    // ── my bookings ─────────────────────────────────────────────────────────

    public function test_my_bookings_lists_only_my_own_with_plain_clock_times(): void
    {
        $society = $this->makeSociety();
        $owner = $this->owner($society);
        $other = $this->owner($society);
        $facility = $this->facility($society);

        Sanctum::actingAs($other);
        $this->postJson('/api/v1/facility-bookings', $this->payload($facility, ['start_time' => '15:00:00', 'end_time' => '16:00:00']))->assertCreated();
        Sanctum::actingAs($owner);
        $this->postJson('/api/v1/facility-bookings', $this->payload($facility))->assertCreated();

        $r = $this->getJson('/api/v1/facility-bookings')->assertOk();

        $this->assertCount(1, $r->json('data'));
        $row = $r->json('data.0');
        $this->assertSame('10:00', $row['start_time'], 'not an ISO timestamp');
        $this->assertSame('12:00', $row['end_time']);
        $this->assertSame('2026-10-12', $row['booking_date']);
        $this->assertTrue($row['can_cancel']);
    }

    public function test_a_resident_cannot_cancel_someone_elses_booking(): void
    {
        $society = $this->makeSociety();
        $owner = $this->owner($society);
        $other = $this->owner($society);
        $facility = $this->facility($society);

        Sanctum::actingAs($owner);
        $id = $this->postJson('/api/v1/facility-bookings', $this->payload($facility))->json('data.id');

        Sanctum::actingAs($other);
        $this->postJson("/api/v1/facility-bookings/{$id}/cancel")->assertNotFound();
        $this->assertSame('pending', FacilityBooking::findOrFail($id)->status);
    }

    public function test_a_booking_that_has_started_cannot_be_cancelled_and_the_office_hears_of_a_cancellation(): void
    {
        $society = $this->makeSociety();
        $owner = $this->owner($society);
        $admin = $this->makeUserWithRole($society, 'Admin');
        $facility = $this->facility($society);

        Sanctum::actingAs($owner);
        $later = $this->postJson('/api/v1/facility-bookings', $this->payload($facility, ['booking_date' => self::TODAY, 'start_time' => '15:00:00', 'end_time' => '16:00:00']))->json('data.id');
        $started = FacilityBooking::create([
            'society_id' => $society->id, 'facility_id' => $facility->id, 'user_id' => $owner->id, 'flat_id' => null,
            'booking_date' => self::TODAY, 'start_time' => '09:00:00', 'end_time' => '11:00:00', 'status' => 'approved',
        ]);

        $this->postJson("/api/v1/facility-bookings/{$started->id}/cancel")->assertStatus(422);
        $this->assertSame('approved', $started->fresh()->status);

        $this->postJson("/api/v1/facility-bookings/{$later}/cancel", ['reason' => 'Plans changed'])->assertOk();
        $this->assertSame('Plans changed', FacilityBooking::findOrFail($later)->cancellation_reason);
        $this->assertContains('Facility booking cancelled', $admin->fresh()->notifications()->pluck('data')->map(fn ($d) => $d['title'])->all());
    }

    // ── the web form, which shares the rules ────────────────────────────────

    public function test_the_web_form_refuses_another_societys_facility(): void
    {
        $mine = $this->makeSociety('Mine');
        $other = $this->makeSociety('Other');
        $flat = $this->makeFlat($mine, $this->makeBuilding($mine));
        $owner = $this->makeResidentUser($mine, $flat);
        $this->makeRole('Apartment Owner');
        $owner->assignRole('Apartment Owner');
        $theirs = $this->facility($other);

        $this->actingAs($owner)->post(route('facility-bookings.store'), [
            'facility_id' => $theirs->id, 'flat_id' => $flat->id,
            'booking_date' => '2026-10-12', 'start_time' => '10:00', 'end_time' => '11:00',
        ])->assertSessionHas('error');

        $this->assertSame(0, FacilityBooking::count(), 'a forged facility_id must never book another society\'s facility');
    }

    public function test_the_web_form_applies_the_facilitys_hours(): void
    {
        $society = $this->makeSociety();
        $flat = $this->makeFlat($society, $this->makeBuilding($society));
        $owner = $this->makeResidentUser($society, $flat);
        $this->makeRole('Apartment Owner');
        $owner->assignRole('Apartment Owner');
        $facility = $this->facility($society);

        $this->actingAs($owner)->post(route('facility-bookings.store'), [
            'facility_id' => $facility->id, 'flat_id' => $flat->id,
            'booking_date' => '2026-10-12', 'start_time' => '05:00', 'end_time' => '06:00',
        ])->assertSessionHas('error');
        $this->assertSame(0, FacilityBooking::count());

        $this->actingAs($owner)->post(route('facility-bookings.store'), [
            'facility_id' => $facility->id, 'flat_id' => $flat->id,
            'booking_date' => '2026-10-12', 'start_time' => '10:00', 'end_time' => '11:00',
        ])->assertRedirect(route('facility-bookings.index'));
        $this->assertSame(1, FacilityBooking::count());
    }

    public function test_the_web_edit_form_cannot_move_a_booking_to_a_neighbours_unit(): void
    {
        $society = $this->makeSociety();
        $building = $this->makeBuilding($society);
        $mine = $this->makeFlat($society, $building, '101');
        $neighbour = $this->makeFlat($society, $building, '102');
        $owner = $this->makeResidentUser($society, $mine);
        $this->makeRole('Apartment Owner');
        $owner->assignRole('Apartment Owner');
        $facility = $this->facility($society);
        $booking = FacilityBooking::create([
            'society_id' => $society->id, 'facility_id' => $facility->id, 'user_id' => $owner->id, 'flat_id' => $mine->id,
            'booking_date' => '2026-10-12', 'start_time' => '10:00', 'end_time' => '11:00', 'status' => 'pending',
        ]);

        $this->actingAs($owner)->put(route('facility-bookings.update', $booking), [
            'facility_id' => $facility->id, 'flat_id' => $neighbour->id,
            'booking_date' => '2026-10-12', 'start_time' => '10:00', 'end_time' => '11:00',
        ])->assertSessionHas('error');

        $this->assertSame($mine->id, $booking->fresh()->flat_id);
    }
}
