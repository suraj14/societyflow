<?php

namespace Tests\Feature;

use App\Models\Flat;
use App\Models\Society;
use App\Models\VillaArea;
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
}
