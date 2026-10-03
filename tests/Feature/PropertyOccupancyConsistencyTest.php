<?php

namespace Tests\Feature;

use App\Models\Flat;
use App\Models\Owner;
use App\Models\Resident;
use App\Models\Society;
use App\Models\Tenant;
use App\Models\VillaArea;
use App\Support\PropertyOccupancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Occupancy is one rule (App\Support\PropertyOccupancy), and every screen
 * and counter must agree with it:
 *
 *   active tenant                    -> Tenant Occupied
 *   no tenant, active owner          -> Owner Occupied
 *   neither                          -> Vacant
 *
 * The original bug: Property & Occupancy showed Villa 158 and 160 as Owner
 * Occupied while the Villas page showed them Vacant, because each screen
 * decided occupancy for itself (stored flats.status vs tenant/owner
 * records) and the stored column is written by a dozen flows with different
 * vocabularies.
 */
class PropertyOccupancyConsistencyTest extends TestCase
{
    use RefreshDatabase;

    /** Royal Eden: 158 + 160 owner only, 159 owner + tenant, 165 + 72 empty. */
    private function royalEden(): array
    {
        $society = $this->makeSociety('Royal Eden');
        $area    = VillaArea::create(['society_id' => $society->id, 'name' => 'Suman Sadan', 'status' => 'active']);
        $admin   = $this->makeUserWithRole($society, 'Admin');

        $villas = [];
        foreach (['158', '159', '160', '165', '72'] as $no) {
            $villas[$no] = Flat::create([
                'society_id' => $society->id, 'property_type' => 'villa', 'villa_area_id' => $area->id,
                // Deliberately wrong stored statuses: nothing may read them.
                'flat_number' => $no, 'floor' => 0, 'status' => 'vacant',
            ]);
        }

        $owners = [];
        foreach (['158' => 'Owner 158', '159' => 'Lokesh', '160' => 'Suraj'] as $no => $name) {
            $owners[$no] = Owner::create([
                'society_id' => $society->id, 'name' => $name, 'email' => 'o' . $no . uniqid() . '@test.local',
                'phone' => '90000' . $no . '0', 'status' => 'active', 'property_type' => 'villa',
                'villa_area_id' => $area->id, 'villa_no' => $no,
            ]);
        }

        $tenant = Tenant::create([
            'society_id' => $society->id, 'flat_id' => $villas['159']->id, 'name' => 'Piyus',
            'email' => 'piyus' . uniqid() . '@test.local', 'phone' => '9000000061',
            'move_in_date' => now(), 'status' => 'active',
        ]);

        return compact('society', 'area', 'admin', 'villas', 'owners', 'tenant');
    }

    private function expected(): array
    {
        return [
            '158' => PropertyOccupancy::OWNER,
            '159' => PropertyOccupancy::TENANT,
            '160' => PropertyOccupancy::OWNER,
            '165' => PropertyOccupancy::VACANT,
            '72'  => PropertyOccupancy::VACANT,
        ];
    }

    public function test_the_same_property_has_the_same_occupancy_on_every_screen(): void
    {
        $f = $this->royalEden();

        // The model.
        foreach ($this->expected() as $no => $state) {
            $this->assertSame($state, $f['villas'][$no]->occupancyState(), "Villa {$no} via the model");
        }

        // Property & Occupancy: rows and counters.
        $hub = $this->actingAs($f['admin'])->get(route('admin.property-occupancy.index'));
        $hub->assertOk();
        $rows = $hub->viewData('occupancy');
        foreach ($this->expected() as $no => $state) {
            $this->assertSame($state, $rows[$f['villas'][$no]->id]['state'], "Villa {$no} on Property & Occupancy");
        }
        $stats = $hub->viewData('stats');
        $this->assertSame(5, $stats['total']);
        $this->assertSame(2, $stats['owner_occupied']);
        $this->assertSame(1, $stats['tenant_occupied']);
        $this->assertSame(2, $stats['vacant']);
        $this->assertSame($stats['total'], $stats['owner_occupied'] + $stats['tenant_occupied'] + $stats['vacant']);

        // Villas page (the screen that used to say Vacant for 158 and 160).
        $villas = $this->actingAs($f['admin'])->get(route('villas.index'));
        $villas->assertOk();
        $villaRows = $villas->viewData('occupancy');
        foreach ($this->expected() as $no => $state) {
            $this->assertSame($state, $villaRows[$f['villas'][$no]->id]['state'], "Villa {$no} on the Villas page");
        }
        $villas->assertSee('Owner Occupied')->assertSee('Tenant Occupied')->assertSee('Vacant');

        // Flats list.
        $flats = $this->actingAs($f['admin'])->get(route('flats.index'));
        $flats->assertOk();
        foreach ($this->expected() as $no => $state) {
            $this->assertSame($state, $flats->viewData('occupancy')[$f['villas'][$no]->id]['state'], "Villa {$no} on the Flats list");
        }

        // Dashboard counters.
        $dash = $this->actingAs($f['admin'])->get('/dashboard')->viewData('stats');
        $this->assertSame(2, $dash['vacant_units']);
        $this->assertSame(3, $dash['occupied_units']);
        $this->assertSame(2, $dash['owner_occupied_units']);
        $this->assertSame(1, $dash['tenant_occupied_units']);

        // Per-building/area counts agree too.
        $this->assertSame(2, $f['area']->getVacantVillasCount());
        $this->assertSame(3, $f['area']->getOccupiedVillasCount());

        // Owner-level counts (were stubs that always returned 0).
        $this->assertSame(1, $f['owners']['160']->getOccupiedPropertiesCount());
        $this->assertSame(0, $f['owners']['160']->getVacantPropertiesCount());
        $this->assertSame(1, $f['owners']['159']->getOccupiedPropertiesCount(), 'a rented property is still occupied');
    }

    public function test_flats_list_status_filter_uses_the_same_rule(): void
    {
        $f = $this->royalEden();

        $ids = fn (string $status) => $this->actingAs($f['admin'])
            ->get(route('flats.index', ['status' => $status]))
            ->viewData('flats')->pluck('id')->sort()->values()->all();

        $v = $f['villas'];
        $this->assertEquals(collect([$v['165']->id, $v['72']->id])->sort()->values()->all(), $ids('vacant'));
        $this->assertEquals(collect([$v['158']->id, $v['160']->id])->sort()->values()->all(), $ids('owner_occupied'));
        $this->assertEquals([$v['159']->id], $ids('tenant_occupied'));
        $this->assertEquals([$v['159']->id], $ids('on_rent'));
        $this->assertCount(3, $ids('occupied'));
    }

    public function test_renting_does_not_convert_the_owner_and_moving_out_returns_to_owner_occupied(): void
    {
        $f = $this->royalEden();
        $villa159 = $f['villas']['159']->fresh();

        // Owner of 159 is untouched by the tenancy.
        $this->assertSame('Lokesh', $villa159->resolvedOwner()->name);
        $this->assertSame('active', $f['owners']['159']->fresh()->status);
        $this->assertSame('on_rent', $villa159->status);

        $f['tenant']->update(['flat_id' => null, 'move_out_date' => now()]);

        $villa159 = $villa159->fresh();
        $this->assertSame(PropertyOccupancy::OWNER, $villa159->occupancyState());
        // Used to be written as 'vacant' even though the owner lives there.
        $this->assertSame('occupied', $villa159->status);
        $this->assertSame('Lokesh', $villa159->resolvedOwner()->name);
    }

    public function test_a_unit_with_no_owner_goes_vacant_when_its_tenant_leaves(): void
    {
        $f = $this->royalEden();

        $tenant = Tenant::create([
            'society_id' => $f['society']->id, 'flat_id' => $f['villas']['165']->id, 'name' => 'Visitor Tenant',
            'email' => 'vt' . uniqid() . '@test.local', 'phone' => '9000000099', 'move_in_date' => now(), 'status' => 'active',
        ]);
        $this->assertSame('on_rent', $f['villas']['165']->fresh()->status);
        $this->assertSame(PropertyOccupancy::TENANT, $f['villas']['165']->fresh()->occupancyState());

        $tenant->update(['status' => 'inactive']);

        $this->assertSame(PropertyOccupancy::VACANT, $f['villas']['165']->fresh()->occupancyState());
        $this->assertSame('vacant', $f['villas']['165']->fresh()->status);
    }

    public function test_an_inactive_owner_does_not_make_a_property_occupied(): void
    {
        $f = $this->royalEden();

        $f['owners']['160']->update(['status' => 'inactive']);

        $this->assertSame(PropertyOccupancy::VACANT, $f['villas']['160']->fresh()->occupancyState());
        $this->assertSame(3, PropertyOccupancy::counts($f['society']->id)['vacant']);
    }

    public function test_reassigning_a_moved_out_tenant_clears_the_stale_move_out_date(): void
    {
        $f = $this->royalEden();

        $f['tenant']->update(['flat_id' => null, 'move_out_date' => now()->subDay()]);
        $this->assertNotNull($f['tenant']->fresh()->move_out_date);

        $f['tenant']->update(['flat_id' => $f['villas']['165']->id]);

        $tenant = $f['tenant']->fresh();
        $this->assertNull($tenant->move_out_date, 'an active tenant placed in a unit is not moved out');
        $this->assertSame(PropertyOccupancy::TENANT, $f['villas']['165']->fresh()->occupancyState());
        $this->assertSame(PropertyOccupancy::OWNER, $f['villas']['159']->fresh()->occupancyState());
    }

    public function test_a_resident_row_change_does_not_clobber_a_rented_units_status(): void
    {
        $f = $this->royalEden();
        $this->assertSame('on_rent', $f['villas']['159']->fresh()->status);

        // Resident::saved/deleted hooks call Flat::updateStatus(), which used
        // to flip any unit with an active resident to "occupied".
        $resident = Resident::create([
            'society_id' => $f['society']->id, 'flat_id' => $f['villas']['159']->id, 'name' => 'Legacy Row',
            'email' => 'lr' . uniqid() . '@test.local', 'phone' => '9000000088', 'type' => 'tenant',
            'move_in_date' => now()->toDateString(), 'status' => 'active',
        ]);
        $this->assertSame('on_rent', $f['villas']['159']->fresh()->status);

        $resident->delete();
        $this->assertSame('on_rent', $f['villas']['159']->fresh()->status);
        $this->assertSame(PropertyOccupancy::TENANT, $f['villas']['159']->fresh()->occupancyState());
    }

    public function test_occupancy_counts_are_scoped_to_one_society(): void
    {
        $f = $this->royalEden();
        $other = $this->makeSociety('Elsewhere');
        Flat::create(['society_id' => $other->id, 'property_type' => 'villa', 'flat_number' => '1', 'floor' => 0, 'status' => 'occupied']);

        $this->assertSame(5, PropertyOccupancy::counts($f['society']->id)['total']);
        $this->assertSame(1, PropertyOccupancy::counts($other->id)['total']);
        $this->assertSame(1, PropertyOccupancy::counts($other->id)['vacant']);
    }

    public function test_flat_search_cannot_list_another_societys_flats(): void
    {
        $f = $this->royalEden();
        $other = $this->makeSociety('Elsewhere');
        $byNumber = Flat::create(['society_id' => $other->id, 'property_type' => 'villa', 'flat_number' => '777', 'floor' => 0, 'status' => 'vacant']);
        $b = $this->makeBuilding($other, 'Tower 777');
        $byBuilding = Flat::create(['society_id' => $other->id, 'building_id' => $b->id, 'property_type' => 'apartment', 'flat_number' => '9', 'floor' => 1, 'status' => 'vacant']);

        // Matches the other society by flat number and by building name. The
        // ungrouped OR used to list both of them to this society's admin.
        $response = $this->actingAs($f['admin'])->get(route('flats.index', ['search' => '777']));
        $response->assertOk();

        $ids = $response->viewData('flats')->pluck('id')->all();
        $this->assertNotContains($byNumber->id, $ids);
        $this->assertNotContains($byBuilding->id, $ids);

        // A genuine search inside the admin's own society still works.
        $own = $this->actingAs($f['admin'])->get(route('flats.index', ['search' => '159']))->viewData('flats')->pluck('id')->all();
        $this->assertSame([$f['villas']['159']->id], $own);
    }
}
