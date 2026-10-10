<?php

namespace Tests\Feature;

use App\Models\Flat;
use App\Models\Resident;
use App\Models\Society;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CLAUDE.md section 3: "scope by default, exempt Super Admin explicitly". The tenant and resident
 * screens compared a record's society to getSocietyId() - null for a Super Admin - so a Super Admin
 * saw an EMPTY tenant list and "Tenant not found" for every record (found by the role crawl).
 * Society Admins must remain confined to their own society, and nothing may create a
 * society-less record on a Super Admin's behalf.
 */
class SuperAdminSocietyRecordAccessTest extends TestCase
{
    use RefreshDatabase;

    private Society $a;
    private Society $b;
    private User $super;
    private User $adminA;
    private Tenant $tenantA;
    private Tenant $tenantB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->a = $this->makeSociety('Alpha');
        $this->b = $this->makeSociety('Beta');
        $this->super = $this->makeUserWithRole($this->a, 'Super Admin', ['society_id' => null]);
        $this->adminA = $this->makeUserWithRole($this->a, 'Admin');
        $this->tenantA = $this->tenant($this->a, 'Tina Alpha');
        $this->tenantB = $this->tenant($this->b, 'Tom Beta');
    }

    private function tenant(Society $society, string $name): Tenant
    {
        $flat = $this->makeFlat($society, $this->makeBuilding($society, 'T' . uniqid()), (string) random_int(100, 999));
        $user = $this->makeUserWithRole($society, 'Tenant', ['name' => $name]);

        return Tenant::create(['society_id' => $society->id, 'flat_id' => $flat->id, 'user_id' => $user->id, 'name' => $name, 'email' => uniqid() . '@t.local', 'phone' => '0000000001', 'move_in_date' => '2026-09-01', 'status' => 'active']);
    }

    public function test_a_super_admin_sees_the_tenants_of_every_society(): void
    {
        $tenants = $this->actingAs($this->super)->get(route('admin.tenants.index'))->assertOk()->viewData('tenants');

        $this->assertEqualsCanonicalizing([$this->tenantA->id, $this->tenantB->id], $tenants->pluck('id')->all());
    }

    public function test_a_super_admin_can_open_and_edit_any_societys_tenant(): void
    {
        foreach ([$this->tenantA, $this->tenantB] as $tenant) {
            $this->actingAs($this->super)->get(route('admin.tenants.show', $tenant))->assertOk()->assertSee($tenant->name);
            $edit = $this->actingAs($this->super)->get(route('admin.tenants.edit', $tenant))->assertOk();
            // the unit picker is built from the TENANT's society, never from the (absent) society of the viewer
            $this->assertContains((int) $tenant->flat_id, $edit->viewData('availableUnits')->pluck('id')->map(fn ($i) => (int) $i)->all());
        }
    }

    public function test_a_super_admin_can_move_a_tenant_out_in_any_society(): void
    {
        $this->actingAs($this->super)->post(route('admin.tenants.move-out', $this->tenantB))->assertSessionHas('success');

        $this->assertNull($this->tenantB->fresh()->flat_id);
    }

    public function test_a_super_admin_cannot_create_a_society_less_tenant(): void
    {
        $before = Tenant::count();

        $this->actingAs($this->super)->get(route('admin.tenants.create'))->assertRedirect(route('admin.tenants.index'))->assertSessionHas('error');
        $this->actingAs($this->super)->post(route('admin.tenants.store'), ['name' => 'X'])->assertRedirect(route('admin.tenants.index'))->assertSessionHas('error');

        $this->assertSame($before, Tenant::count());
        $this->assertSame(0, Tenant::whereNull('society_id')->count());
    }

    public function test_a_society_admin_still_sees_and_opens_only_their_own_tenants(): void
    {
        $list = $this->actingAs($this->adminA)->get(route('admin.tenants.index'))->assertOk()->viewData('tenants');
        $this->assertSame([$this->tenantA->id], $list->pluck('id')->all());

        $this->actingAs($this->adminA)->get(route('admin.tenants.show', $this->tenantA))->assertOk();
        $this->actingAs($this->adminA)->get(route('admin.tenants.show', $this->tenantB))->assertRedirect()->assertSessionHas('error');
        $this->actingAs($this->adminA)->get(route('admin.tenants.edit', $this->tenantB))->assertRedirect()->assertSessionHas('error');
        $this->actingAs($this->adminA)->post(route('admin.tenants.move-out', $this->tenantB))->assertSessionHas('error');
        $this->assertNotNull($this->tenantB->fresh()->flat_id, 'another society\'s tenant was not moved out');
    }

    public function test_a_super_admin_can_open_any_societys_resident_and_a_society_admin_only_their_own(): void
    {
        $residentB = Resident::create(['society_id' => $this->b->id, 'flat_id' => $this->tenantB->flat_id, 'user_id' => $this->tenantB->user_id, 'name' => 'Tom', 'type' => 'tenant', 'move_in_date' => '2026-09-01', 'status' => 'active']);

        $this->actingAs($this->super)->get(route('residents.edit', $residentB))->assertOk();
        $this->actingAs($this->adminA)->get(route('residents.edit', $residentB))->assertRedirect()->assertSessionHas('error');
    }
}
