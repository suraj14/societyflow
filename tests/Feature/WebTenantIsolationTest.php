<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\Facility;
use App\Models\Flat;
use App\Models\Owner;
use App\Models\Payment;
use App\Models\Society;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tenant isolation for the Blade side of the application.
 *
 * The API had isolation tests; the web controllers had none, and that is
 * exactly where the holes were. Route-model binding resolves any ID in the
 * table, so every one of these routes returned another society's row until
 * the controller checked ownership.
 *
 * Each test here failed before the AuthorizesSocietyAccess fix.
 */
class WebTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Society $societyA;
    private Society $societyB;
    private User $adminA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->societyA = $this->makeSociety('Society A');
        $this->societyB = $this->makeSociety('Society B');
        $this->adminA   = $this->makeUserWithRole($this->societyA, 'Admin');
    }

    private function flatIn(Society $society, string $number = '101'): Flat
    {
        return $this->makeFlat($society, $this->makeBuilding($society), $number);
    }

    // ── Flats ────────────────────────────────────────────────────────────────

    public function test_admin_cannot_view_a_flat_from_another_society(): void
    {
        $flatB = $this->flatIn($this->societyB);

        $this->actingAs($this->adminA)->get("/flats/{$flatB->id}")->assertForbidden();
    }

    public function test_admin_cannot_edit_a_flat_from_another_society(): void
    {
        $flatB = $this->flatIn($this->societyB);

        $this->actingAs($this->adminA)->get("/flats/{$flatB->id}/edit")->assertForbidden();
    }

    public function test_admin_cannot_delete_a_flat_from_another_society(): void
    {
        $flatB = $this->flatIn($this->societyB);

        $this->actingAs($this->adminA)->delete("/flats/{$flatB->id}")->assertForbidden();
        $this->assertDatabaseHas('flats', ['id' => $flatB->id]);
    }

    public function test_admin_can_still_view_a_flat_in_their_own_society(): void
    {
        $flatA = $this->flatIn($this->societyA, '201');

        $this->actingAs($this->adminA)->get("/flats/{$flatA->id}")->assertOk();
    }

    // ── Owners ───────────────────────────────────────────────────────────────

    public function test_admin_cannot_view_an_owner_from_another_society(): void
    {
        $ownerB = Owner::create([
            'society_id' => $this->societyB->id,
            'name'       => 'Owner B',
            'email'      => 'ownerb@test.local',
            'status'     => 'active',
        ]);

        $this->actingAs($this->adminA)->get("/owners/{$ownerB->id}")->assertForbidden();
    }

    public function test_admin_cannot_delete_an_owner_from_another_society(): void
    {
        $ownerB = Owner::create([
            'society_id' => $this->societyB->id,
            'name'       => 'Owner B',
            'email'      => 'ownerb2@test.local',
            'status'     => 'active',
        ]);

        $this->actingAs($this->adminA)->delete("/owners/{$ownerB->id}")->assertForbidden();
        $this->assertDatabaseHas('owners', ['id' => $ownerB->id]);
    }

    // ── Facilities ───────────────────────────────────────────────────────────

    public function test_resident_cannot_delete_a_facility(): void
    {
        $flatA    = $this->flatIn($this->societyA, '301');
        $resident = $this->makeResidentUser($this->societyA, $flatA);
        $resident->assignRole($this->makeRole('Apartment Owner')->name);

        $facilityA = Facility::create([
            'society_id' => $this->societyA->id,
            'name'       => 'Gym',
            'status'     => 'active',
        ]);

        $this->actingAs($resident)->delete("/facilities/{$facilityA->id}")->assertForbidden();
        $this->assertDatabaseHas('facilities', ['id' => $facilityA->id]);
    }

    public function test_admin_cannot_edit_a_facility_from_another_society(): void
    {
        $facilityB = Facility::create([
            'society_id' => $this->societyB->id,
            'name'       => 'Pool',
            'status'     => 'active',
        ]);

        $this->actingAs($this->adminA)->get("/facilities/{$facilityB->id}/edit")->assertForbidden();
    }

    // ── Payments ─────────────────────────────────────────────────────────────

    private function makePayment(Society $society, ?User $user): Payment
    {
        return Payment::create([
            'society_id'     => $society->id,
            'user_id'        => $user?->id,
            'payment_id'     => 'PAY-' . uniqid(),
            'amount'         => 1500,
            'payment_method' => 'cash',
            'status'         => 'success',
            'payment_date'   => now()->toDateString(),
            'bill_type'      => 'Maintenance',
            'due_date'       => now()->toDateString(),
        ]);
    }

    /**
     * The original check was `if ($user->hasRole('Admin') && $payment->society_id !== ...)`,
     * so every non-Admin role skipped it entirely.
     */
    public function test_resident_cannot_view_another_societys_payment(): void
    {
        $flatA    = $this->flatIn($this->societyA, '401');
        $resident = $this->makeResidentUser($this->societyA, $flatA);
        $resident->assignRole($this->makeRole('Tenant')->name);

        $paymentB = $this->makePayment($this->societyB, null);

        $this->actingAs($resident)->get("/payments/{$paymentB->id}")->assertForbidden();
    }

    public function test_resident_cannot_view_another_residents_payment(): void
    {
        $flatA     = $this->flatIn($this->societyA, '402');
        $residentA = $this->makeResidentUser($this->societyA, $flatA);
        $residentA->assignRole($this->makeRole('Tenant')->name);
        $residentB = $this->makeResidentUser($this->societyA, $flatA);

        $paymentOfB = $this->makePayment($this->societyA, $residentB);

        $this->actingAs($residentA)->get("/payments/{$paymentOfB->id}")->assertForbidden();
    }

    public function test_resident_cannot_delete_another_residents_payment(): void
    {
        $flatA     = $this->flatIn($this->societyA, '403');
        $residentA = $this->makeResidentUser($this->societyA, $flatA);
        $residentA->assignRole($this->makeRole('Tenant')->name);
        $residentB = $this->makeResidentUser($this->societyA, $flatA);

        $paymentOfB = $this->makePayment($this->societyA, $residentB);

        $this->actingAs($residentA)->delete("/payments/{$paymentOfB->id}")->assertForbidden();
        $this->assertDatabaseHas('payments', ['id' => $paymentOfB->id]);
    }

    public function test_resident_can_still_view_their_own_payment(): void
    {
        $flatA    = $this->flatIn($this->societyA, '404');
        $resident = $this->makeResidentUser($this->societyA, $flatA);
        $resident->assignRole($this->makeRole('Tenant')->name);

        $own = $this->makePayment($this->societyA, $resident);

        $this->actingAs($resident)->get("/payments/{$own->id}")->assertOk();
    }

    public function test_admin_cannot_view_another_societys_payment(): void
    {
        $paymentB = $this->makePayment($this->societyB, null);

        $this->actingAs($this->adminA)->get("/payments/{$paymentB->id}")->assertForbidden();
    }

    // ── Complaints ───────────────────────────────────────────────────────────

    private function makeComplaint(Society $society, User $author, Flat $flat): Complaint
    {
        $category = ComplaintCategory::create([
            'society_id' => $society->id,
            'name'       => 'Plumbing ' . uniqid(),
            'status'     => 'active',
        ]);

        return Complaint::create([
            'society_id'            => $society->id,
            'complaint_category_id' => $category->id,
            'flat_id'               => $flat->id,
            'created_by'            => $author->id,
            'title'                 => 'Leak',
            'description'           => 'Water everywhere',
            'status'                => 'open',
            'priority'              => 'medium',
        ]);
    }

    public function test_resident_cannot_edit_another_residents_complaint(): void
    {
        $flatA     = $this->flatIn($this->societyA, '501');
        $authorA   = $this->makeResidentUser($this->societyA, $flatA);
        $intruder  = $this->makeResidentUser($this->societyA, $flatA);
        $intruder->assignRole($this->makeRole('Tenant')->name);

        $complaint = $this->makeComplaint($this->societyA, $authorA, $flatA);

        $this->actingAs($intruder)->get("/complaints/{$complaint->id}/edit")->assertForbidden();
    }

    public function test_resident_cannot_delete_another_residents_complaint(): void
    {
        $flatA    = $this->flatIn($this->societyA, '502');
        $authorA  = $this->makeResidentUser($this->societyA, $flatA);
        $intruder = $this->makeResidentUser($this->societyA, $flatA);
        $intruder->assignRole($this->makeRole('Tenant')->name);

        $complaint = $this->makeComplaint($this->societyA, $authorA, $flatA);

        $this->actingAs($intruder)->delete("/complaints/{$complaint->id}")->assertForbidden();
        $this->assertDatabaseHas('complaints', ['id' => $complaint->id]);
    }

    public function test_admin_cannot_update_a_complaint_from_another_society(): void
    {
        $flatB   = $this->flatIn($this->societyB, '503');
        $authorB = $this->makeResidentUser($this->societyB, $flatB);

        $complaintB = $this->makeComplaint($this->societyB, $authorB, $flatB);

        $this->actingAs($this->adminA)->put("/complaints/{$complaintB->id}", [
            'complaint_category_id' => $complaintB->complaint_category_id,
            'title'                 => 'Hijacked',
            'description'           => 'Hijacked',
            'priority'              => 'low',
            'status'                => 'closed',
        ])->assertForbidden();

        $this->assertSame('Leak', $complaintB->fresh()->title);
    }

    /**
     * complaints.update ran `$complaint->update($request->all())`, so a
     * request could rewrite society_id, created_by, assigned_to and flat_id.
     */
    public function test_complaint_update_cannot_reassign_ownership_fields(): void
    {
        $flatA   = $this->flatIn($this->societyA, '504');
        $authorA = $this->makeResidentUser($this->societyA, $flatA);

        $complaint = $this->makeComplaint($this->societyA, $authorA, $flatA);

        $this->actingAs($this->adminA)->put("/complaints/{$complaint->id}", [
            'complaint_category_id' => $complaint->complaint_category_id,
            'title'                 => 'Legit update',
            'description'           => 'Legit update',
            'priority'              => 'high',
            'status'                => 'in_progress',
            // Injected fields that must be ignored.
            'society_id'            => $this->societyB->id,
            'created_by'            => $this->adminA->id,
        ])->assertRedirect();

        $fresh = $complaint->fresh();
        $this->assertSame($this->societyA->id, $fresh->society_id, 'society_id was mass-assignable');
        $this->assertSame($authorA->id, $fresh->created_by, 'created_by was mass-assignable');
        $this->assertSame('Legit update', $fresh->title, 'the legitimate part of the update was lost');
    }
}
