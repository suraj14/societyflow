<?php

namespace Tests\Feature;

use App\Models\EmailLog;
use App\Models\Facility;
use App\Models\FacilityBooking;
use App\Models\Flat;
use App\Models\Owner;
use App\Models\Society;
use App\Models\User;
use App\Models\VillaArea;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * The WEB booking form used to tell nobody: the office never learned a request
 * existed, the resident got no acknowledgement, an edit to a pending request
 * (new date/time) changed under the office's feet, and withdrawing a request
 * was silent - while the mobile API and the legacy portal already notified.
 * Notifications fire only after the write succeeded, only to the right society,
 * and never for the office acting on its own.
 */
class FacilityBookingWebNotificationTest extends TestCase
{
    use RefreshDatabase;

    private Society $society;
    private User $admin;
    private User $resident;
    private Flat $flat;
    private User $otherAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Permission::firstOrCreate(['name' => 'manage_facilities', 'guard_name' => 'web']);

        $this->society = $this->makeSociety('Eden');
        $this->admin = $this->makeUserWithRole($this->society, 'Admin');
        $this->admin->givePermissionTo('manage_facilities');
        $this->otherAdmin = $this->makeUserWithRole($this->makeSociety('Other'), 'Admin');

        $area = VillaArea::create(['society_id' => $this->society->id, 'name' => 'A', 'status' => 'active']);
        $owner = Owner::create(['society_id' => $this->society->id, 'name' => 'Owner', 'email' => 'o@t.local', 'phone' => '0000000001', 'status' => 'active', 'property_type' => 'villa']);
        $this->flat = Flat::create(['society_id' => $this->society->id, 'villa_area_id' => $area->id, 'property_type' => 'villa', 'flat_number' => '160', 'status' => 'vacant', 'owner_id' => $owner->id]);
        $this->resident = $this->makeUserWithRole($this->society, 'Owner', ['name' => 'Suraj', 'owner_id' => $owner->id]);
    }

    private function facility(bool $approval, string $name = 'Clubhouse'): Facility
    {
        return Facility::create([
            'society_id' => $this->society->id, 'name' => $name, 'type' => 'clubhouse', 'status' => 'active', 'requires_approval' => $approval,
            'opening_time' => '08:00', 'closing_time' => '22:00', 'advance_booking_days' => 60, 'max_booking_hours' => 6, 'booking_charge' => 0,
            'available_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
        ]);
    }

    private function slot(array $over = []): array
    {
        return array_merge([
            'booking_date' => now()->addDays(10)->toDateString(), 'start_time' => '10:00', 'end_time' => '12:00',
            'purpose' => 'Party', 'expected_guests' => 10, 'flat_id' => $this->flat->id,
        ], $over);
    }

    private function titles(User $user): array
    {
        return $user->fresh()->notifications()->pluck('data')->map(fn ($d) => $d['title'])->sort()->values()->all();
    }

    private function book(User $as, Facility $facility, array $over = [])
    {
        return $this->actingAs($as)->post(route('facility-bookings.store'), $this->slot($over) + ['facility_id' => $facility->id]);
    }

    // ---------------------------------------------------------------- request

    public function test_a_request_needing_approval_tells_the_office_and_acknowledges_the_resident(): void
    {
        $this->book($this->resident, $this->facility(true))->assertRedirect(route('facility-bookings.index'));

        $this->assertSame(['Facility booking request'], $this->titles($this->admin));
        $this->assertSame(['Booking request received'], $this->titles($this->resident));
        $this->assertSame([], $this->titles($this->otherAdmin), 'another society hears nothing');
        $this->assertStringContainsString('Clubhouse', $this->admin->notifications()->first()->data['body']);
    }

    public function test_an_instant_booking_is_confirmed_to_the_resident_and_reported_to_the_office(): void
    {
        $this->book($this->resident, $this->facility(false));

        $this->assertSame(['New facility booking'], $this->titles($this->admin));
        $this->assertSame(['Booking confirmed'], $this->titles($this->resident));
    }

    public function test_the_office_booking_for_itself_notifies_nobody(): void
    {
        $this->book($this->admin, $this->facility(false))->assertRedirect(route('facility-bookings.index'));

        $this->assertSame(1, FacilityBooking::count());
        $this->assertSame([], $this->titles($this->admin));
        $this->assertSame([], $this->titles($this->resident));
    }

    public function test_a_refused_request_creates_no_booking_and_no_notification(): void
    {
        $facility = $this->facility(false);
        $this->book($this->resident, $facility)->assertSessionMissing('error');
        $this->admin->notifications()->delete();
        $this->resident->notifications()->delete();

        // The very same slot again: refused as a clash.
        $this->book($this->resident, $facility)->assertSessionHas('error');

        $this->assertSame(1, FacilityBooking::count());
        $this->assertSame([], $this->titles($this->admin));
        $this->assertSame([], $this->titles($this->resident));
    }

    public function test_a_request_for_someone_elses_property_is_refused_silently(): void
    {
        $stranger = $this->makeUserWithRole($this->society, 'Owner', ['owner_id' => null]);

        $this->book($stranger, $this->facility(true))->assertSessionHas('error');

        $this->assertSame(0, FacilityBooking::count());
        $this->assertSame([], $this->titles($this->admin));
    }

    // ---------------------------------------------------------------- edit

    public function test_a_resident_changing_a_pending_request_tells_the_office_the_new_time(): void
    {
        $facility = $this->facility(true);
        $this->book($this->resident, $facility);
        $booking = FacilityBooking::firstOrFail();
        $this->admin->notifications()->delete();

        $this->actingAs($this->resident)->put(route('facility-bookings.update', $booking), $this->slot(['start_time' => '15:00', 'end_time' => '17:00']) + ['facility_id' => $facility->id])
            ->assertRedirect(route('facility-bookings.index'));

        $this->assertSame(['Facility booking request changed'], $this->titles($this->admin));
        $this->assertStringContainsString('03:00 PM - 05:00 PM', $this->admin->notifications()->first()->data['body']);
    }

    public function test_the_office_editing_a_request_does_not_notify_itself(): void
    {
        $facility = $this->facility(true);
        $this->book($this->resident, $facility);
        $booking = FacilityBooking::firstOrFail();
        $this->admin->notifications()->delete();

        $this->actingAs($this->admin)->put(route('facility-bookings.update', $booking), $this->slot(['start_time' => '15:00', 'end_time' => '17:00']) + ['facility_id' => $facility->id]);

        $this->assertSame([], $this->titles($this->admin));
    }

    // ---------------------------------------------------------------- withdraw

    public function test_a_resident_withdrawing_a_pending_request_tells_the_office(): void
    {
        $this->book($this->resident, $this->facility(true));
        $booking = FacilityBooking::firstOrFail();
        $this->admin->notifications()->delete();

        $this->actingAs($this->resident)->delete(route('facility-bookings.destroy', $booking))->assertRedirect(route('facility-bookings.index'));

        $this->assertSame(0, FacilityBooking::count());
        $this->assertSame(['Facility booking cancelled'], $this->titles($this->admin));
        $this->assertSame(route('facility-bookings.index'), $this->admin->notifications()->first()->data['url'], 'no link to a deleted record');
    }

    public function test_the_office_deleting_a_record_is_not_a_cancellation_notice(): void
    {
        $this->book($this->resident, $this->facility(true));
        $booking = FacilityBooking::firstOrFail();
        $this->admin->notifications()->delete();

        $this->actingAs($this->admin)->delete(route('facility-bookings.destroy', $booking));

        $this->assertSame([], $this->titles($this->admin));
    }

    // ---------------------------------------------------------------- decision

    public function test_approving_notifies_the_owner_once_and_only_on_a_real_transition(): void
    {
        $this->book($this->resident, $this->facility(true));
        $booking = FacilityBooking::firstOrFail();
        $this->resident->notifications()->delete();
        EmailLog::query()->delete();

        $approve = fn () => $this->actingAs($this->admin)->put(route('facility-bookings.update-status', $booking), ['status' => 'approved']);
        $approve()->assertRedirect(route('facility-bookings.index'));

        $this->assertSame(['Booking Approved'], $this->titles($this->resident));
        $this->assertSame(1, EmailLog::where('recipient_email', $this->resident->email)->where('trigger_action', 'facility_booking_status_updated')->where('status', 'sent')->count());

        $approve();   // approving what is already approved
        $this->assertSame(['Booking Approved'], $this->titles($this->resident), 'no duplicate in-app message');
        $this->assertSame(1, EmailLog::where('recipient_email', $this->resident->email)->count(), 'no duplicate email');
    }

    public function test_rejection_is_reported_with_the_real_status(): void
    {
        $this->book($this->resident, $this->facility(true));
        $booking = FacilityBooking::firstOrFail();
        $this->resident->notifications()->delete();

        $this->actingAs($this->admin)->put(route('facility-bookings.update-status', $booking), ['status' => 'rejected', 'admin_notes' => 'Reserved']);

        $this->assertSame(['Booking Rejected'], $this->titles($this->resident));
        $this->assertStringContainsString('Rejected', $this->resident->notifications()->first()->data['body']);
    }

    public function test_another_societys_admin_cannot_decide_it_and_nobody_is_told(): void
    {
        Permission::firstOrCreate(['name' => 'manage_facilities', 'guard_name' => 'web']);
        $this->otherAdmin->givePermissionTo('manage_facilities');
        $this->book($this->resident, $this->facility(true));
        $booking = FacilityBooking::firstOrFail();
        $this->resident->notifications()->delete();

        $this->actingAs($this->otherAdmin)->put(route('facility-bookings.update-status', $booking), ['status' => 'approved'])->assertStatus(403);

        $this->assertSame('pending', $booking->fresh()->status);
        $this->assertSame([], $this->titles($this->resident));
    }
}
