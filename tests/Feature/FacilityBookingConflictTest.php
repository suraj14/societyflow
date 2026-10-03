<?php

namespace Tests\Feature;

use App\Models\Facility;
use App\Models\FacilityBooking;
use App\Models\Flat;
use App\Models\Society;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FacilityBookingController::store() used to check for an overlapping
 * booking with a plain `->exists()` query, then insert with a separate
 * `create()` call - a check-then-create TOCTOU race with nothing serializing
 * the two steps. Two concurrent requests for the same slot could both pass
 * the overlap check before either had inserted its row, producing a double
 * booking (the brief's explicit P0 callout: "no double booking under
 * concurrent requests").
 *
 * The fix wraps the check and the insert in one DB::transaction() and takes
 * a lockForUpdate() on the facility row first, so a second transaction's
 * check always waits for the first to commit (or roll back) and therefore
 * always sees its row. True concurrent HTTP requests cannot be produced in
 * this single-process PHPUnit run against an in-memory SQLite database - PHP
 * itself is single-threaded here, so there is no way to have two requests
 * literally mid-flight at once. What IS verified: the sequential case (a
 * second, genuinely overlapping request after the first has committed) is
 * still correctly rejected, the transaction wrapping doesn't accidentally
 * break the happy path, and the exact overlap boundaries the original query
 * covered are unchanged. Real concurrent-request protection depends on the
 * lockForUpdate() being present and wrapped in a transaction, which the
 * source change makes structurally true rather than proven by this test
 * alone - documented here rather than overclaimed.
 */
class FacilityBookingConflictTest extends TestCase
{
    use RefreshDatabase;

    private function facilityIn(Society $society): Facility
    {
        return Facility::create([
            'society_id'     => $society->id,
            'name'           => 'Clubhouse',
            'type'           => 'clubhouse',
            'booking_charge' => 500,
            'status'         => 'active',
        ]);
    }

    public function test_overlapping_booking_is_rejected_after_the_first_is_committed(): void
    {
        $society  = $this->makeSociety('Society A');
        $facility = $this->facilityIn($society);
        $flat     = $this->makeFlat($society, $this->makeBuilding($society));
        $owner    = $this->makeResidentUser($society, $flat);
        $this->makeRole('Apartment Owner');
        $owner->assignRole('Apartment Owner');

        $payload = [
            'facility_id'     => $facility->id,
            'flat_id'         => $flat->id,
            'booking_date'    => now()->addDay()->toDateString(),
            'start_time'      => '10:00',
            'end_time'        => '12:00',
            'expected_guests' => 5,
        ];

        $this->actingAs($owner)->post('/facility-bookings', $payload)
            ->assertRedirect(route('facility-bookings.index'));

        $this->assertSame(1, FacilityBooking::where('facility_id', $facility->id)->count());

        // A second request for a fully overlapping slot must be rejected,
        // and must not create a second row.
        $overlap = array_merge($payload, ['start_time' => '11:00', 'end_time' => '13:00']);

        $response = $this->actingAs($owner)->post('/facility-bookings', $overlap);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertSame(1, FacilityBooking::where('facility_id', $facility->id)->count());
    }

    public function test_non_overlapping_booking_on_the_same_day_succeeds(): void
    {
        $society  = $this->makeSociety('Society A');
        $facility = $this->facilityIn($society);
        $flat     = $this->makeFlat($society, $this->makeBuilding($society));
        $owner    = $this->makeResidentUser($society, $flat);
        $this->makeRole('Apartment Owner');
        $owner->assignRole('Apartment Owner');

        $date = now()->addDay()->toDateString();

        $this->actingAs($owner)->post('/facility-bookings', [
            'facility_id'  => $facility->id,
            'flat_id'      => $flat->id,
            'booking_date' => $date,
            'start_time'   => '10:00',
            'end_time'     => '12:00',
        ])->assertRedirect(route('facility-bookings.index'));

        $this->actingAs($owner)->post('/facility-bookings', [
            'facility_id'  => $facility->id,
            'flat_id'      => $flat->id,
            'booking_date' => $date,
            'start_time'   => '14:00',
            'end_time'     => '16:00',
        ])->assertRedirect(route('facility-bookings.index'));

        $this->assertSame(2, FacilityBooking::where('facility_id', $facility->id)->count());
    }

    public function test_booking_is_rolled_back_entirely_when_the_overlap_check_rejects_it(): void
    {
        // Guards against the transaction wrapper silently committing a
        // partial row (e.g. a FacilityBooking with no booking_number) on the
        // rejected path.
        $society  = $this->makeSociety('Society A');
        $facility = $this->facilityIn($society);
        $flat     = $this->makeFlat($society, $this->makeBuilding($society));
        $owner    = $this->makeResidentUser($society, $flat);
        $this->makeRole('Apartment Owner');
        $owner->assignRole('Apartment Owner');

        FacilityBooking::create([
            'society_id'   => $society->id,
            'facility_id'  => $facility->id,
            'user_id'      => $owner->id,
            'flat_id'      => $flat->id,
            'booking_date' => now()->addDay()->toDateString(),
            'start_time'   => '10:00',
            'end_time'     => '12:00',
            'status'       => 'approved',
        ]);

        $this->actingAs($owner)->post('/facility-bookings', [
            'facility_id'  => $facility->id,
            'flat_id'      => $flat->id,
            'booking_date' => now()->addDay()->toDateString(),
            'start_time'   => '11:00',
            'end_time'     => '13:00',
        ]);

        $this->assertSame(1, FacilityBooking::where('facility_id', $facility->id)->count());
    }
}
