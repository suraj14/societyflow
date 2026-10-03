<?php

namespace Tests\Feature;

use App\Models\Flat;
use App\Models\Owner;
use App\Models\Society;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VillaArea;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Reproduces the real Villa 160 / Royal Eden scenario: an Owner whose
 * flats.owner_id was never linked (documented gap, P1-7) and a Tenant who
 * has since moved in. Renting the unit must not hide it from its Owner,
 * must not touch the Owner's login/role, and must not let either side
 * reach the other's data or admin-only screens.
 */
class OwnerTenantAccessModelTest extends TestCase
{
    use RefreshDatabase;

    private function makeVillaScenario(): array
    {
        $society = $this->makeSociety('Royal Eden');
        $villaArea = VillaArea::create([
            'society_id' => $society->id,
            'name' => 'Suman Sadan',
            'total_villas' => 10,
            'status' => 'active',
        ]);

        $flat = Flat::create([
            'society_id' => $society->id,
            'property_type' => 'villa',
            'villa_area_id' => $villaArea->id,
            'flat_number' => '160',
            'floor' => 0,
            'status' => 'on_rent',
            // owner_id deliberately left null - the real, documented gap.
        ]);

        $owner = Owner::create([
            'society_id' => $society->id,
            'name' => 'Lokesh Suman',
            'email' => 'lokesh' . uniqid() . '@test.local',
            'phone' => '9876500001',
            'status' => 'active',
            'property_type' => 'villa',
            'villa_area_id' => $villaArea->id,
            'villa_no' => '160',
        ]);

        $ownerUser = $this->makeUserWithRole($society, 'Owner', [
            'name' => 'Lokesh Suman',
            'email' => $owner->email,
            'owner_id' => $owner->id,
        ]);

        $tenantUser = $this->makeUserWithRole($society, 'Tenant', [
            'name' => 'Piyus Chudarri',
            'email' => 'piyus' . uniqid() . '@test.local',
        ]);

        $tenant = Tenant::create([
            'society_id' => $society->id,
            'flat_id' => $flat->id,
            'user_id' => $tenantUser->id,
            'name' => 'Piyus Chudarri',
            'email' => $tenantUser->email,
            'phone' => '9876500002',
            'move_in_date' => now(),
            'status' => 'active',
        ]);
        $tenantUser->update(['tenant_id' => $tenant->id]);

        return [$society, $villaArea, $flat, $owner, $ownerUser, $tenant, $tenantUser];
    }

    public function test_owner_login_and_dashboard_show_the_rented_property_and_current_tenant(): void
    {
        [, , $flat, , $ownerUser, $tenant] = $this->makeVillaScenario();

        $response = $this->actingAs($ownerUser)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('160');
        $response->assertSee('Tenant Occupied');
        $response->assertSee('Piyus Chudarri');
        $response->assertDontSee('No Unit Assigned');

        // The auto-fabrication fallback must never fire for a real owner
        // whose unit is simply unlinked - it must find the real flat, not
        // invent a second one.
        $this->assertSame(1, Flat::where('society_id', $flat->society_id)->count());
    }

    public function test_renting_a_unit_does_not_disable_or_reassign_the_owner(): void
    {
        [, , $flat, $owner, $ownerUser] = $this->makeVillaScenario();

        $ownerUser->refresh();
        $this->assertSame('active', $ownerUser->status);
        $this->assertTrue($ownerUser->hasRole('Owner'));
        $this->assertFalse($ownerUser->hasRole('Tenant'));
        $this->assertSame($owner->id, $ownerUser->owner_id);
    }

    public function test_tenant_login_and_dashboard_show_the_assigned_property(): void
    {
        [, , $flat, , , , $tenantUser] = $this->makeVillaScenario();

        $response = $this->actingAs($tenantUser)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('160');
    }

    public function test_owner_cannot_reach_admin_management_screens(): void
    {
        [, , , , $ownerUser] = $this->makeVillaScenario();

        $this->actingAs($ownerUser)->get('/users')->assertForbidden();
        $this->actingAs($ownerUser)->get('/buildings')->assertForbidden();
        $this->actingAs($ownerUser)->get('/settings')->assertForbidden();
        $this->actingAs($ownerUser)->get('/owners')->assertForbidden();
    }

    public function test_tenant_cannot_reach_admin_or_owner_management_screens(): void
    {
        [, , , , , , $tenantUser] = $this->makeVillaScenario();

        $this->actingAs($tenantUser)->get('/users')->assertForbidden();
        $this->actingAs($tenantUser)->get('/buildings')->assertForbidden();
        $this->actingAs($tenantUser)->get('/settings')->assertForbidden();
        $this->actingAs($tenantUser)->get('/owners')->assertForbidden();
    }

    public function test_owner_and_tenant_cannot_see_another_societys_property(): void
    {
        [, , , , $ownerUser, , $tenantUser] = $this->makeVillaScenario();

        $otherSociety = $this->makeSociety('Other Society');
        $otherBuilding = $this->makeBuilding($otherSociety);
        $otherFlat = $this->makeFlat($otherSociety, $otherBuilding, '999');

        $this->actingAs($ownerUser)->get("/flats/{$otherFlat->id}")->assertForbidden();
        $this->actingAs($tenantUser)->get("/flats/{$otherFlat->id}")->assertForbidden();
    }
}
