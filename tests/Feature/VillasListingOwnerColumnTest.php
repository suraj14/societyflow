<?php

namespace Tests\Feature;

use App\Models\Flat;
use App\Models\Owner;
use App\Models\VillaArea;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Villas listing (VillaController::index / villas/index.blade.php) read
 * $villa->ownerModel, a plain belongsTo(Owner::class, 'owner_id') - the same
 * relation Property & Occupancy used before it was fixed to use
 * Flat::resolvedOwner() (see PropertyOccupancyOwnerConsistencyTest).
 * flats.owner_id is unset on most real rows, so every villa showed
 * "Not Assigned" here even when Owner Management and Property & Occupancy
 * both correctly showed the owner via the address-matching fallback.
 */
class VillasListingOwnerColumnTest extends TestCase
{
    use RefreshDatabase;

    private function villa(\App\Models\Society $society, VillaArea $area, string $number, string $status = 'vacant'): Flat
    {
        return Flat::create([
            'society_id' => $society->id,
            'villa_area_id' => $area->id,
            'flat_number' => $number,
            'property_type' => 'villa',
            'status' => $status,
        ]);
    }

    public function test_villas_listing_shows_the_owner_resolved_by_address_when_owner_id_is_unlinked(): void
    {
        $society = $this->makeSociety();
        $admin = $this->makeUserWithRole($society, 'Admin');
        $area = VillaArea::create(['society_id' => $society->id, 'name' => 'Suman Sadan']);

        $villa159 = $this->villa($society, $area, '159', 'on_rent');
        // Rented means an active tenant record (occupancy is derived from
        // tenant/owner records, not from the stored status column alone).
        \App\Models\Tenant::create([
            'society_id' => $society->id, 'flat_id' => $villa159->id, 'name' => 'Piyus',
            'email' => 'piyus@test.local', 'phone' => '9000000031', 'move_in_date' => now(), 'status' => 'active',
        ]);
        Owner::create([
            'society_id' => $society->id, 'name' => 'Lokesh Suman', 'email' => 'lokesh@test.local',
            'phone' => '9000000030', 'status' => 'active', 'property_type' => 'villa',
            'villa_area_id' => $area->id, 'villa_no' => '159',
            // owner_id on the flat is deliberately left null - the real gap.
        ]);

        $villa165 = $this->villa($society, $area, '165');

        $response = $this->actingAs($admin)->get('/villas');

        $response->assertOk();
        $response->assertSeeInOrder(['159'], false);
        $response->assertSee('Lokesh Suman');

        // Unassigned villa still correctly shows "Not Assigned".
        $response->assertSee('Not Assigned');

        // Renting is shown from the tenant record, and the owner is still listed.
        $response->assertSee('Tenant Occupied');

        $this->assertSame(1, Owner::where('society_id', $society->id)->count());
        $this->assertSame(2, Flat::where('society_id', $society->id)->count());
    }
}
