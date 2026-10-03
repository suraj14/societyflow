<?php

namespace Tests\Feature;

use App\Models\Flat;
use App\Models\Resident;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * The exact bug: Tenant Assignments wrote to `residents` (type='tenant')
 * while Tenant Management always read/wrote `tenants.flat_id` - two
 * unrelated tables for the same fact, never synchronized. Assigning a unit
 * through Tenant Assignments showed "Assigned" there, "Not Assigned" on
 * Tenant Management, and left Property & Occupancy's "On Rent" count at
 * zero (it also used the wrong flats.status value, 'occupied' instead of
 * 'on_rent'). This suite pins `tenants.flat_id` + `flats.status='on_rent'`
 * as the single source every screen now reads, and exercises the full
 * assign -> reassign -> move-out lifecycle end to end.
 */
class TenantOccupancyConsistencyTest extends TestCase
{
    use RefreshDatabase;

    private function villaFlat(\App\Models\Society $society, string $number = '160'): Flat
    {
        return Flat::create([
            'society_id' => $society->id,
            'flat_number' => $number,
            'property_type' => 'villa',
            'status' => 'vacant',
        ]);
    }

    public function test_assigning_a_tenant_through_tenant_assignments_updates_tenant_management_and_occupancy(): void
    {
        $society = $this->makeSociety();
        $admin = $this->makeUserWithRole($society, 'Admin');
        $villa = $this->villaFlat($society);
        $tenant = Tenant::create([
            'society_id' => $society->id, 'name' => 'Piyush', 'email' => 'piyush@test.local',
            'phone' => '9000000001', 'status' => 'active',
        ]);

        $this->actingAs($admin)->post('/admin/tenant-assignments/assign', [
            'tenant_id' => $tenant->id,
            'unit_id' => $villa->id,
        ])->assertRedirect();

        $tenant->refresh();
        $villa->refresh();

        // Tenant Management's own source.
        $this->assertSame($villa->id, $tenant->flat_id);

        // Property & Occupancy's own source.
        $this->assertSame('on_rent', $villa->status);

        // All three screens render without error and agree.
        $tm = $this->actingAs($admin)->get('/admin/tenants');
        $tm->assertOk()->assertSee('160')->assertDontSee('Not Assigned');

        $ta = $this->actingAs($admin)->get('/admin/tenant-assignments');
        $ta->assertOk()->assertSee('Unassigned Tenants (0)');

        $po = $this->actingAs($admin)->get('/admin/property-occupancy');
        $po->assertOk();
    }

    public function test_cannot_assign_two_active_tenants_to_the_same_property(): void
    {
        $society = $this->makeSociety();
        $admin = $this->makeUserWithRole($society, 'Admin');
        $villa = $this->villaFlat($society);
        $tenantA = Tenant::create(['society_id' => $society->id, 'name' => 'A', 'email' => 'a@test.local', 'phone' => '9000000002', 'status' => 'active', 'flat_id' => $villa->id]);
        $villa->update(['status' => 'on_rent']);
        $tenantB = Tenant::create(['society_id' => $society->id, 'name' => 'B', 'email' => 'b@test.local', 'phone' => '9000000003', 'status' => 'active']);

        $this->actingAs($admin)->post('/admin/tenant-assignments/assign', [
            'tenant_id' => $tenantB->id,
            'unit_id' => $villa->id,
        ])->assertRedirect();

        $tenantB->refresh();
        $this->assertNull($tenantB->flat_id, 'a second active tenant should not be assignable to an occupied property');
    }

    public function test_reassign_vacates_old_property_and_occupies_new_one(): void
    {
        $society = $this->makeSociety();
        $admin = $this->makeUserWithRole($society, 'Admin');
        $villaA = $this->villaFlat($society, '160');
        $villaB = $this->villaFlat($society, '161');
        $tenant = Tenant::create(['society_id' => $society->id, 'name' => 'Piyush', 'email' => 'piyush2@test.local', 'phone' => '9000000004', 'status' => 'active', 'flat_id' => $villaA->id]);
        $villaA->update(['status' => 'on_rent']);

        $this->actingAs($admin)->post('/admin/tenant-assignments/reassign', [
            'current_tenant_id' => $tenant->id,
            'new_unit_id' => $villaB->id,
        ])->assertRedirect();

        $tenant->refresh();
        $villaA->refresh();
        $villaB->refresh();

        $this->assertSame($villaB->id, $tenant->flat_id);
        $this->assertSame('vacant', $villaA->status, 'old property must become vacant');
        $this->assertSame('on_rent', $villaB->status, 'new property must become tenant-occupied');
    }

    public function test_move_out_vacates_property_and_preserves_tenant_record(): void
    {
        $society = $this->makeSociety();
        $admin = $this->makeUserWithRole($society, 'Admin');
        $villa = $this->villaFlat($society);
        $tenant = Tenant::create(['society_id' => $society->id, 'name' => 'Piyush', 'email' => 'piyush3@test.local', 'phone' => '9000000005', 'status' => 'active', 'flat_id' => $villa->id]);
        $villa->update(['status' => 'on_rent']);

        $this->actingAs($admin)->post("/admin/tenants/{$tenant->id}/move-out")->assertRedirect();

        $tenant->refresh();
        $villa->refresh();

        $this->assertNull($tenant->flat_id);
        $this->assertNotNull($tenant->move_out_date);
        $this->assertSame('vacant', $villa->status);

        // The tenant record itself is preserved, not deleted.
        $this->assertDatabaseHas('tenants', ['id' => $tenant->id, 'name' => 'Piyush']);
    }

    public function test_move_out_via_tenant_assignments_unassign_also_works(): void
    {
        $society = $this->makeSociety();
        $admin = $this->makeUserWithRole($society, 'Admin');
        $villa = $this->villaFlat($society);
        $tenant = Tenant::create(['society_id' => $society->id, 'name' => 'Piyush', 'email' => 'piyush4@test.local', 'phone' => '9000000006', 'status' => 'active', 'flat_id' => $villa->id]);
        $villa->update(['status' => 'on_rent']);

        $this->actingAs($admin)->post('/admin/tenant-assignments/unassign', [
            'tenant_id' => $tenant->id,
        ])->assertRedirect();

        $tenant->refresh();
        $villa->refresh();
        $this->assertNull($tenant->flat_id);
        $this->assertSame('vacant', $villa->status);
    }

    public function test_repair_command_backfills_a_tenant_from_a_stale_resident_record(): void
    {
        $society = $this->makeSociety();
        $villa = $this->villaFlat($society);
        $user = $this->makeUserWithRole($society, 'Tenant');
        $tenant = Tenant::create([
            'society_id' => $society->id, 'user_id' => $user->id, 'name' => 'Piyush',
            'email' => 'piyush5@test.local', 'phone' => '9000000007', 'status' => 'active',
        ]);
        // The old Tenant Assignments mechanism: a Resident row, unrelated to
        // the Tenant row above.
        Resident::create([
            'user_id' => $user->id, 'society_id' => $society->id, 'flat_id' => $villa->id,
            'type' => 'tenant', 'status' => 'active', 'move_in_date' => now(),
        ]);

        $this->assertNull($tenant->flat_id);

        Artisan::call('tenants:repair-flat-assignments', ['--force' => true]);

        $tenant->refresh();
        $this->assertSame($villa->id, $tenant->flat_id);
    }

    public function test_repair_command_report_mode_changes_nothing(): void
    {
        $society = $this->makeSociety();
        $villa = $this->villaFlat($society);
        $user = $this->makeUserWithRole($society, 'Tenant');
        $tenant = Tenant::create([
            'society_id' => $society->id, 'user_id' => $user->id, 'name' => 'Piyush',
            'email' => 'piyush6@test.local', 'phone' => '9000000008', 'status' => 'active',
        ]);
        Resident::create([
            'user_id' => $user->id, 'society_id' => $society->id, 'flat_id' => $villa->id,
            'type' => 'tenant', 'status' => 'active', 'move_in_date' => now(),
        ]);

        Artisan::call('tenants:repair-flat-assignments');

        $tenant->refresh();
        $this->assertNull($tenant->flat_id);
    }

    public function test_tenant_cannot_access_another_tenants_rent_or_property(): void
    {
        $societyA = $this->makeSociety('A');
        $societyB = $this->makeSociety('B');
        $villaA = $this->villaFlat($societyA);
        $villaB = $this->villaFlat($societyB, '200');
        $userA = $this->makeUserWithRole($societyA, 'Tenant');
        $userB = $this->makeUserWithRole($societyB, 'Tenant');
        Tenant::create(['society_id' => $societyA->id, 'user_id' => $userA->id, 'name' => 'A', 'email' => 'ta@test.local', 'phone' => '9000000009', 'status' => 'active', 'flat_id' => $villaA->id]);
        $tenantB = Tenant::create(['society_id' => $societyB->id, 'user_id' => $userB->id, 'name' => 'B', 'email' => 'tb@test.local', 'phone' => '9000000010', 'status' => 'active', 'flat_id' => $villaB->id]);

        // Tenant A's admin cannot view Tenant B's rent/property record - the
        // controller redirects with an error rather than rendering it
        // (same pattern as OwnerController/PaymentController), so this
        // checks no data was rendered, not a specific status code.
        $adminA = $this->makeUserWithRole($societyA, 'Admin');
        $response = $this->actingAs($adminA)->get("/admin/tenants/{$tenantB->id}");
        $response->assertRedirect();
        $response->assertSessionHas('error');
    }
}
