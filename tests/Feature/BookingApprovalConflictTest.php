<?php

namespace Tests\Feature;

use App\Models\EmailLog;
use App\Models\Facility;
use App\Models\FacilityBooking;
use App\Models\Society;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Creating or editing a booking refuses a taken slot, but the DECISION step did
 * not: a pending request that overlaps an approved one (the shape of dev
 * bookings 23/30 against 22/28, filed before the checks existed) could simply
 * be approved on top of it. Approval - web and mobile API - now refuses, while
 * rejecting, cancelling, adjacent slots and other facilities still work.
 */
class BookingApprovalConflictTest extends TestCase
{
    use RefreshDatabase;

    private Society $society;
    private User $admin;
    private User $resident;
    private Facility $hall;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Permission::firstOrCreate(['name' => 'manage_facilities', 'guard_name' => 'web']);
        $this->society = $this->makeSociety('Eden');
        $this->admin = $this->makeUserWithRole($this->society, 'Admin');
        $this->admin->givePermissionTo('manage_facilities');
        $this->resident = $this->makeUserWithRole($this->society, 'Tenant');
        $this->hall = $this->facility('Community Hall');
    }

    private function facility(string $name, ?Society $society = null): Facility
    {
        return Facility::create(['society_id' => ($society ?? $this->society)->id, 'name' => $name, 'type' => 'hall', 'status' => 'active', 'booking_charge' => 0]);
    }

    private function booking(string $status, string $start = '09:00', string $end = '12:00', ?Facility $facility = null, string $date = '2026-02-12'): FacilityBooking
    {
        $facility ??= $this->hall;

        return FacilityBooking::create([
            'society_id' => $facility->society_id, 'facility_id' => $facility->id, 'user_id' => $this->resident->id, 'flat_id' => null,
            'booking_date' => $date, 'start_time' => $start, 'end_time' => $end, 'booking_amount' => 0, 'status' => $status,
        ]);
    }

    private function web(FacilityBooking $b, string $status, ?User $as = null)
    {
        return $this->actingAs($as ?? $this->admin)->from(route('facility-bookings.index'))
            ->put(route('facility-bookings.update-status', $b), ['status' => $status]);
    }

    private function api(FacilityBooking $b, string $status)
    {
        Sanctum::actingAs($this->admin);

        return $this->postJson("/api/v1/admin/facility-bookings/{$b->id}/status", ['status' => $status]);
    }

    private function untouched(FacilityBooking $b, string $status): void
    {
        $this->assertSame($status, $b->fresh()->status);
        $this->assertNull($b->fresh()->approved_at, 'not approved');
        $this->assertSame([], $this->resident->fresh()->notifications()->pluck('data')->all(), 'nobody was told it was approved');
        $this->assertSame(0, EmailLog::count());
    }

    // ------------------------------------------------------------ refused

    public function test_web_refuses_to_approve_a_pending_booking_over_an_approved_one(): void
    {
        $this->booking('approved');
        $pending = $this->booking('pending');

        $this->web($pending, 'approved')->assertRedirect(route('facility-bookings.index'))->assertSessionHas('error');

        $this->untouched($pending, 'pending');
    }

    public function test_the_api_refuses_it_too(): void
    {
        $this->booking('approved');
        $pending = $this->booking('pending', '10:00', '11:00');   // inside the approved slot

        $this->api($pending, 'approved')->assertStatus(409)->assertJson(['success' => false]);

        $this->untouched($pending, 'pending');
    }

    public function test_any_partial_overlap_counts(): void
    {
        $this->booking('approved', '10:00', '12:00');

        foreach ([['09:00', '11:00'], ['11:00', '13:00'], ['09:00', '13:00'], ['10:00', '12:00']] as [$s, $e]) {
            $pending = $this->booking('pending', $s, $e);
            $this->web($pending, 'approved')->assertSessionHas('error');
            $this->assertSame('pending', $pending->fresh()->status, "{$s}-{$e}");
        }
    }

    public function test_reopening_a_rejected_booking_onto_a_taken_slot_is_refused(): void
    {
        $this->booking('approved');
        $rejected = $this->booking('rejected');

        $this->web($rejected, 'pending')->assertSessionHas('error');

        $this->assertSame('rejected', $rejected->fresh()->status);
    }

    // ------------------------------------------------------------ still works

    public function test_a_conflicting_pending_booking_can_still_be_rejected_or_cancelled(): void
    {
        $this->booking('approved');
        $a = $this->booking('pending');
        $b = $this->booking('pending');

        $this->web($a, 'rejected')->assertSessionMissing('error');
        $this->web($b, 'cancelled')->assertSessionMissing('error');

        $this->assertSame('rejected', $a->fresh()->status);
        $this->assertSame('cancelled', $b->fresh()->status);
    }

    public function test_once_the_other_booking_is_cancelled_the_slot_is_free_to_approve(): void
    {
        $approved = $this->booking('approved');
        $pending = $this->booking('pending');
        $this->web($pending, 'approved')->assertSessionHas('error');

        $this->web($approved, 'cancelled')->assertSessionMissing('error');
        $this->web($pending, 'approved')->assertSessionMissing('error');

        $this->assertSame('approved', $pending->fresh()->status);
        $this->assertNotNull($pending->fresh()->approved_at);
    }

    public function test_adjacent_other_day_and_other_facility_bookings_are_not_conflicts(): void
    {
        $this->booking('approved', '10:00', '12:00');

        $adjacent = $this->booking('pending', '12:00', '14:00');                                   // starts when the other ends
        $otherDay = $this->booking('pending', '10:00', '12:00', null, '2026-02-13');
        $otherFacility = $this->booking('pending', '10:00', '12:00', $this->facility('Gym'));

        foreach ([$adjacent, $otherDay, $otherFacility] as $b) {
            $this->web($b, 'approved')->assertSessionMissing('error');
            $this->assertSame('approved', $b->fresh()->status);
        }
    }

    public function test_approving_a_normal_booking_notifies_the_owner_exactly_as_before(): void
    {
        $pending = $this->booking('pending');

        $this->web($pending, 'approved')->assertSessionMissing('error');

        $this->assertSame('approved', $pending->fresh()->status);
        $this->assertSame(['Booking Approved'], $this->resident->fresh()->notifications()->pluck('data')->map(fn ($d) => $d['title'])->all());
    }

    public function test_approving_what_is_already_approved_is_not_a_conflict_with_itself(): void
    {
        $approved = $this->booking('approved');

        $this->web($approved, 'approved')->assertSessionMissing('error');
        $this->assertSame('approved', $approved->fresh()->status);
    }

    // ------------------------------------------------------------ boundaries

    public function test_another_societys_booking_never_blocks_this_ones(): void
    {
        $otherSociety = $this->makeSociety('Other');
        $theirs = $this->facility('Community Hall', $otherSociety);
        $this->booking('approved', '09:00', '12:00', $theirs);
        $mine = $this->booking('pending');

        $this->web($mine, 'approved')->assertSessionMissing('error');
        $this->assertSame('approved', $mine->fresh()->status);
    }

    public function test_another_societys_admin_still_cannot_decide_it(): void
    {
        $other = $this->makeUserWithRole($this->makeSociety('Other'), 'Admin');
        $other->givePermissionTo('manage_facilities');
        $pending = $this->booking('pending');

        $this->web($pending, 'approved', $other)->assertStatus(403);
        $this->assertSame('pending', $pending->fresh()->status);
    }
}
