<?php

namespace Tests\Feature;

use App\Models\Building;
use App\Models\Flat;
use App\Models\Society;
use App\Models\User;
use App\Models\VillaArea;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The cascading unit pickers of the Payments and Utility Bills pages. Their floor / flat / villa lookups took a
 * building or villa area id from the request and listed whatever matched, so anyone signed in could read another
 * society's flat and villa numbers by trying ids. They list the signed-in user's own society only.
 */
class UnitLookupScopeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Building $ownBuilding;
    private VillaArea $ownArea;
    private Building $foreignBuilding;
    private VillaArea $foreignArea;

    protected function setUp(): void
    {
        parent::setUp();

        [$own, $this->ownBuilding, $this->ownArea] = $this->societyWithUnits('Alpha', '101', '160');
        [, $this->foreignBuilding, $this->foreignArea] = $this->societyWithUnits('Beta', '901', '960');
        $this->admin = $this->makeUserWithRole($own, 'Admin');
    }

    /** @return array{0: Society, 1: Building, 2: VillaArea} */
    private function societyWithUnits(string $name, string $flatNo, string $villaNo): array
    {
        $society = $this->makeSociety($name);
        $building = $this->makeBuilding($society);
        $this->makeFlat($society, $building, $flatNo);
        $area = VillaArea::create(['society_id' => $society->id, 'name' => "{$name} Villas", 'status' => 'active']);
        Flat::create(['society_id' => $society->id, 'property_type' => 'villa', 'villa_area_id' => $area->id, 'flat_number' => $villaNo, 'floor' => 0, 'status' => 'occupied']);

        return [$society, $building, $area];
    }

    private function lookup(string $route, array $query): array
    {
        return $this->actingAs($this->admin)->getJson(route($route, $query))->assertOk()->json();
    }

    public function test_floor_lookups_list_only_the_users_own_society(): void
    {
        foreach (['api.floors', 'api.payments.floors'] as $route) {
            $this->assertSame([1], $this->lookup($route, ['building_id' => $this->ownBuilding->id]), $route);
            $this->assertSame([], $this->lookup($route, ['building_id' => $this->foreignBuilding->id]), "{$route} lists another society's floors");
        }
    }

    public function test_flat_lookups_list_only_the_users_own_society(): void
    {
        foreach (['api.utility-bills.flats', 'api.payments.flats'] as $route) {
            $this->assertSame(['101'], array_column($this->lookup($route, ['building_id' => $this->ownBuilding->id, 'floor' => 1]), 'flat_number'), $route);
            $this->assertSame([], $this->lookup($route, ['building_id' => $this->foreignBuilding->id, 'floor' => 1]), "{$route} lists another society's flats");
        }
    }

    public function test_villa_lookups_list_only_the_users_own_society_by_their_number(): void
    {
        foreach (['api.villas', 'api.payments.villas'] as $route) {
            $own = $this->lookup($route, ['villa_area_id' => $this->ownArea->id]);
            $this->assertSame(['160'], array_map('strval', array_column($own, 'villa_number')), "{$route} names each villa by its number");
            $this->assertSame([], $this->lookup($route, ['villa_area_id' => $this->foreignArea->id]), "{$route} lists another society's villas");
        }
    }
}
