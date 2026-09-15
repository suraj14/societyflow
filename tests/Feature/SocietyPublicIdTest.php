<?php

namespace Tests\Feature;

use App\Models\Building;
use App\Models\Flat;
use App\Models\Owner;
use App\Models\Society;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Societies are addressed publicly by a ULID, internally by the integer.
 *
 * The point is not secrecy - it is that /super-admin/societies/6 announces
 * how many societies exist and invites a try at 5. The numeric key stays
 * exactly where it was, because 40 tables join on it.
 */
class SocietyPublicIdTest extends TestCase
{
    use RefreshDatabase;

    // ------------------------------------------------------------ the column

    public function test_the_column_is_unique_and_not_nullable(): void
    {
        $this->assertTrue(Schema::hasColumn('societies', 'public_id'));

        if (DB::getDriverName() === 'sqlite') {
            $column = collect(DB::select('PRAGMA table_info(societies)'))->firstWhere('name', 'public_id');
            $this->assertNotNull($column);
            $this->assertSame(1, (int) $column->notnull, 'public_id must be NOT NULL');

            $indexes = collect(DB::select("SELECT name FROM sqlite_master WHERE type='index' AND tbl_name='societies'"))
                ->pluck('name');
            $this->assertTrue($indexes->contains('societies_public_id_unique'));
        }
    }

    /** The rebuild must not have cost societies its existing structure. */
    public function test_the_rebuild_preserved_the_existing_indexes(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            $this->markTestSkipped('index names checked on sqlite');
        }

        $indexes = collect(DB::select("SELECT name FROM sqlite_master WHERE type='index' AND tbl_name='societies'"))
            ->pluck('name');

        foreach ([
            'societies_slug_unique',
            'societies_subdomain_unique',
            'societies_status_subscription_ends_at_index',
            'societies_admin_id_status_index',
            'societies_subscription_plan_id_status_index',
            'societies_property_type_index',
        ] as $index) {
            $this->assertTrue($indexes->contains($index), "index {$index} was lost in the rebuild");
        }
    }

    // ------------------------------------------------------ assignment

    public function test_every_society_receives_a_unique_public_id(): void
    {
        $societies = collect(range(1, 5))->map(fn ($i) => $this->makeSociety("Society {$i}"));

        $ids = $societies->pluck('public_id');

        $this->assertCount(5, $ids->filter());
        $this->assertSame(5, $ids->unique()->count(), 'public_id values collided');

        foreach ($ids as $id) {
            $this->assertSame(26, strlen($id), 'a ULID is 26 characters');
        }
    }

    public function test_a_new_society_receives_one_automatically(): void
    {
        $society = $this->makeSociety('Fresh Society');

        $this->assertNotEmpty($society->public_id);
        $this->assertDatabaseHas('societies', [
            'id'        => $society->id,
            'public_id' => $society->public_id,
        ]);
    }

    /** It identifies the row, so editing the row must not move it. */
    public function test_renaming_a_society_does_not_change_its_public_id(): void
    {
        $society  = $this->makeSociety('Original Name');
        $original = $society->public_id;

        $society->update([
            'name'    => 'Completely Different Name',
            'address' => 'A new address entirely',
            'city'    => 'Another City',
        ]);

        $this->assertSame($original, $society->fresh()->public_id);
    }

    /** It must not be settable from request input. */
    public function test_public_id_is_not_mass_assignable(): void
    {
        $society = $this->makeSociety('Guarded');
        $original = $society->public_id;

        $society->fill(['public_id' => 'ATTACKERSUPPLIEDVALUE00000'])->save();

        $this->assertSame($original, $society->fresh()->public_id);
    }

    // ------------------------------------------------------------- routing

    public function test_the_detail_url_resolves_by_public_id(): void
    {
        $society = $this->makeSociety('Routable');
        $admin   = $this->makeUserWithRole($society, 'Super Admin');

        $this->actingAs($admin)
            ->get("/super-admin/societies/{$society->public_id}")
            ->assertOk()
            ->assertSee('Routable');
    }

    public function test_the_numeric_id_is_no_longer_a_valid_url(): void
    {
        $society = $this->makeSociety('Not By Number');
        $admin   = $this->makeUserWithRole($society, 'Super Admin');

        $this->actingAs($admin)
            ->get("/super-admin/societies/{$society->id}")
            ->assertNotFound();
    }

    public function test_an_unknown_public_id_is_a_plain_404(): void
    {
        $society = $this->makeSociety('Present');
        $admin   = $this->makeUserWithRole($society, 'Super Admin');

        $this->actingAs($admin)
            ->get('/super-admin/societies/01JQXYZNOTAREALULID000000')
            ->assertNotFound();
    }

    /** Generated links must carry the ULID, never the sequence. */
    public function test_generated_links_do_not_expose_the_sequential_id(): void
    {
        $society = $this->makeSociety('Linked');
        $admin   = $this->makeUserWithRole($society, 'Super Admin');

        $this->assertStringContainsString(
            $society->public_id,
            route('super-admin.societies.show', $society)
        );

        $html = $this->actingAs($admin)->get('/super-admin/societies')->assertOk()->getContent();

        $this->assertStringContainsString($society->public_id, $html);
        $this->assertStringNotContainsString("/societies/{$society->id}\"", $html);
    }

    // ------------------------------------------------------ authorization

    /**
     * A valid public_id is not permission. This was open: SocietyController
     * had no scoping at all, so any Admin could read and edit any society.
     */
    public function test_an_admin_cannot_reach_another_society_by_its_public_id(): void
    {
        $mine  = $this->makeSociety('Mine');
        $other = $this->makeSociety('Theirs');
        $admin = $this->makeUserWithRole($mine, 'Admin');

        $this->actingAs($admin)->get("/societies/{$other->public_id}")->assertForbidden();
        $this->actingAs($admin)->get("/societies/{$other->public_id}/edit")->assertForbidden();
    }

    public function test_an_admin_cannot_update_another_society(): void
    {
        $mine  = $this->makeSociety('Mine');
        $other = $this->makeSociety('Theirs');
        $admin = $this->makeUserWithRole($mine, 'Admin');

        $this->actingAs($admin)->put("/societies/{$other->public_id}", [
            'name'    => 'Hijacked',
            'address' => 'x',
            'city'    => 'x',
            'state'   => 'x',
            'pincode' => '000000',
        ])->assertForbidden();

        $this->assertSame('Theirs', $other->fresh()->name);
    }

    public function test_an_admin_sees_only_their_own_society_in_the_listing(): void
    {
        $mine  = $this->makeSociety('Mine Only');
        $other = $this->makeSociety('Not Mine');
        $admin = $this->makeUserWithRole($mine, 'Admin');

        $html = $this->actingAs($admin)->get('/societies')->assertOk()->getContent();

        $this->assertStringContainsString('Mine Only', $html);
        $this->assertStringNotContainsString('Not Mine', $html);
    }

    public function test_an_admin_may_still_reach_their_own_society(): void
    {
        $mine  = $this->makeSociety('Mine');
        $admin = $this->makeUserWithRole($mine, 'Admin');

        $this->actingAs($admin)->get("/societies/{$mine->public_id}")->assertOk();
    }

    // ------------------------------------------- internal relations unchanged

    /**
     * The whole point of the design: nothing that joins had to change.
     */
    public function test_related_records_still_use_the_numeric_society_id(): void
    {
        $society  = $this->makeSociety('Relational');
        $building = $this->makeBuilding($society);
        $flat     = $this->makeFlat($society, $building, '101');

        $owner = Owner::create([
            'society_id' => $society->id,
            'name'       => 'An Owner',
            'email'      => 'owner' . uniqid() . '@test.local',
            'status'     => 'active',
        ]);

        $user = $this->makeUserWithRole($society, 'Admin');

        foreach ([
            ['buildings', $building->id],
            ['flats', $flat->id],
            ['owners', $owner->id],
            ['users', $user->id],
        ] as [$table, $id]) {
            $this->assertDatabaseHas($table, ['id' => $id, 'society_id' => $society->id]);
        }

        // The foreign key holds the integer, not the ULID.
        $this->assertSame($society->id, Building::find($building->id)->society_id);
        $this->assertIsInt(Flat::find($flat->id)->society_id);
    }

    public function test_the_numeric_primary_key_is_untouched(): void
    {
        $first  = $this->makeSociety('First');
        $second = $this->makeSociety('Second');

        $this->assertIsInt($first->id);
        $this->assertSame($first->id + 1, $second->id, 'the sequence still increments');
    }

    // ---------------------------------------------------------------- api

    /** Additive only: `id` stays because shipped Flutter builds parse it. */
    public function test_the_api_exposes_public_id_without_dropping_the_numeric_id(): void
    {
        $society = $this->makeSociety('Api Society');
        $user    = $this->makeUserWithRole($society, 'Admin');

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/auth/me')->assertOk();

        $payload = $response->json('data.society');

        $this->assertSame($society->id, $payload['id'], 'the numeric id must remain');
        $this->assertSame($society->public_id, $payload['public_id']);
        $this->assertSame($society->name, $payload['name']);

        // The property-type contract must be unchanged.
        $this->assertArrayHasKey('property_type', $payload);
        $this->assertArrayHasKey('supports_apartments', $payload);
        $this->assertArrayHasKey('supports_villas', $payload);
    }
}
