<?php

namespace Tests\Feature;

use App\Models\Facility;
use App\Models\FacilityBooking;
use App\Models\Flat;
use App\Models\Payment;
use App\Models\Society;
use App\Models\User;
use App\Models\VillaArea;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "owner_id IS NULL" is not "no owner": it is most of the flats in the
 * system (flats.owner_id is unset on the majority of rows - see
 * UserFlatResolver). Several screens ran
 *
 *     Flat::where('owner_id', $user->owner_id)
 *
 * for an account holding an Owner role but with NO owner link (trivially
 * created from User Management). Laravel turns where('owner_id', null) into
 * `owner_id IS NULL`, so the account was treated as the owner of every
 * unassigned flat, in every society:
 *
 *   - Payments / Facility Bookings lists showed other residents' records;
 *   - the legacy /my/* villa-owner portal resolved "my villa" to the first
 *     unassigned villa of ANY society, letting an unlinked account (even a
 *     tenant or a guard) list another society's facilities, book them, and
 *     cancel bookings made on that villa.
 *
 * An account with no owner link owns nothing.
 */
class UnlinkedOwnerIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Society $a;
    private Society $b;

    protected function setUp(): void
    {
        parent::setUp();
        $this->a = $this->makeSociety('Society A');
        $this->b = $this->makeSociety('Society B');
    }

    private function payment(Society $society, Flat $flat, User $by, string $note): Payment
    {
        return Payment::create([
            'society_id' => $society->id, 'flat_id' => $flat->id, 'user_id' => $by->id,
            'payment_id' => Payment::generatePaymentId(), 'transaction_id' => 'TX-' . uniqid(),
            'amount' => 1500, 'payment_method' => 'cash', 'payment_gateway' => 'manual', 'status' => 'success',
            'payment_date' => now(), 'bill_type' => 'maintenance', 'due_date' => now(), 'notes' => $note,
        ]);
    }

    private function booking(Society $society, Facility $facility, Flat $flat, User $by, array $over = []): FacilityBooking
    {
        return FacilityBooking::create(array_merge([
            'society_id' => $society->id, 'facility_id' => $facility->id, 'user_id' => $by->id, 'flat_id' => $flat->id,
            'booking_date' => now()->addDays(3)->toDateString(), 'start_time' => '10:00', 'end_time' => '11:00',
            'status' => 'pending', 'purpose' => 'secret purpose ' . uniqid(),
        ], $over));
    }

    public static function ownerRoles(): array
    {
        return ['Owner' => ['Owner'], 'Villa Owner' => ['Villa Owner'], 'Apartment Owner' => ['Apartment Owner']];
    }

    // ----------------------------------------------- listings in one's own society

    /** @dataProvider ownerRoles */
    public function test_an_owner_role_with_no_owner_link_sees_no_one_elses_payments_or_bookings(string $role): void
    {
        $building = $this->makeBuilding($this->a);
        $unowned  = $this->makeFlat($this->a, $building, '101');          // owner_id NULL, like most flats
        $resident = $this->makeUserWithRole($this->a, 'Tenant');
        $facility = Facility::create(['society_id' => $this->a->id, 'name' => 'Gym', 'type' => 'gym', 'status' => 'active']);

        $theirPayment = $this->payment($this->a, $unowned, $resident, 'tenant payment');
        $theirBooking = $this->booking($this->a, $facility, $unowned, $resident);

        $unlinked = $this->makeUserWithRole($this->a, $role, ['owner_id' => null]);

        $payments = $this->actingAs($unlinked)->get(route('payments.index'))->viewData('payments');
        $this->assertNotNull($payments, "$role: payments page did not render a list");
        $this->assertNotContains($theirPayment->id, $payments->pluck('id')->all(), "$role saw a tenant's payment");

        $bookings = $this->actingAs($unlinked)->get(route('facility-bookings.index'))->viewData('bookings');
        $this->assertNotNull($bookings, "$role: bookings page did not render a list");
        $this->assertNotContains($theirBooking->id, $bookings->pluck('id')->all(), "$role saw a tenant's booking");
    }

    public function test_a_properly_linked_owner_still_sees_their_own_units_records(): void
    {
        $building = $this->makeBuilding($this->a);
        $owner = \App\Models\Owner::create(['society_id' => $this->a->id, 'name' => 'Real', 'email' => 'real@test.local', 'phone' => '9000000020', 'status' => 'active']);
        $mine  = $this->makeFlat($this->a, $building, '201');
        $mine->update(['owner_id' => $owner->id]);
        $other = $this->makeFlat($this->a, $building, '202');
        $user  = $this->makeUserWithRole($this->a, 'Owner', ['owner_id' => $owner->id]);
        $neighbour = $this->makeUserWithRole($this->a, 'Tenant');
        $facility = Facility::create(['society_id' => $this->a->id, 'name' => 'Gym', 'type' => 'gym', 'status' => 'active']);

        $own   = $this->payment($this->a, $mine, $user, 'mine');
        $notMine = $this->payment($this->a, $other, $neighbour, 'not mine');
        $ownBooking = $this->booking($this->a, $facility, $mine, $user);
        $notMineBooking = $this->booking($this->a, $facility, $other, $neighbour);

        $ids = $this->actingAs($user)->get(route('payments.index'))->viewData('payments')->pluck('id')->all();
        $this->assertContains($own->id, $ids);
        $this->assertNotContains($notMine->id, $ids);

        $bids = $this->actingAs($user)->get(route('facility-bookings.index'))->viewData('bookings')->pluck('id')->all();
        $this->assertContains($ownBooking->id, $bids);
        $this->assertNotContains($notMineBooking->id, $bids);
    }

    // ------------------------------------------------ the legacy /my/* portal

    private function foreignUnassignedVilla(): array
    {
        $area  = VillaArea::create(['society_id' => $this->b->id, 'name' => 'B Area', 'status' => 'active']);
        $villa = Flat::create(['society_id' => $this->b->id, 'villa_area_id' => $area->id, 'property_type' => 'villa', 'flat_number' => 'B-1', 'status' => 'vacant']);
        $facility = Facility::create(['society_id' => $this->b->id, 'name' => 'B Clubhouse', 'type' => 'clubhouse', 'status' => 'active']);
        $someone  = $this->makeUserWithRole($this->b, 'Tenant');
        $booking  = $this->booking($this->b, $facility, $villa, $someone);

        return [$villa, $facility, $booking];
    }

    /** @dataProvider portalUsers */
    public function test_an_account_with_no_villa_cannot_use_another_societys_unassigned_villa_through_the_portal(string $role): void
    {
        [$villa, $facility, $booking] = $this->foreignUnassignedVilla();
        $user = $this->makeUserWithRole($this->a, $role, ['owner_id' => null]);
        $bookingsBefore = FacilityBooking::count();

        // "Refused" = sent away (302, no villa) or denied outright (403, e.g. the Guard
        // containment middleware blocks the whole portal). Never a 200 page.
        $refused = fn ($r) => $this->assertContains($r->getStatusCode(), [302, 403], "$role was served the portal");

        $refused($this->actingAs($user)->get(route('villa-owner.facilities')));
        $this->actingAs($user)->get(route('villa-owner.facilities.book', $facility))->assertForbidden();
        $this->actingAs($user)->post(route('villa-owner.facilities.store-booking', $facility), [
            'booking_date' => now()->addDays(5)->toDateString(), 'start_time' => '14:00', 'end_time' => '15:00',
        ])->assertForbidden();
        $refused($this->actingAs($user)->get(route('villa-owner.bookings')));
        $this->actingAs($user)->post(route('villa-owner.bookings.cancel', $booking))->assertForbidden();

        $this->assertSame($bookingsBefore, FacilityBooking::count(), "$role created a booking in another society");
        $this->assertSame('pending', $booking->fresh()->status, "$role cancelled another society's booking");
    }

    public static function portalUsers(): array
    {
        return ['Tenant' => ['Tenant'], 'Guard' => ['Guard'], 'Staff' => ['Staff'], 'Accountant' => ['Accountant'], 'Admin' => ['Admin'], 'Owner (unlinked)' => ['Owner']];
    }

    public function test_a_real_villa_owner_can_still_book_and_cancel_through_the_portal(): void
    {
        $area  = VillaArea::create(['society_id' => $this->a->id, 'name' => 'A Area', 'status' => 'active']);
        $owner = \App\Models\Owner::create(['society_id' => $this->a->id, 'name' => 'Villa Owner', 'email' => 'vo@test.local', 'phone' => '9000000021', 'status' => 'active', 'property_type' => 'villa']);
        $villa = Flat::create(['society_id' => $this->a->id, 'villa_area_id' => $area->id, 'property_type' => 'villa', 'flat_number' => 'A-1', 'status' => 'occupied', 'owner_id' => $owner->id]);
        $user  = $this->makeUserWithRole($this->a, 'Owner', ['owner_id' => $owner->id]);
        $facility = Facility::create(['society_id' => $this->a->id, 'name' => 'A Clubhouse', 'type' => 'clubhouse', 'status' => 'active', 'requires_approval' => false]);
        $admin      = $this->makeUserWithRole($this->a, 'Admin');
        $otherAdmin = $this->makeUserWithRole($this->b, 'Admin');

        $this->actingAs($user)->get(route('villa-owner.facilities'))->assertOk()->assertSee('A Clubhouse');
        $this->actingAs($user)->post(route('villa-owner.facilities.store-booking', $facility), [
            'booking_date' => now()->addDays(5)->toDateString(), 'start_time' => '14:00', 'end_time' => '15:00',
        ])->assertRedirect(route('villa-owner.bookings'));

        $booking = FacilityBooking::where('flat_id', $villa->id)->sole();
        $this->assertSame($user->id, $booking->user_id);

        // The office hears about it - this portal used to book silently - and only this society's.
        $this->assertSame(['New facility booking'], $admin->fresh()->notifications()->pluck('data')->map(fn ($d) => $d['title'])->all());
        $this->assertSame(0, $otherAdmin->fresh()->notifications()->count());

        // The shared rules now apply here too: an overlapping slot is refused, not double-booked.
        $this->actingAs($user)->post(route('villa-owner.facilities.store-booking', $facility), [
            'booking_date' => now()->addDays(5)->toDateString(), 'start_time' => '14:30', 'end_time' => '15:30',
        ])->assertSessionHas('error');
        $this->assertSame(1, FacilityBooking::where('facility_id', $facility->id)->count());

        // The portal shows the slot as a time of day. It used to print the raw datetime cast,
        // i.e. "2026-10-10 14:00:00" - today's date in front of the time, not the booking's.
        $page = $this->actingAs($user)->get(route('villa-owner.bookings'))->assertOk();
        $page->assertSee('02:00 PM - 03:00 PM');
        $page->assertDontSee($booking->start_time instanceof \DateTimeInterface ? $booking->start_time->format('Y-m-d') . ' 14:00:00' : '14:00:00 - ');

        $this->actingAs($user)->post(route('villa-owner.bookings.cancel', $booking))->assertSessionHas('success');
        $this->assertSame('cancelled', $booking->fresh()->status);
        $this->assertSame(2, $admin->fresh()->notifications()->count(), 'and is told about the cancellation');
    }
}
