<?php

namespace Tests\Feature;

use App\Models\Flat;
use App\Models\Owner;
use App\Models\Tenant;
use App\Models\VillaArea;
use App\Support\DataIntegrityRepair;
use App\Support\PropertyOccupancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Occupancy is business state, never a form field. Every write of
 * occupied / vacant / on_rent to flats.status is replaced with the value the
 * canonical rule derives (Flat::saving), "under maintenance" is the one
 * state a person may set, and the data repair brings existing rows in line.
 *
 * Found in the dev database: flat 1A in society 1 stored as "occupied" with
 * no owner, tenant or resident; Villa 159's tenant "active" with a
 * move-out date from an earlier move-out.
 */
class OccupancyStatusDerivationTest extends TestCase
{
    use RefreshDatabase;

    private function villa(array $overrides = []): array
    {
        $society = $this->makeSociety();
        $area    = VillaArea::create(['society_id' => $society->id, 'name' => 'Area', 'status' => 'active']);
        $admin   = $this->makeUserWithRole($society, 'Admin');
        $villa   = Flat::create(array_merge([
            'society_id' => $society->id, 'property_type' => 'villa', 'villa_area_id' => $area->id,
            'flat_number' => '160', 'floor' => 0, 'status' => 'occupied',
        ], $overrides));

        return compact('society', 'area', 'admin', 'villa');
    }

    private function owner(array $f, string $villaNo = '160', string $status = 'active'): Owner
    {
        return Owner::create([
            'society_id' => $f['society']->id, 'name' => 'Owner ' . $villaNo, 'email' => 'o' . uniqid() . '@test.local',
            'phone' => '9000000001', 'status' => $status, 'property_type' => 'villa',
            'villa_area_id' => $f['area']->id, 'villa_no' => $villaNo,
        ]);
    }

    private function tenant(array $f): Tenant
    {
        return Tenant::create([
            'society_id' => $f['society']->id, 'flat_id' => $f['villa']->id, 'name' => 'T',
            'email' => 't' . uniqid() . '@test.local', 'phone' => '9000000002',
            'move_in_date' => now(), 'status' => 'active',
        ]);
    }

    public function test_a_stored_occupied_with_no_occupant_is_never_persisted(): void
    {
        $f = $this->villa(['status' => 'occupied']);

        // Flat 1A's case: the form said "occupied", nobody lives there.
        $this->assertSame('vacant', $f['villa']->fresh()->status);
        $this->assertSame(PropertyOccupancy::VACANT, $f['villa']->occupancyState());
    }

    public function test_manual_occupancy_writes_are_replaced_with_the_derived_value(): void
    {
        $f = $this->villa();
        $this->owner($f);
        $villa = $f['villa']->fresh();
        $this->assertSame('occupied', $villa->status, 'owner record alone makes it owner-occupied');

        $villa->update(['status' => 'vacant']);
        $this->assertSame('occupied', $villa->fresh()->status, 'a contradicting "vacant" is ignored');

        $villa->update(['status' => 'on_rent']);
        $this->assertSame('occupied', $villa->fresh()->status, 'a contradicting "on_rent" is ignored');

        $this->tenant($f);
        $this->assertSame('on_rent', $villa->fresh()->status, 'a tenant makes it on_rent');

        $villa->fresh()->update(['status' => 'vacant']);
        $this->assertSame('on_rent', $villa->fresh()->status);
    }

    public function test_the_edit_forms_cannot_set_occupancy_but_can_set_maintenance(): void
    {
        $f = $this->villa();
        $this->owner($f);

        $base = [
            'villa_area_id' => $f['area']->id, 'flat_number' => '160', 'has_garden' => 0, 'has_parking' => 0,
        ];

        // "Occupied" / "On Rent" / "Vacant" from a form never win.
        $this->actingAs($f['admin'])->put(route('villas.update', $f['villa']), $base + ['status' => 'vacant'])->assertRedirect();
        $this->assertSame('occupied', $f['villa']->fresh()->status);

        $this->actingAs($f['admin'])->put(route('villas.update', $f['villa']), $base + ['status' => 'on_rent'])->assertRedirect();
        $this->assertSame('occupied', $f['villa']->fresh()->status);

        // Under maintenance is a decision and sticks.
        $this->actingAs($f['admin'])->put(route('villas.update', $f['villa']), $base + ['status' => 'under_maintenance'])->assertRedirect();
        $this->assertSame('under_maintenance', $f['villa']->fresh()->status);

        // Taking it out of maintenance derives again.
        $this->actingAs($f['admin'])->put(route('villas.update', $f['villa']), $base)->assertRedirect();
        $this->assertSame('occupied', $f['villa']->fresh()->status);
    }

    public function test_flat_change_status_endpoint_only_toggles_maintenance(): void
    {
        $society  = $this->makeSociety();
        $building = $this->makeBuilding($society);
        $admin    = $this->makeUserWithRole($society, 'Admin');
        $flat     = $this->makeFlat($society, $building, '1A'); // TestCase writes 'occupied'

        $this->assertSame('vacant', $flat->fresh()->status, 'no occupant: the fixture\'s "occupied" is not stored');

        $this->actingAs($admin)->post(route('flats.change-status', $flat), ['status' => 'occupied'])->assertRedirect();
        $this->assertSame('vacant', $flat->fresh()->status);

        $this->actingAs($admin)->post(route('flats.change-status', $flat), ['status' => 'maintenance'])->assertRedirect();
        $this->assertSame('maintenance', $flat->fresh()->status);
    }

    public function test_the_forms_no_longer_offer_occupancy_choices(): void
    {
        $f = $this->villa();
        $society  = $f['society'];
        $building = $this->makeBuilding($society);
        $flat     = $this->makeFlat($society, $building, '2B');

        foreach ([route('villas.edit', $f['villa']), route('villas.create'), route('flats.edit', $flat), route('flats.create')] as $url) {
            $html = $this->actingAs($f['admin'])->get($url)->assertOk()->getContent();
            $this->assertStringNotContainsString('<option value="occupied"', $html, $url);
            $this->assertStringNotContainsString('<option value="on_rent"', $html, $url);
            $this->assertStringContainsString('Under Maintenance', $html, $url);
        }
    }

    public function test_owner_record_changes_keep_the_stored_status_in_step(): void
    {
        $f = $this->villa();
        $this->assertSame('vacant', $f['villa']->fresh()->status);

        $owner = $this->owner($f);
        $this->assertSame('occupied', $f['villa']->fresh()->status, 'owner created after the flat');

        $owner->update(['status' => 'inactive']);
        $this->assertSame('vacant', $f['villa']->fresh()->status, 'inactive owner does not occupy');

        $owner->update(['status' => 'active']);
        $this->assertSame('occupied', $f['villa']->fresh()->status);

        $owner->delete();
        $this->assertSame('vacant', $f['villa']->fresh()->status, 'owner removed');
    }

    public function test_tenant_move_out_falls_back_to_owner_occupied_not_vacant(): void
    {
        $f = $this->villa();
        $this->owner($f);
        $tenant = $this->tenant($f);
        $this->assertSame('on_rent', $f['villa']->fresh()->status);

        $tenant->update(['status' => 'inactive', 'move_out_date' => now()]);
        $this->assertSame('occupied', $f['villa']->fresh()->status);
    }

    public function test_an_active_tenancy_never_keeps_a_move_out_date(): void
    {
        $f = $this->villa();
        $tenant = $this->tenant($f);

        $tenant->update(['status' => 'inactive', 'flat_id' => null, 'move_out_date' => now()->subDay()]);
        $this->assertNotNull($tenant->fresh()->move_out_date);

        // Re-assigned: the Villa 159 case.
        $tenant->update(['status' => 'active', 'flat_id' => $f['villa']->id]);
        $this->assertNull($tenant->fresh()->move_out_date);
        $this->assertSame('on_rent', $f['villa']->fresh()->status);
    }

    public function test_repair_resyncs_stale_rows_written_before_the_guard_and_is_idempotent(): void
    {
        $f = $this->villa();
        $this->owner($f);
        $tenant = $this->tenant($f);

        // Write the dev database's two inconsistencies straight to the
        // tables, bypassing the models.
        \DB::table('flats')->where('id', $f['villa']->id)->update(['status' => 'vacant']);
        \DB::table('tenants')->where('id', $tenant->id)->update(['move_out_date' => '2026-09-24 08:13:50']);

        $report = DataIntegrityRepair::run(false);
        $this->assertSame(1, $report['flats']);
        $this->assertSame(1, $report['tenants']);
        $this->assertSame('vacant', $f['villa']->fresh()->status, 'report mode writes nothing');

        $fixed = DataIntegrityRepair::run(true);
        $this->assertSame(1, $fixed['flats']);
        $this->assertSame(1, $fixed['tenants']);
        $this->assertSame('on_rent', $f['villa']->fresh()->status);
        $this->assertNull($tenant->fresh()->move_out_date);
        $this->assertDatabaseHas('activity_logs', ['action' => 'flat.stored_status_resynced', 'model_id' => $f['villa']->id]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'tenant.stale_move_out_cleared', 'model_id' => $tenant->id]);

        $again = DataIntegrityRepair::run(true);
        $this->assertSame(0, $again['flats']);
        $this->assertSame(0, $again['tenants']);
    }
}
