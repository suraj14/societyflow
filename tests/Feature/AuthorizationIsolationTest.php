<?php

namespace Tests\Feature;

use App\Models\Facility;
use App\Models\FacilityBooking;
use App\Models\Flat;
use App\Models\MaintenanceBill;
use App\Models\Owner;
use App\Models\User;
use App\Models\Visitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthorizationIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function makeFacility($society, string $name = 'Clubhouse'): Facility
    {
        return Facility::create([
            'society_id' => $society->id,
            'name'       => $name,
            'status'     => 'active',
        ]);
    }

    // ── Facility booking ─────────────────────────────────────────────────────

    public function test_cannot_book_a_facility_from_another_society(): void
    {
        $societyA = $this->makeSociety('Society A');
        $societyB = $this->makeSociety('Society B');
        $flatA    = $this->makeFlat($societyA, $this->makeBuilding($societyA));
        $userA    = $this->makeResidentUser($societyA, $flatA);
        $userA->assignRole($this->makeRole('Tenant'));
        $facilityB = $this->makeFacility($societyB, 'B Clubhouse');

        Sanctum::actingAs($userA);

        $this->postJson('/api/v1/facility-bookings', [
            'facility_id'  => $facilityB->id,
            'booking_date' => now()->addDay()->toDateString(),
            'start_time'   => '10:00',
            'end_time'     => '11:00',
        ])->assertStatus(404);

        $this->assertSame(0, FacilityBooking::count());
    }

    public function test_double_booking_is_rejected(): void
    {
        $society  = $this->makeSociety();
        $flat     = $this->makeFlat($society, $this->makeBuilding($society));
        $user1    = $this->makeResidentUser($society, $flat);
        $user1->assignRole($this->makeRole('Tenant'));
        $user2    = $this->makeResidentUser($society, $flat);
        $user2->assignRole($this->makeRole('Tenant'));
        $facility = $this->makeFacility($society);
        $date     = now()->addDay()->toDateString();

        Sanctum::actingAs($user1);
        $this->postJson('/api/v1/facility-bookings', [
            'facility_id'  => $facility->id,
            'booking_date' => $date,
            'start_time'   => '10:00',
            'end_time'     => '12:00',
        ])->assertStatus(201);

        // Overlapping slot by another user → rejected
        Sanctum::actingAs($user2);
        $this->postJson('/api/v1/facility-bookings', [
            'facility_id'  => $facility->id,
            'booking_date' => $date,
            'start_time'   => '11:00',
            'end_time'     => '13:00',
        ])->assertStatus(422);

        // Non-overlapping slot → allowed
        $this->postJson('/api/v1/facility-bookings', [
            'facility_id'  => $facility->id,
            'booking_date' => $date,
            'start_time'   => '12:00',
            'end_time'     => '13:00',
        ])->assertStatus(201);

        $this->assertSame(2, FacilityBooking::count());
    }

    public function test_booking_validation_rejects_bad_times_and_past_dates(): void
    {
        $society  = $this->makeSociety();
        $flat     = $this->makeFlat($society, $this->makeBuilding($society));
        $user     = $this->makeResidentUser($society, $flat);
        $user->assignRole($this->makeRole('Tenant'));
        $facility = $this->makeFacility($society);

        Sanctum::actingAs($user);

        // end before start
        $this->postJson('/api/v1/facility-bookings', [
            'facility_id'  => $facility->id,
            'booking_date' => now()->addDay()->toDateString(),
            'start_time'   => '12:00',
            'end_time'     => '10:00',
        ])->assertStatus(422);

        // past date
        $this->postJson('/api/v1/facility-bookings', [
            'facility_id'  => $facility->id,
            'booking_date' => now()->subDay()->toDateString(),
            'start_time'   => '10:00',
            'end_time'     => '11:00',
        ])->assertStatus(422);
    }

    public function test_cannot_cancel_someone_elses_booking(): void
    {
        $society  = $this->makeSociety();
        $flat     = $this->makeFlat($society, $this->makeBuilding($society));
        $owner    = $this->makeResidentUser($society, $flat);
        $owner->assignRole($this->makeRole('Tenant'));
        $other    = $this->makeResidentUser($society, $flat);
        $other->assignRole($this->makeRole('Tenant'));
        $facility = $this->makeFacility($society);

        Sanctum::actingAs($owner);
        $id = $this->postJson('/api/v1/facility-bookings', [
            'facility_id'  => $facility->id,
            'booking_date' => now()->addDay()->toDateString(),
            'start_time'   => '10:00',
            'end_time'     => '11:00',
        ])->json('data.id');

        Sanctum::actingAs($other);
        $this->postJson("/api/v1/facility-bookings/{$id}/cancel")->assertStatus(404);
    }

    // ── Visitors ─────────────────────────────────────────────────────────────

    public function test_resident_cannot_attach_visitor_to_foreign_flat(): void
    {
        $societyA = $this->makeSociety('Society A');
        $societyB = $this->makeSociety('Society B');
        $flatA    = $this->makeFlat($societyA, $this->makeBuilding($societyA));
        $flatB    = $this->makeFlat($societyB, $this->makeBuilding($societyB), '201');
        $flatA2   = $this->makeFlat($societyA, $this->makeBuilding($societyA, 'Tower B'), '301');
        $userA    = $this->makeResidentUser($societyA, $flatA);

        Sanctum::actingAs($userA);

        // Another society's flat
        $this->postJson('/api/v1/visitors', [
            'visitor_phone' => '9000000001',
            'visitor_type'  => 'guest',
            'flat_id'       => $flatB->id,
        ])->assertStatus(403);

        // Same society, but not the user's own flat
        $this->postJson('/api/v1/visitors', [
            'visitor_phone' => '9000000002',
            'visitor_type'  => 'guest',
            'flat_id'       => $flatA2->id,
        ])->assertStatus(403);

        // Own flat (implicit) works
        $this->postJson('/api/v1/visitors', [
            'visitor_phone' => '9000000003',
            'visitor_type'  => 'guest',
        ])->assertStatus(201);

        $this->assertSame(1, Visitor::count());
        $this->assertSame($flatA->id, Visitor::first()->flat_id);
    }

    public function test_resident_cannot_check_in_someone_elses_visitor(): void
    {
        $society = $this->makeSociety();
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));
        $host    = $this->makeResidentUser($society, $flat);
        $other   = $this->makeResidentUser($society, $flat);

        Sanctum::actingAs($host);
        $visitorId = $this->postJson('/api/v1/visitors', [
            'visitor_phone' => '9000000004',
            'visitor_type'  => 'guest',
        ])->json('data.id');

        Sanctum::actingAs($other);
        $this->postJson("/api/v1/visitors/{$visitorId}/check-in")->assertStatus(403);

        Sanctum::actingAs($host);
        $this->postJson("/api/v1/visitors/{$visitorId}/check-in")->assertOk();
    }

    // ── Complaints ───────────────────────────────────────────────────────────

    public function test_complaint_categories_route_is_reachable(): void
    {
        // Was shadowed by /complaints/{id} with an int type-hint → 500
        $society = $this->makeSociety();
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));
        $user    = $this->makeResidentUser($society, $flat);

        Sanctum::actingAs($user);
        $this->getJson('/api/v1/complaints/categories')->assertOk();
    }

    public function test_cannot_read_another_users_complaint(): void
    {
        $society = $this->makeSociety();
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));
        $author  = $this->makeResidentUser($society, $flat);
        $other   = $this->makeResidentUser($society, $flat);

        Sanctum::actingAs($author);
        $create = $this->postJson('/api/v1/complaints', [
            'title'       => 'Leaking tap',
            'description' => 'Kitchen tap leaks',
        ]);
        $create->assertStatus(201);
        $id = $create->json('data.id');
        $this->assertNotNull($id);

        Sanctum::actingAs($other);
        $this->getJson("/api/v1/complaints/{$id}")->assertStatus(404);
    }

    // ── Owner bill resolution ────────────────────────────────────────────────

    public function test_owner_without_resident_record_sees_their_bills(): void
    {
        $society = $this->makeSociety();
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));

        $owner = Owner::create([
            'society_id' => $society->id,
            'name'       => 'Villa Owner',
            'email'      => 'owner@test.local',
            'phone'      => '9555555555',
            'status'     => 'active',
        ]);
        $flat->update(['owner_id' => $owner->id]);

        $user = User::create([
            'society_id' => $society->id,
            'owner_id'   => $owner->id,
            'name'       => 'Villa Owner',
            'email'      => 'owner@test.local',
            'phone'      => '9555555555',
            'password'   => bcrypt('password123'),
            'status'     => 'active',
        ]);

        MaintenanceBill::create([
            'society_id'   => $society->id,
            'flat_id'      => $flat->id,
            'bill_number'  => 'MB-TEST-1',
            'month'        => 'January',
            'year'         => now()->year,
            'total_amount' => 1500,
            'paid_amount'  => 0,
            'balance_amount' => 1500,
            'bill_date'    => now()->toDateString(),
            'due_date'     => now()->addDays(10)->toDateString(),
            'status'       => 'pending',
        ]);

        Sanctum::actingAs($user);
        $res = $this->getJson('/api/v1/bills');
        $res->assertOk();
        $this->assertCount(1, $res->json('data'));
        $this->assertSame('MB-TEST-1', $res->json('data.0.bill_number'));
    }

    // ── Session / 401 ────────────────────────────────────────────────────────

    // ── Dashboard / payments smoke (exercises the reactivated date casts) ────

    public function test_dashboard_and_payments_render_with_payment_history(): void
    {
        $society = $this->makeSociety();
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));
        $user    = $this->makeResidentUser($society, $flat);

        \App\Models\Payment::create([
            'society_id'     => $society->id,
            'user_id'        => $user->id,
            'amount'         => 500,
            'bill_type'      => 'maintenance',
            'payment_method' => 'cash',
            'status'         => 'success',
            'payment_date'   => now()->toDateString(),
            'due_date'       => now()->toDateString(),
        ]);

        Sanctum::actingAs($user);

        // recent_activities calls payment_date->diffForHumans(); with the
        // dead casts() this was a 500 for anyone with a payment.
        $this->getJson('/api/v1/dashboard')->assertOk();
        $this->getJson('/api/v1/payments')->assertOk();
        $this->getJson('/api/v1/payments/history')->assertOk();
    }

    public function test_protected_route_returns_401_without_token(): void
    {
        $this->getJson('/api/v1/dashboard')->assertStatus(401);
    }
}
