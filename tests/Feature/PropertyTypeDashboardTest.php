<?php

namespace Tests\Feature;

use App\Enums\SocietyPropertyType;
use App\Models\Flat;
use App\Models\Society;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Dashboard figures follow the society's property type.
 *
 * A villa township has no buildings to count and an apartment society has
 * no villas, so showing both cards to everyone produces meaningless zeros.
 * A mixed society gets them split out rather than collapsed into one
 * "units" number.
 */
class PropertyTypeDashboardTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0:Society,1:\App\Models\User} */
    private function societyWithUnits(SocietyPropertyType $type, int $apartments, int $villas): array
    {
        $society = $this->makeSociety();
        $society->update(['property_type' => $type->value]);
        $society = $society->fresh();

        $building = $this->makeBuilding($society);

        // Occupancy is derived from real tenant/owner records (see
        // App\Support\PropertyOccupancy), not from the stored status column,
        // so "occupied" units get an active tenant here.
        $occupy = function (Flat $flat) use ($society) {
            \App\Models\Tenant::create([
                'society_id' => $society->id, 'flat_id' => $flat->id, 'name' => 'T ' . $flat->flat_number,
                'email' => 't' . $flat->id . uniqid() . '@test.local', 'phone' => '90000' . random_int(10000, 99999),
                'move_in_date' => now(), 'status' => 'active',
            ]);
        };

        for ($i = 0; $i < $apartments; $i++) {
            $flat = Flat::create([
                'society_id' => $society->id,
                'building_id' => $building->id,
                'property_type' => 'apartment',
                'flat_number' => 'A-' . $i,
                'floor' => 1,
                'status' => $i === 0 ? 'vacant' : 'occupied',
            ]);
            if ($i !== 0) {
                $occupy($flat);
            }
        }

        for ($i = 0; $i < $villas; $i++) {
            $flat = Flat::create([
                'society_id' => $society->id,
                'property_type' => 'villa',
                'flat_number' => 'V-' . $i,
                'status' => 'occupied',
            ]);
            $occupy($flat);
        }

        return [$society, $this->makeUserWithRole($society, 'Admin')];
    }

    public function test_villa_society_dashboard_counts_villas_and_hides_buildings(): void
    {
        [, $admin] = $this->societyWithUnits(SocietyPropertyType::Villa, 0, 5);

        $response = $this->actingAs($admin)->get('/dashboard')->assertOk();
        $stats = $response->viewData('stats');

        $this->assertSame(5, $stats['total_villas']);
        $this->assertSame(0, $stats['total_apartments']);

        $html = $response->getContent();
        $this->assertStringContainsString('Villas', $html);
        $this->assertStringNotContainsString('>Buildings<', $html);
    }

    public function test_apartment_society_dashboard_shows_buildings_not_villas(): void
    {
        [, $admin] = $this->societyWithUnits(SocietyPropertyType::Apartment, 4, 0);

        $response = $this->actingAs($admin)->get('/dashboard')->assertOk();
        $stats = $response->viewData('stats');

        $this->assertSame(4, $stats['total_apartments']);
        $this->assertSame(0, $stats['total_villas']);
        $this->assertStringContainsString('Buildings', $response->getContent());
    }

    public function test_mixed_society_dashboard_splits_the_counts(): void
    {
        [, $admin] = $this->societyWithUnits(SocietyPropertyType::Both, 3, 2);

        $response = $this->actingAs($admin)->get('/dashboard')->assertOk();
        $stats = $response->viewData('stats');

        $this->assertSame(3, $stats['total_apartments']);
        $this->assertSame(2, $stats['total_villas']);
        $this->assertSame(5, $stats['total_units']);

        $html = $response->getContent();
        $this->assertStringContainsString('Apartments', $html);
        $this->assertStringContainsString('Villas', $html);
    }

    public function test_occupancy_split_is_correct(): void
    {
        [, $admin] = $this->societyWithUnits(SocietyPropertyType::Both, 3, 2);

        $stats = $this->actingAs($admin)->get('/dashboard')->assertOk()->viewData('stats');

        // Seeded: 3 apartments (the first vacant) + 2 villas, all occupied.
        $this->assertSame(1, $stats['vacant_units']);
        $this->assertSame(4, $stats['occupied_units']);
    }

    /**
     * The split counts must not cost extra round trips per card or per
     * unit. (This used to assert "one grouped property_type query", an
     * implementation detail of the old stored-status counting; the intent
     * is that the cost does not grow with the number of units.)
     */
    public function test_property_counts_cost_a_fixed_number_of_queries(): void
    {
        $flatQueries = function (int $apartments, int $villas): int {
            [, $admin] = $this->societyWithUnits(SocietyPropertyType::Both, $apartments, $villas);

            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($admin)->get('/dashboard')->assertOk();
            $queries = DB::getQueryLog();
            DB::disableQueryLog();

            return count(array_filter(
                $queries,
                fn ($q) => preg_match('/from "(flats|tenants|owners)"/i', $q['query'])
            ));
        };

        $small = $flatQueries(2, 2);
        $large = $flatQueries(12, 12);

        $this->assertSame($small, $large, 'unit/occupancy queries must not grow with the number of units');
    }
}
