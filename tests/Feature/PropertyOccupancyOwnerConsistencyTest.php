<?php

namespace Tests\Feature;

use App\Models\Flat;
use App\Models\Owner;
use App\Models\Tenant;
use App\Models\VillaArea;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Owner Management (resources/views/owners/show.blade.php) has only ever
 * read a property from the owner record's OWN denormalized fields
 * (villa_area_id + villa_no for a villa, building_id + flat_no for an
 * apartment) - never from flats.owner_id. Property & Occupancy used
 * Flat::ownerModel(), a plain belongsTo(Owner::class, 'owner_id') - so any
 * flat whose owner_id was never populated (108 of 121 rows in the dev
 * database - see UserFlatResolver's own docblock, TODO.md P1-7) showed
 * "Owner: -" there while Owner Management correctly showed the owner via
 * the address-matching fields. This suite pins Flat::resolvedOwner(),
 * which checks flats.owner_id first and falls back to the same
 * address-matching UserFlatResolver already documents for the reverse
 * direction (user -> flat).
 */
class PropertyOccupancyOwnerConsistencyTest extends TestCase
{
    use RefreshDatabase;

    private function villa(\App\Models\Society $society, VillaArea $area, string $number = '160'): Flat
    {
        return Flat::create([
            'society_id' => $society->id,
            'villa_area_id' => $area->id,
            'flat_number' => $number,
            'property_type' => 'villa',
            'status' => 'vacant',
        ]);
    }

    /** The exact regression scenario: Owner=Lokesh Suman, Property=Villa 160, Tenant=Piyush Chudarri. */
    public function test_property_with_unlinked_owner_and_active_tenant_shows_both_correctly(): void
    {
        $society = $this->makeSociety();
        $admin = $this->makeUserWithRole($society, 'Admin');
        $area = VillaArea::create(['society_id' => $society->id, 'name' => 'Suman Sadan']);
        $villa = $this->villa($society, $area, '160');

        // Owner linked only through the denormalized address fields, exactly
        // like the real "Lokesh Suman" record - flats.owner_id stays null.
        $owner = Owner::create([
            'society_id' => $society->id, 'name' => 'Lokesh Suman', 'email' => 'lokesh@test.local',
            'phone' => '9000000020', 'status' => 'active', 'property_type' => 'villa',
            'villa_area_id' => $area->id, 'villa_no' => '160',
        ]);

        $tenant = Tenant::create([
            'society_id' => $society->id, 'name' => 'Piyush Chudarri', 'email' => 'piyush@test.local',
            'phone' => '9000000021', 'status' => 'active', 'flat_id' => $villa->id,
        ]);
        $villa->update(['status' => 'on_rent']);

        $this->assertNull($villa->fresh()->owner_id, 'the regression must reproduce owner_id staying unset');

        $resolved = $villa->fresh()->resolvedOwner();
        $this->assertNotNull($resolved);
        $this->assertSame('Lokesh Suman', $resolved->name);

        $html = $this->actingAs($admin)->get('/admin/property-occupancy')->assertOk()->getContent();
        $this->assertStringContainsString('Lokesh Suman', $html);
        $this->assertStringContainsString('Piyush Chudarri', $html);
        $this->assertStringContainsString('Tenant Occupied', $html);
    }

    public function test_property_with_owner_only_shows_owner_occupied(): void
    {
        $society = $this->makeSociety();
        $admin = $this->makeUserWithRole($society, 'Admin');
        $area = VillaArea::create(['society_id' => $society->id, 'name' => 'Area 72']);
        $villa = $this->villa($society, $area, '72');
        $villa->update(['status' => 'occupied']);
        Owner::create([
            'society_id' => $society->id, 'name' => 'Mahesh', 'email' => 'mahesh@test.local',
            'phone' => '9000000022', 'status' => 'active', 'property_type' => 'villa',
            'villa_area_id' => $area->id, 'villa_no' => '72',
        ]);

        $this->assertSame('Mahesh', $villa->fresh()->resolvedOwner()->name);

        $html = $this->actingAs($admin)->get('/admin/property-occupancy')->assertOk()->getContent();
        $this->assertStringContainsString('Owner Occupied', $html);
    }

    public function test_vacant_property_shows_no_owner_and_no_tenant(): void
    {
        $society = $this->makeSociety();
        $admin = $this->makeUserWithRole($society, 'Admin');
        $area = VillaArea::create(['society_id' => $society->id, 'name' => 'Empty Area']);
        $villa = $this->villa($society, $area, '99');

        $this->assertNull($villa->fresh()->resolvedOwner());

        $html = $this->actingAs($admin)->get('/admin/property-occupancy')->assertOk()->getContent();
        $this->assertStringContainsString('Vacant', $html);
    }

    public function test_directly_linked_owner_id_is_still_preferred_over_address_matching(): void
    {
        $society = $this->makeSociety();
        $area = VillaArea::create(['society_id' => $society->id, 'name' => 'Area X']);
        $villa = $this->villa($society, $area, '55');
        $correctOwner = Owner::create([
            'society_id' => $society->id, 'name' => 'Correct Owner', 'email' => 'correct@test.local',
            'phone' => '9000000023', 'status' => 'active', 'property_type' => 'villa',
        ]);
        // A different owner record that happens to carry the same address
        // fields must NOT win once a direct link exists.
        Owner::create([
            'society_id' => $society->id, 'name' => 'Stale Address Match', 'email' => 'stale@test.local',
            'phone' => '9000000024', 'status' => 'active', 'property_type' => 'villa',
            'villa_area_id' => $area->id, 'villa_no' => '55',
        ]);
        $villa->update(['owner_id' => $correctOwner->id]);

        $this->assertSame('Correct Owner', $villa->fresh()->resolvedOwner()->name);
    }

    public function test_on_rent_counter_reflects_active_tenant_not_owner(): void
    {
        $society = $this->makeSociety();
        $admin = $this->makeUserWithRole($society, 'Admin');
        $area = VillaArea::create(['society_id' => $society->id, 'name' => 'Counter Area']);

        $rented = $this->villa($society, $area, '160');
        Tenant::create(['society_id' => $society->id, 'name' => 'T', 'email' => 't@test.local', 'phone' => '9000000025', 'status' => 'active', 'flat_id' => $rented->id]);
        $rented->update(['status' => 'on_rent']);

        $ownerOccupied = $this->villa($society, $area, '72');
        $ownerOccupied->update(['status' => 'occupied']);

        $html = $this->actingAs($admin)->get('/admin/property-occupancy')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/On Rent.*?(?:\s|<[^>]*>)*1/s', $html);
    }

    public function test_owner_management_and_property_occupancy_agree_on_the_same_owner(): void
    {
        $society = $this->makeSociety();
        $admin = $this->makeUserWithRole($society, 'Admin');
        $area = VillaArea::create(['society_id' => $society->id, 'name' => 'Agreement Area']);
        $villa = $this->villa($society, $area, '160');
        $owner = Owner::create([
            'society_id' => $society->id, 'name' => 'Consistency Owner', 'email' => 'consistency@test.local',
            'phone' => '9000000026', 'status' => 'active', 'property_type' => 'villa',
            'villa_area_id' => $area->id, 'villa_no' => '160',
        ]);

        $ownerPage = $this->actingAs($admin)->get("/owners/{$owner->id}")->assertOk()->getContent();
        $this->assertStringContainsString('160', $ownerPage);

        $occupancyPage = $this->actingAs($admin)->get('/admin/property-occupancy')->assertOk()->getContent();
        $this->assertStringContainsString('Consistency Owner', $occupancyPage);
    }

    public function test_cross_society_owner_is_never_matched_by_address(): void
    {
        $societyA = $this->makeSociety('A');
        $societyB = $this->makeSociety('B');
        $area = VillaArea::create(['society_id' => $societyA->id, 'name' => 'Shared-looking Area']);
        $villaA = $this->villa($societyA, $area, '160');

        // A same-numbered villa in a different society must never match.
        $areaB = VillaArea::create(['society_id' => $societyB->id, 'name' => 'Other Area']);
        Owner::create([
            'society_id' => $societyB->id, 'name' => 'Wrong Society Owner', 'email' => 'wrong@test.local',
            'phone' => '9000000027', 'status' => 'active', 'property_type' => 'villa',
            'villa_area_id' => $areaB->id, 'villa_no' => '160',
        ]);

        $this->assertNull($villaA->fresh()->resolvedOwner());
    }
}
