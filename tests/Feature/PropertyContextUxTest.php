<?php

namespace Tests\Feature;

use App\Enums\SocietyPropertyType;
use App\Models\Facility;
use App\Models\FacilityBooking;
use App\Models\Flat;
use App\Models\MaintenanceBill;
use App\Models\Society;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * "Ask only what the system does not already know" - the property-context
 * UX simplification brief.
 *
 * Six views rendered a raw property_type <select> even for a society that
 * only supports one type, instead of the existing
 * partials/property-type-field.blade.php component, which already hides the
 * select and shows a read-only line for exactly that case (see
 * PropertyTypeEnforcementTest for the component's own coverage). These tests
 * prove all six now go through it, plus the backend-trust gaps found while
 * fixing them: two controllers accepted any same-society flat_id from a
 * resident, not just their own.
 */
class PropertyContextUxTest extends TestCase
{
    use RefreshDatabase;

    private function societyOf(SocietyPropertyType $type, string $name = 'Test Society'): Society
    {
        $society = $this->makeSociety($name);
        $society->update(['property_type' => $type->value]);

        return $society->fresh();
    }

    private function adminOf(SocietyPropertyType $type, string $name = 'Test Society'): array
    {
        $society = $this->societyOf($type, $name);

        return [$society, $this->makeUserWithRole($society, 'Admin')];
    }

    // ------------------------------------------------------- payments/create

    public function test_apartment_only_society_hides_property_type_on_payment_create(): void
    {
        [, $admin] = $this->adminOf(SocietyPropertyType::Apartment);

        $html = $this->actingAs($admin)->get('/payments/create')->assertOk()->getContent();

        // The component keeps the <select> in the DOM (its value still
        // submits) but hides it and shows this read-only line instead - the
        // same "ask only what the system doesn't know" contract every view
        // in this test now goes through.
        $this->assertStringContainsString('Property Type: Apartment', $html);
    }

    public function test_villa_only_society_hides_property_type_on_payment_create(): void
    {
        [, $admin] = $this->adminOf(SocietyPropertyType::Villa);

        $html = $this->actingAs($admin)->get('/payments/create')->assertOk()->getContent();

        $this->assertStringContainsString('Property Type: Villa', $html);
    }

    public function test_mixed_society_shows_property_type_on_payment_create(): void
    {
        [, $admin] = $this->adminOf(SocietyPropertyType::Both);

        $html = $this->actingAs($admin)->get('/payments/create')->assertOk()->getContent();

        $this->assertStringContainsString('Select Property Type', $html);
    }

    // -------------------------------------------------------- rents/create

    public function test_apartment_only_society_hides_property_type_on_rent_create(): void
    {
        [, $admin] = $this->adminOf(SocietyPropertyType::Apartment);

        $html = $this->actingAs($admin)->get('/admin/rents/create')->assertOk()->getContent();

        $this->assertStringContainsString('Property Type: Apartment', $html);
    }

    /**
     * The unit-loading API used to hard-reject a request with no
     * property_type at all - which is exactly what a single-type society's
     * page now sends, since the select is hidden. It must resolve the
     * society's one supported type itself instead of erroring.
     */
    public function test_rent_units_api_resolves_property_type_for_single_type_society(): void
    {
        [$society, $admin] = $this->adminOf(SocietyPropertyType::Apartment);
        $building = $this->makeBuilding($society);
        $flat = $this->makeFlat($society, $building, 'A-201');
        $flat->update(['status' => 'on_rent']);

        $tenantUser = $this->makeUserWithRole($society, 'Tenant');
        \App\Models\Tenant::create([
            'society_id' => $society->id,
            'user_id' => $tenantUser->id,
            'flat_id' => $flat->id,
            'name' => $tenantUser->name,
            'email' => $tenantUser->email,
            'phone' => $tenantUser->phone,
            'status' => 'active',
            'monthly_rent' => 20000,
            // Nullable on SQLite, NOT NULL without a default on MariaDB
            // (tracked as P1-19 in docs/TODO.md) - supplying it keeps this
            // fixture valid on both engines rather than papering over that
            // gap.
            'move_in_date' => now()->toDateString(),
        ]);

        $response = $this->actingAs($admin)->getJson('/admin/api/rent/units');

        $response->assertOk();
        $response->assertJsonFragment(['id' => $flat->id]);
    }

    // ------------------------------------------------------- tenants/create

    public function test_apartment_only_society_hides_property_type_on_tenant_create(): void
    {
        [, $admin] = $this->adminOf(SocietyPropertyType::Apartment);

        $html = $this->actingAs($admin)->get('/admin/tenants/create')->assertOk()->getContent();

        $this->assertStringContainsString('Property Type: Apartment', $html);
    }

    // -------------------------------------------------- facility-bookings

    private function grantManageFacilities($user): void
    {
        Permission::firstOrCreate(['name' => 'manage_facilities', 'guard_name' => 'web']);
        $user->givePermissionTo('manage_facilities');
    }

    public function test_apartment_only_society_hides_property_type_on_facility_booking_create(): void
    {
        [, $admin] = $this->adminOf(SocietyPropertyType::Apartment);
        $this->grantManageFacilities($admin);

        $html = $this->actingAs($admin)->get('/facility-bookings/create')->assertOk()->getContent();

        $this->assertStringContainsString('Property Type: Apartment', $html);
    }

    /**
     * create() already builds this resident a pre-populated single-property
     * list with no property_type prompt at all - confirms the resident path
     * was already correct before this pass, and stays correct.
     */
    public function test_owner_with_one_property_sees_no_property_type_on_facility_booking_create(): void
    {
        $society = $this->societyOf(SocietyPropertyType::Apartment);
        $building = $this->makeBuilding($society);
        $flat = $this->makeFlat($society, $building, 'A-301');
        $owner = $this->makeResidentUser($society, $flat);
        $this->makeRole('Apartment Owner');
        $owner->assignRole('Apartment Owner');

        $html = $this->actingAs($owner)->get('/facility-bookings/create')->assertOk()->getContent();

        $this->assertStringNotContainsString('Property Type', $html);
    }

    /**
     * A same-society resident could otherwise hand-craft flat_id and book a
     * facility against a neighbour's property - create() only ever lists the
     * requester's own flat as an option, but store() had nothing stopping a
     * forged request naming someone else's.
     */
    public function test_facility_booking_rejects_a_flat_the_resident_does_not_occupy(): void
    {
        $society = $this->societyOf(SocietyPropertyType::Apartment);
        $building = $this->makeBuilding($society);
        $ownFlat = $this->makeFlat($society, $building, 'A-401');
        $neighboursFlat = $this->makeFlat($society, $building, 'A-402');

        $owner = $this->makeResidentUser($society, $ownFlat);
        $this->makeRole('Apartment Owner');
        $owner->assignRole('Apartment Owner');

        $facility = Facility::create([
            'society_id' => $society->id,
            'name' => 'Clubhouse',
            'status' => 'active',
            'booking_charge' => 0,
        ]);

        $response = $this->actingAs($owner)->post('/facility-bookings', [
            'facility_id' => $facility->id,
            'flat_id' => $neighboursFlat->id,
            'booking_date' => now()->addDay()->toDateString(),
            'start_time' => '10:00',
            'end_time' => '11:00',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('facility_bookings', ['flat_id' => $neighboursFlat->id]);
    }

    /** Admin/Staff booking on behalf of someone else must still work. */
    public function test_admin_with_manage_facilities_can_book_for_any_flat_in_their_society(): void
    {
        [$society, $admin] = $this->adminOf(SocietyPropertyType::Apartment);
        $this->grantManageFacilities($admin);
        $building = $this->makeBuilding($society);
        $flat = $this->makeFlat($society, $building, 'A-501');

        $facility = Facility::create([
            'society_id' => $society->id,
            'name' => 'Clubhouse',
            'status' => 'active',
            'booking_charge' => 0,
        ]);

        $this->actingAs($admin)->post('/facility-bookings', [
            'facility_id' => $facility->id,
            'flat_id' => $flat->id,
            'booking_date' => now()->addDay()->toDateString(),
            'start_time' => '10:00',
            'end_time' => '11:00',
        ])->assertRedirect(route('facility-bookings.index'));

        $this->assertDatabaseHas('facility_bookings', ['flat_id' => $flat->id]);
    }

    // -------------------------------------------------------- residents/create

    public function test_apartment_only_society_hides_property_type_on_residents_create(): void
    {
        [, $admin] = $this->adminOf(SocietyPropertyType::Apartment);

        $html = $this->actingAs($admin)->get('/residents/create')->assertOk()->getContent();

        $this->assertStringContainsString('Select Apartment/Villa: Apartment', $html);
    }

    /**
     * The form used to promise "A user account will be created automatically
     * with the default password: password" - but store() only ever writes a
     * Resident row (its own docblock documents this screen as legacy/
     * orphaned; Owner/Tenant creation is the supported path for a resident
     * who needs to log in). The UI now says so instead of promising a login
     * that never appears, and store()'s actual behaviour is pinned here so
     * the two cannot drift apart silently again.
     */
    public function test_residents_create_does_not_promise_a_login_account(): void
    {
        [, $admin] = $this->adminOf(SocietyPropertyType::Apartment);

        $html = $this->actingAs($admin)->get('/residents/create')->assertOk()->getContent();

        $this->assertStringNotContainsString('A user account will be created', $html);
        $this->assertStringContainsString('No login account is created here', $html);
    }

    public function test_resident_store_creates_no_user_account(): void
    {
        $society = $this->societyOf(SocietyPropertyType::Apartment);
        $building = $this->makeBuilding($society);
        $flat = $this->makeFlat($society, $building, 'A-701');
        $flat->update(['status' => 'vacant']);
        $admin = $this->makeUserWithRole($society, 'Admin');

        $usersBefore = \App\Models\User::count();

        $this->actingAs($admin)->post('/residents', [
            'name' => 'Standalone Resident',
            'email' => 'standalone' . uniqid() . '@test.local',
            'phone' => '9876500000',
            'flat_id' => $flat->id,
            'move_in_date' => now()->toDateString(),
        ])->assertRedirect(route('residents.index'));

        $this->assertSame($usersBefore, \App\Models\User::count());
        $this->assertDatabaseHas('residents', ['flat_id' => $flat->id, 'status' => 'active']);
    }

    /**
     * Route::redirect('/residents', '/owners') matched every HTTP verb via
     * any(), which is what broke POST above. Fixed by naming a GET-only
     * route residents.index instead - this pins that GET still redirects,
     * so the "Residents deprecated" behaviour the comment always intended
     * did not regress while fixing the POST collision.
     */
    public function test_residents_index_still_redirects_to_owners(): void
    {
        $society = $this->societyOf(SocietyPropertyType::Apartment);
        $admin = $this->makeUserWithRole($society, 'Admin');

        $this->actingAs($admin)->get('/residents')->assertRedirect('/owners');
    }

    // -------------------------------------------------------- utility-bills

    public function test_apartment_only_society_hides_property_type_on_utility_bills_index(): void
    {
        [, $admin] = $this->adminOf(SocietyPropertyType::Apartment);

        $html = $this->actingAs($admin)->get('/utility-bills')->assertOk()->getContent();

        $this->assertStringContainsString('Property Type: Apartment', $html);
    }

    // ------------------------------------------------- payments: bill-first

    private function billOn(Flat $flat, array $overrides = []): MaintenanceBill
    {
        return MaintenanceBill::create(array_merge([
            'society_id' => $flat->society_id,
            'flat_id' => $flat->id,
            'month' => now()->format('F'),
            'year' => now()->year,
            'bill_date' => now()->toDateString(),
            'due_date' => now()->addDays(10)->toDateString(),
            'maintenance_amount' => 2500,
            'water_charges' => 0,
            'electricity_charges' => 0,
            'parking_charges' => 0,
            'penalty_amount' => 0,
            'other_charges' => 0,
            'paid_amount' => 0,
            'status' => 'pending',
            'bill_type' => 'Maintenance',
        ], $overrides));
    }

    public function test_outstanding_bills_endpoint_lists_a_flats_unpaid_bill(): void
    {
        $society = $this->societyOf(SocietyPropertyType::Apartment);
        $building = $this->makeBuilding($society);
        $flat = $this->makeFlat($society, $building, 'A-601');
        $owner = $this->makeResidentUser($society, $flat);
        $this->makeRole('Apartment Owner');
        $owner->assignRole('Apartment Owner');

        $bill = $this->billOn($flat);

        $response = $this->actingAs($owner)->getJson("/api/payments/outstanding-bills?flat_id={$flat->id}");

        $response->assertOk();
        $response->assertJsonFragment(['id' => $bill->id, 'outstanding_amount' => 2500.0]);
    }

    public function test_payment_store_resolves_bill_type_and_due_date_from_the_selected_bill(): void
    {
        $society = $this->societyOf(SocietyPropertyType::Apartment);
        $building = $this->makeBuilding($society);
        $flat = $this->makeFlat($society, $building, 'A-602');
        $owner = $this->makeResidentUser($society, $flat);
        $this->makeRole('Apartment Owner');
        $owner->assignRole('Apartment Owner');

        $bill = $this->billOn($flat, ['maintenance_amount' => 3000]);

        $this->actingAs($owner)->post('/payments', [
            'flat_id' => $flat->id,
            'maintenance_bill_id' => $bill->id,
            'amount' => 3000,
            'payment_method' => 'cash',
            'payment_date' => now()->toDateString(),
        ])->assertRedirect(route('payments.index'));

        $this->assertDatabaseHas('payments', [
            'flat_id' => $flat->id,
            'maintenance_bill_id' => $bill->id,
            'bill_type' => 'Maintenance',
        ]);
    }

    /** A bill for one flat must never attach to a payment for another. */
    public function test_payment_store_rejects_a_bill_belonging_to_a_different_flat(): void
    {
        $society = $this->societyOf(SocietyPropertyType::Apartment);
        $building = $this->makeBuilding($society);
        $ownFlat = $this->makeFlat($society, $building, 'A-603');
        $otherFlat = $this->makeFlat($society, $building, 'A-604');
        $owner = $this->makeResidentUser($society, $ownFlat);
        $this->makeRole('Apartment Owner');
        $owner->assignRole('Apartment Owner');

        $otherBill = $this->billOn($otherFlat);

        $response = $this->actingAs($owner)->post('/payments', [
            'flat_id' => $ownFlat->id,
            'maintenance_bill_id' => $otherBill->id,
            'amount' => 2500,
            'payment_method' => 'cash',
            'payment_date' => now()->toDateString(),
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('error');
        $this->assertDatabaseMissing('payments', ['maintenance_bill_id' => $otherBill->id]);
    }

    // ---------------------------------------------- property & occupancy hub

    /** Brief §15/§16: one entry point, gated by what the society supports. */
    public function test_property_occupancy_hub_shows_only_supported_sections(): void
    {
        [$villaSociety, $villaAdmin] = $this->adminOf(SocietyPropertyType::Villa, 'Villa Society');
        $villaHtml = $this->actingAs($villaAdmin)->get('/admin/property-occupancy')->assertOk()->getContent();

        $this->assertStringContainsString('Villa Assignments', $villaHtml);
        $this->assertStringNotContainsString('Apartment Assignments', $villaHtml);
        $this->assertStringContainsString('Tenant Assignments', $villaHtml);

        [, $apartmentAdmin] = $this->adminOf(SocietyPropertyType::Apartment, 'Apartment Society');
        $apartmentHtml = $this->actingAs($apartmentAdmin)->get('/admin/property-occupancy')->assertOk()->getContent();

        $this->assertStringContainsString('Apartment Assignments', $apartmentHtml);
        $this->assertStringNotContainsString('Villa Assignments', $apartmentHtml);
    }

    public function test_property_occupancy_hub_is_linked_from_the_sidebar(): void
    {
        [, $admin] = $this->adminOf(SocietyPropertyType::Apartment);

        $html = $this->actingAs($admin)->get('/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString(route('admin.property-occupancy.index'), $html);
    }

    /**
     * A same-society resident could otherwise hand-craft flat_id and record
     * a payment against a neighbour's property.
     */
    public function test_payment_store_rejects_a_flat_the_resident_does_not_occupy(): void
    {
        $society = $this->societyOf(SocietyPropertyType::Apartment);
        $building = $this->makeBuilding($society);
        $ownFlat = $this->makeFlat($society, $building, 'A-605');
        $neighboursFlat = $this->makeFlat($society, $building, 'A-606');
        $owner = $this->makeResidentUser($society, $ownFlat);
        $this->makeRole('Apartment Owner');
        $owner->assignRole('Apartment Owner');

        $response = $this->actingAs($owner)->post('/payments', [
            'flat_id' => $neighboursFlat->id,
            'amount' => 1500,
            'payment_method' => 'cash',
            'payment_date' => now()->toDateString(),
            'bill_type' => 'Maintenance',
            'due_date' => now()->addDays(7)->toDateString(),
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('error');
        $this->assertDatabaseMissing('payments', ['flat_id' => $neighboursFlat->id]);
    }
}
