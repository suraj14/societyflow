<?php

namespace Tests\Feature;

use App\Models\Flat;
use App\Models\Resident;
use App\Models\Society;
use App\Models\Tenant;
use App\Models\VillaArea;
use App\Models\Visitor;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Constraints that must hold identically on SQLite and MariaDB.
 *
 * The two engines drifted because Laravel compiles ->foreign() on an
 * existing table to nothing on SQLite, so several migrations create their
 * constraints inside SQLite-only table rebuilds - which MySQL never runs.
 * The result was constraints that existed on the development engine and not
 * on the production one, or the reverse, with nothing to notice.
 *
 * These assertions are written against whichever engine is running, so the
 * same file proves parity when executed twice.
 */
class SchemaParityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The foreign key on $table.$column, normalised across engines.
     *
     * @return array{ref_table:string,ref_column:string,on_delete:string}|null
     */
    private function foreignKey(string $table, string $column): ?array
    {
        if (DB::getDriverName() === 'sqlite') {
            foreach (DB::select("PRAGMA foreign_key_list(\"{$table}\")") as $fk) {
                if ($fk->from === $column) {
                    return [
                        'ref_table'  => $fk->table,
                        'ref_column' => $fk->to,
                        'on_delete'  => strtoupper($fk->on_delete),
                    ];
                }
            }

            return null;
        }

        $row = DB::selectOne(
            'SELECT kcu.REFERENCED_TABLE_NAME rt, kcu.REFERENCED_COLUMN_NAME rc, rc2.DELETE_RULE dr
             FROM information_schema.KEY_COLUMN_USAGE kcu
             JOIN information_schema.REFERENTIAL_CONSTRAINTS rc2
               ON rc2.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME
              AND rc2.CONSTRAINT_SCHEMA = kcu.CONSTRAINT_SCHEMA
             WHERE kcu.TABLE_SCHEMA = DATABASE() AND kcu.TABLE_NAME = ? AND kcu.COLUMN_NAME = ?
               AND kcu.REFERENCED_TABLE_NAME IS NOT NULL',
            [$table, $column]
        );

        return $row ? [
            'ref_table'  => $row->rt,
            'ref_column' => $row->rc,
            'on_delete'  => strtoupper($row->dr),
        ] : null;
    }

    private function isNullable(string $table, string $column): bool
    {
        if (DB::getDriverName() === 'sqlite') {
            $c = collect(DB::select("PRAGMA table_info(\"{$table}\")"))->firstWhere('name', $column);
            $this->assertNotNull($c, "{$table}.{$column} does not exist");

            return !$c->notnull;
        }

        $c = DB::selectOne(
            'SELECT IS_NULLABLE n FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column]
        );
        $this->assertNotNull($c, "{$table}.{$column} does not exist");

        return $c->n === 'YES';
    }

    /**
     * Ownership and property containment, on both engines.
     *
     * @dataProvider requiredForeignKeys
     */
    public function test_required_foreign_keys_exist(string $table, string $column, string $refTable, string $onDelete): void
    {
        $fk = $this->foreignKey($table, $column);

        $this->assertNotNull($fk, "{$table}.{$column} has no foreign key on " . DB::getDriverName());
        $this->assertSame($refTable, $fk['ref_table'], "{$table}.{$column} points at the wrong table");
        $this->assertSame('id', $fk['ref_column']);
        $this->assertSame($onDelete, $fk['on_delete'], "{$table}.{$column} has the wrong ON DELETE rule");
    }

    public static function requiredForeignKeys(): array
    {
        return [
            // Fixed in 2026_09_15_000001 (MySQL already had it, SQLite did not).
            'users.owner_id'         => ['users', 'owner_id', 'owners', 'SET NULL'],
            // Fixed in 2026_09_15_000003 (SQLite had it, MySQL did not).
            'flats.owner_id'         => ['flats', 'owner_id', 'owners', 'SET NULL'],
            // Fixed in 2026_09_15_000004, matching buildings.
            'flats.villa_area_id'    => ['flats', 'villa_area_id', 'villa_areas', 'CASCADE'],
            // Never diverged - included so a future rebuild cannot drop them.
            'flats.building_id'      => ['flats', 'building_id', 'buildings', 'CASCADE'],
            'flats.society_id'       => ['flats', 'society_id', 'societies', 'CASCADE'],
            'buildings.society_id'   => ['buildings', 'society_id', 'societies', 'CASCADE'],
            'owners.society_id'      => ['owners', 'society_id', 'societies', 'CASCADE'],
            'users.society_id'       => ['users', 'society_id', 'societies', 'CASCADE'],
            // Fixed in 2026_10_09_000001 (SQLite already had it, MariaDB ran
            // the original 2024 CASCADE the whole time - P0, see
            // docs/SCHEMA_PARITY.md). Deleting a user must not take a
            // visitor log or a residency record with it.
            'residents.user_id'      => ['residents', 'user_id', 'users', 'SET NULL'],
            'visitors.host_user_id'  => ['visitors', 'host_user_id', 'users', 'SET NULL'],
            // P1-17: a tenancy must outlive either side. It was CASCADE on one
            // engine and SET NULL on the other, in opposite directions for
            // the two columns (docs/SCHEMA_PARITY.md).
            'tenants.user_id'        => ['tenants', 'user_id', 'users', 'SET NULL'],
            'tenants.flat_id'        => ['tenants', 'flat_id', 'flats', 'SET NULL'],
        ];
    }

    /**
     * Columns that must stay nullable because the empty state is real:
     * most flats have no owner, apartments have no villa area, and a Super
     * Admin belongs to no society.
     *
     * @dataProvider nullableColumns
     */
    public function test_columns_that_must_stay_nullable(string $table, string $column): void
    {
        $this->assertTrue(
            $this->isNullable($table, $column),
            "{$table}.{$column} must stay nullable - the empty state is legitimate"
        );
    }

    public static function nullableColumns(): array
    {
        return [
            'flats.owner_id'      => ['flats', 'owner_id'],
            'flats.villa_area_id' => ['flats', 'villa_area_id'],
            'flats.building_id'   => ['flats', 'building_id'],
            'users.owner_id'      => ['users', 'owner_id'],
            'users.tenant_id'     => ['users', 'tenant_id'],
            'users.society_id'    => ['users', 'society_id'],
            // Fixed in 2026_10_09_000001 - was NOT NULL on MariaDB only,
            // which is also why that engine could not run ON DELETE SET
            // NULL and stayed on CASCADE instead (P1-15).
            'visitors.host_user_id' => ['visitors', 'host_user_id'],
            // P1-19: NOT NULL without a default on MariaDB only meant any path
            // omitting them worked in development and threw in production.
            'notices.publish_date'  => ['notices', 'publish_date'],
            'tenants.move_in_date'  => ['tenants', 'move_in_date'],
            // P1-17: SET NULL needs a nullable column.
            'tenants.user_id'       => ['tenants', 'user_id'],
            'tenants.flat_id'       => ['tenants', 'flat_id'],
        ];
    }

    // ------------------------------------------------ behaviour, not shape

    public function test_a_villa_cannot_reference_a_missing_villa_area(): void
    {
        $society = $this->makeSociety('Parity Society');

        $missing = (VillaArea::max('id') ?? 0) + 5000;

        $this->expectException(QueryException::class);

        Flat::create([
            'society_id'    => $society->id,
            'villa_area_id' => $missing,
            'property_type' => 'villa',
            'flat_number'   => 'V-BAD',
            'status'        => 'vacant',
        ]);
    }

    public function test_a_flat_with_no_villa_area_is_accepted(): void
    {
        $society  = $this->makeSociety('Parity Society');
        $building = $this->makeBuilding($society);

        $flat = Flat::create([
            'society_id'    => $society->id,
            'building_id'   => $building->id,
            'villa_area_id' => null,
            'property_type' => 'apartment',
            'flat_number'   => 'A-1',
            'floor'         => 1,
            'status'        => 'vacant',
        ]);

        $this->assertNull($flat->fresh()->villa_area_id);
    }

    /**
     * Deleting a villa area takes its villas with it - the same rule
     * buildings already follow. The application refuses this while villas
     * remain; the constraint is the backstop for direct database deletion.
     */
    public function test_deleting_a_villa_area_cascades_to_its_villas(): void
    {
        $society = $this->makeSociety('Parity Society');

        $area = VillaArea::create([
            'society_id' => $society->id,
            'name'       => 'Green Valley',
            'status'     => 'active',
        ]);

        $villa = Flat::create([
            'society_id'    => $society->id,
            'villa_area_id' => $area->id,
            'property_type' => 'villa',
            'flat_number'   => 'V-1',
            'status'        => 'vacant',
        ]);

        $other = Flat::create([
            'society_id'    => $society->id,
            'building_id'   => $this->makeBuilding($society)->id,
            'property_type' => 'apartment',
            'flat_number'   => 'A-1',
            'floor'         => 1,
            'status'        => 'vacant',
        ]);

        $area->delete();

        $this->assertNull(Flat::find($villa->id), 'the villa should go with its area');
        $this->assertNotNull(Flat::find($other->id), 'an unrelated apartment must survive');
    }

    /**
     * The P0 finding, demonstrated rather than inferred from the schema:
     * deleting a user must not take its residency record with it. Before
     * 2026_10_09_000001 this cascaded on MariaDB (CASCADE) while merely
     * clearing the link on SQLite (SET NULL) - same code, different real
     * outcome depending on the engine.
     */
    public function test_deleting_a_user_preserves_their_resident_record(): void
    {
        $society = $this->makeSociety('Parity Society');
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));
        $user    = $this->makeResidentUser($society, $flat);
        $resident = Resident::where('user_id', $user->id)->first();

        $user->delete();

        $resident->refresh();
        $this->assertNotNull($resident, 'the residency record itself must survive');
        $this->assertNull($resident->user_id, 'only the link to the deleted account should clear');
    }

    /**
     * Same finding, for the gate's own security record: deleting the host's
     * account must not delete the visitor log entry.
     */
    public function test_deleting_a_user_preserves_visitor_history_they_hosted(): void
    {
        $society = $this->makeSociety('Parity Society');
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));
        $host    = $this->makeResidentUser($society, $flat);

        $visitor = Visitor::create([
            'society_id'          => $society->id,
            'flat_id'             => $flat->id,
            'host_user_id'        => $host->id,
            'visitor_name'        => 'Delivery Guy',
            'visitor_phone'       => '9' . random_int(100000000, 999999999),
            'visitor_type'        => 'delivery',
            'expected_entry_time' => now(),
            'approval_status'     => 'allowed',
        ]);

        $host->delete();

        $visitor->refresh();
        $this->assertNotNull($visitor, 'the visitor log entry itself must survive');
        $this->assertNull($visitor->host_user_id, 'only the link to the deleted account should clear');
    }

    // ------------------------------------------------ P1-17 tenancy survives

    private function tenancy(): array
    {
        $society = $this->makeSociety('Parity Society');
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));
        $user    = $this->makeResidentUser($society, $flat);

        $tenant = Tenant::create([
            'society_id'   => $society->id,
            'user_id'      => $user->id,
            'flat_id'      => $flat->id,
            'name'         => 'Parity Tenant',
            'email'        => 'parity.tenant' . uniqid() . '@test.local',
            'phone'        => '9' . random_int(100000000, 999999999),
            'move_in_date' => '2026-01-01',
            'status'       => 'active',
        ]);

        return [$tenant, $user, $flat];
    }

    /**
     * Deleting the login account must not delete the tenancy record (rent
     * history hangs off it). It destroyed it on SQLite.
     */
    public function test_deleting_a_tenants_user_account_keeps_the_tenancy(): void
    {
        [$tenant, $user] = $this->tenancy();

        $user->delete();

        $tenant = Tenant::find($tenant->id);
        $this->assertNotNull($tenant, 'the tenancy itself must survive');
        $this->assertNull($tenant->user_id, 'only the link to the deleted account should clear');
    }

    /**
     * Deleting a flat must not delete the tenancy either - it destroyed it
     * on MariaDB, silently, while SQLite kept it.
     */
    public function test_deleting_a_flat_keeps_the_tenancy_that_pointed_at_it(): void
    {
        [$tenant, , $flat] = $this->tenancy();

        // The resident row and visitors reference the flat too; they cascade by design.
        $flat->delete();

        $tenant = Tenant::find($tenant->id);
        $this->assertNotNull($tenant, 'the tenancy itself must survive');
        $this->assertNull($tenant->flat_id, 'only the link to the removed flat should clear');
    }

    // ------------------------------------- P1-18 one flat number per building

    public function test_the_same_flat_number_cannot_be_used_twice_in_one_building(): void
    {
        $society  = $this->makeSociety('Parity Society');
        $building = $this->makeBuilding($society);
        $this->makeFlat($society, $building, '101');

        $this->expectException(QueryException::class);

        $this->makeFlat($society, $building, '101');
    }

    public function test_the_same_flat_number_is_fine_in_a_different_building(): void
    {
        $society = $this->makeSociety('Parity Society');
        $this->makeFlat($society, $this->makeBuilding($society, 'Tower A'), '101');

        $second = $this->makeFlat($society, $this->makeBuilding($society, 'Tower B'), '101');

        $this->assertSame('101', $second->fresh()->flat_number);
    }

    /** Villas have no building (NULL); the unique index must not make them collide. */
    public function test_villas_without_a_building_are_not_blocked_by_the_flat_number_rule(): void
    {
        $society = $this->makeSociety('Parity Society');
        $a = VillaArea::create(['society_id' => $society->id, 'name' => 'Area A', 'status' => 'active']);
        $b = VillaArea::create(['society_id' => $society->id, 'name' => 'Area B', 'status' => 'active']);

        foreach ([$a, $b] as $area) {
            Flat::create([
                'society_id' => $society->id, 'villa_area_id' => $area->id, 'property_type' => 'villa',
                'flat_number' => 'V-1', 'status' => 'vacant',
            ]);
        }

        $this->assertSame(2, Flat::where('flat_number', 'V-1')->count());
    }

    // ------------------------------------------ P1-19 omitted dates are legal

    public function test_a_notice_and_a_tenant_can_be_saved_without_the_dates_mariadb_used_to_demand(): void
    {
        $society = $this->makeSociety('Parity Society');
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));
        $author  = $this->makeUserWithRole($society, 'Admin');

        DB::table('notices')->insert([
            'society_id' => $society->id, 'created_by' => $author->id, 'title' => 'Draft', 'content' => 'x',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $tenant = Tenant::create([
            'society_id' => $society->id, 'flat_id' => $flat->id, 'name' => 'No Date', 'email' => 'nodate' . uniqid() . '@test.local',
            'phone' => '9' . random_int(100000000, 999999999), 'status' => 'active',
        ]);

        $this->assertNull(DB::table('notices')->where('title', 'Draft')->value('publish_date'));
        $this->assertNull($tenant->fresh()->move_in_date);
    }
}
