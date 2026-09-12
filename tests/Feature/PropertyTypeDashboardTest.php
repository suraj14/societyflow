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

        for ($i = 0; $i < $apartments; $i++) {
            Flat::create([
                'society_id' => $society->id,
                'building_id' => $building->id,
                'property_type' => 'apartment',
                'flat_number' => 'A-' . $i,
                'floor' => 1,
                'status' => $i === 0 ? 'vacant' : 'occupied',
            ]);
        }

        for ($i = 0; $i < $villas; $i++) {
            Flat::create([
                'society_id' => $society->id,
                'property_type' => 'villa',
                'flat_number' => 'V-' . $i,
                'status' => 'occupied',
            ]);
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

    /** The split counts must not cost extra round trips per card. */
    public function test_property_counts_use_a_single_grouped_query(): void
    {
        [, $admin] = $this->societyWithUnits(SocietyPropertyType::Both, 6, 6);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($admin)->get('/dashboard')->assertOk();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $grouped = array_filter(
            $queries,
            fn ($q) => str_contains($q['query'], 'group by') && str_contains($q['query'], 'property_type')
        );

        $this->assertCount(1, $grouped, 'expected exactly one grouped property_type query');
    }
}
