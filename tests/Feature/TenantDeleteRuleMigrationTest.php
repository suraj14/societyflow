<?php

namespace Tests\Feature;

use App\Models\Society;
use App\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 2026_10_10_000003 converts an EXISTING database. SchemaParityTest proves the
 * end state on a fresh install; this proves the conversion itself: put the
 * engine into the broken state the old migrations left it in, with real rows,
 * run the migration, and check that nothing was lost and the rules flipped.
 *
 *   SQLite   tenants.user_id was ON DELETE CASCADE  -> rebuilt to SET NULL
 *   MariaDB  tenants.flat_id was ON DELETE CASCADE  -> altered to SET NULL
 *   MariaDB  flats(building_id, flat_number) had no unique index -> added
 *
 * MySQL DDL commits implicitly, so the transaction RefreshDatabase wraps each
 * test in cannot undo it. The MariaDB cases therefore restore the schema and
 * delete their own society (everything below it cascades) in `finally`.
 */
class TenantDeleteRuleMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): object
    {
        return require base_path('database/migrations/2026_10_10_000003_align_tenant_delete_rules_and_flat_number_unique.php');
    }

    private function isSqlite(): bool
    {
        return DB::getDriverName() === 'sqlite';
    }

    /** @return array{0:Society,1:array} a society with a flat, a login and two tenancies */
    private function populated(): array
    {
        $society = $this->makeSociety('Migration Society ' . uniqid());
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));
        $user    = $this->makeResidentUser($society, $flat);

        $one = Tenant::create([
            'society_id' => $society->id, 'user_id' => $user->id, 'flat_id' => $flat->id, 'name' => 'First Tenant',
            'email' => 'first' . uniqid() . '@test.local', 'phone' => '9000000001', 'move_in_date' => '2026-01-01',
            'monthly_rent' => 12500, 'status' => 'active', 'family_members' => [['name' => 'Kid', 'relationship' => 'Child']],
        ]);
        $two = Tenant::create([
            'society_id' => $society->id, 'flat_id' => $flat->id, 'name' => 'Second Tenant',
            'email' => 'second' . uniqid() . '@test.local', 'phone' => '9000000002', 'status' => 'inactive',
        ]);

        return [$society, compact('flat', 'user', 'one', 'two')];
    }

    private function rule(string $table, string $column): ?string
    {
        if ($this->isSqlite()) {
            foreach (DB::select("PRAGMA foreign_key_list(\"{$table}\")") as $fk) {
                if ($fk->from === $column) {
                    return strtoupper($fk->on_delete);
                }
            }

            return null;
        }

        return DB::selectOne(
            'SELECT rc.DELETE_RULE r FROM information_schema.KEY_COLUMN_USAGE k
             JOIN information_schema.REFERENTIAL_CONSTRAINTS rc ON rc.CONSTRAINT_NAME = k.CONSTRAINT_NAME AND rc.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA
             WHERE k.TABLE_SCHEMA = DATABASE() AND k.TABLE_NAME = ? AND k.COLUMN_NAME = ? AND k.REFERENCED_TABLE_NAME IS NOT NULL',
            [$table, $column]
        )?->r;
    }

    /**
     * The state production was left in: no unique index on (building_id,
     * flat_number). The building foreign key needs SOME index on building_id,
     * so a plain one stands in for the unique one before it is dropped (a
     * database in that state necessarily had one, or MariaDB would have
     * refused to drop the unique index).
     */
    private function mysqlRemoveFlatNumberUnique(): void
    {
        DB::statement('ALTER TABLE flats ADD INDEX tmp_building_id_fixture (building_id)');
        DB::statement('ALTER TABLE flats DROP INDEX flats_building_id_flat_number_unique');
    }

    private function mysqlDropFixtureIndex(): void
    {
        if (DB::selectOne("SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'flats' AND INDEX_NAME = 'tmp_building_id_fixture'")) {
            DB::statement('ALTER TABLE flats DROP INDEX tmp_building_id_fixture');
        }
    }

    private function snapshot(): array
    {
        return DB::table('tenants')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();
    }

    // ------------------------------------------------------------- SQLite

    /** Recreate tenants exactly as the old migrations left it: user_id ON DELETE CASCADE. */
    private function sqliteBreakTenants(): void
    {
        $sql = DB::selectOne("SELECT sql FROM sqlite_master WHERE type='table' AND name='tenants'")->sql;
        $indexes = DB::table('sqlite_master')->where('type', 'index')->where('tbl_name', 'tenants')->whereNotNull('sql')->pluck('sql')->all();

        $old = preg_replace('/(references\s+"users"\s*\("id"\)\s+on\s+delete\s+)set null/i', '$1cascade', $sql, 1);
        $old = preg_replace('/^\s*create\s+table\s+"tenants"/i', 'CREATE TABLE "tenants_old"', $old, 1);
        $this->assertNotSame($sql, $old, 'fixture failed to produce the old CASCADE definition');

        DB::statement('PRAGMA foreign_keys = OFF');
        DB::statement($old);
        DB::statement('INSERT INTO "tenants_old" SELECT * FROM "tenants"');
        DB::statement('DROP TABLE "tenants"');
        DB::statement('ALTER TABLE "tenants_old" RENAME TO "tenants"');
        foreach ($indexes as $i) {
            DB::statement($i);
        }
        DB::statement('PRAGMA foreign_keys = ON');
    }

    public function test_sqlite_rebuild_flips_the_rule_and_loses_nothing(): void
    {
        if (!$this->isSqlite()) {
            $this->markTestSkipped('SQLite-only: the MariaDB equivalent is test_mariadb_alters_the_flat_rule_and_loses_nothing.');
        }

        [, $f] = $this->populated();
        $this->sqliteBreakTenants();
        $this->assertSame('CASCADE', $this->rule('tenants', 'user_id'), 'precondition: the old broken state');

        $before  = $this->snapshot();
        $indexes = DB::table('sqlite_master')->where('type', 'index')->where('tbl_name', 'tenants')->whereNotNull('sql')->orderBy('name')->pluck('sql', 'name')->all();

        $this->migration()->up();

        $this->assertSame('SET NULL', $this->rule('tenants', 'user_id'));
        $this->assertSame('SET NULL', $this->rule('tenants', 'flat_id'), 'untouched');
        $this->assertSame('CASCADE', $this->rule('tenants', 'society_id'), 'untouched');
        $this->assertSame($before, $this->snapshot(), 'every row, every column, byte for byte');
        $this->assertSame($indexes, DB::table('sqlite_master')->where('type', 'index')->where('tbl_name', 'tenants')->whereNotNull('sql')->orderBy('name')->pluck('sql', 'name')->all(), 'indexes recreated identically');
        $this->assertSame(0, DB::table('sqlite_master')->where('name', 'tenants_rebuild')->count(), 'no scratch table left behind');

        // Still enforced after the rebuild: unique email, the status check, and the new rule.
        try {
            Tenant::create(['society_id' => $f['one']->society_id, 'name' => 'Dup', 'email' => $f['one']->email, 'status' => 'active']);
            $this->fail('the unique email index should have survived the rebuild');
        } catch (QueryException $e) {
            $this->assertStringContainsString('UNIQUE', strtoupper($e->getMessage()));
        }
        $this->assertSame(1, DB::table('sqlite_master')->where('type', 'table')->where('name', 'tenants')->where('sql', 'like', "%'active', 'inactive'%")->count(), 'check constraint kept');

        $f['user']->delete();
        $this->assertNotNull(Tenant::find($f['one']->id), 'the tenancy now survives its login being deleted');
        $this->assertNull(Tenant::find($f['one']->id)->user_id);
    }

    public function test_sqlite_rebuild_is_idempotent(): void
    {
        if (!$this->isSqlite()) {
            $this->markTestSkipped('SQLite-only.');
        }

        $this->populated();
        $before = $this->snapshot();

        $this->migration()->up();   // already SET NULL on a fresh install
        $this->migration()->up();

        $this->assertSame($before, $this->snapshot());
        $this->assertSame('SET NULL', $this->rule('tenants', 'user_id'));
    }

    // ------------------------------------------------------------ MariaDB

    public function test_mariadb_alters_the_flat_rule_and_loses_nothing(): void
    {
        if ($this->isSqlite()) {
            $this->markTestSkipped('MariaDB-only: the SQLite equivalent is test_sqlite_rebuild_flips_the_rule_and_loses_nothing.');
        }

        [$society, $f] = $this->populated();

        try {
            DB::statement('ALTER TABLE tenants DROP FOREIGN KEY tenants_flat_id_foreign');
            DB::statement('ALTER TABLE tenants ADD CONSTRAINT tenants_flat_id_foreign FOREIGN KEY (flat_id) REFERENCES flats (id) ON DELETE CASCADE');
            $this->assertSame('CASCADE', $this->rule('tenants', 'flat_id'), 'precondition: the old broken state');

            $before = $this->snapshot();

            $this->migration()->up();

            $this->assertSame('SET NULL', $this->rule('tenants', 'flat_id'));
            $this->assertSame('SET NULL', $this->rule('tenants', 'user_id'), 'untouched');
            $this->assertSame($before, $this->snapshot(), 'no row changed');

            DB::table('flats')->where('id', $f['flat']->id)->delete();
            $this->assertSame(2, DB::table('tenants')->where('society_id', $society->id)->count(), 'tenancies outlive a deleted flat');
            $this->assertSame(2, DB::table('tenants')->where('society_id', $society->id)->whereNull('flat_id')->count());

            $this->migration()->up(); // idempotent
            $this->assertSame('SET NULL', $this->rule('tenants', 'flat_id'));
        } finally {
            $this->migration()->up();
            DB::table('societies')->where('id', $society->id)->delete();
        }
    }

    public function test_mariadb_adds_the_unique_flat_number_rule_when_there_are_no_duplicates(): void
    {
        if ($this->isSqlite()) {
            $this->markTestSkipped('MariaDB-only.');
        }

        [$society, $f] = $this->populated();

        try {
            $this->mysqlRemoveFlatNumberUnique();
            $this->assertNull(DB::selectOne("SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'flats' AND INDEX_NAME = 'flats_building_id_flat_number_unique'"), 'precondition: no unique index');

            $this->migration()->up();

            $this->expectException(QueryException::class);
            $this->makeFlat($society, \App\Models\Building::find($f['flat']->building_id), $f['flat']->flat_number);
        } finally {
            $this->migration()->up();
            $this->mysqlDropFixtureIndex();
            DB::table('societies')->where('id', $society->id)->delete();
        }
    }

    public function test_mariadb_refuses_to_add_the_unique_rule_over_existing_duplicates_and_changes_nothing(): void
    {
        if ($this->isSqlite()) {
            $this->markTestSkipped('MariaDB-only.');
        }

        [$society, $f] = $this->populated();

        try {
            $this->mysqlRemoveFlatNumberUnique();
            // The production situation this guards: a duplicate that already exists.
            $duplicate = DB::table('flats')->where('id', $f['flat']->id)->first();
            $row = (array) $duplicate;
            unset($row['id']);
            DB::table('flats')->insert($row);
            $flatsBefore = DB::table('flats')->where('building_id', $f['flat']->building_id)->count();
            $this->assertSame(2, $flatsBefore);

            try {
                $this->migration()->up();
                $this->fail('the migration must stop when duplicates exist');
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('duplicates already exist', $e->getMessage());
                $this->assertStringContainsString("flat {$f['flat']->flat_number}", $e->getMessage());
                $this->assertStringContainsString('Nothing was changed', $e->getMessage());
            }

            $this->assertSame($flatsBefore, DB::table('flats')->where('building_id', $f['flat']->building_id)->count(), 'no flat merged or deleted');
            $this->assertNull(DB::selectOne("SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'flats' AND INDEX_NAME = 'flats_building_id_flat_number_unique'"));
        } finally {
            DB::table('societies')->where('id', $society->id)->delete();
            $this->migration()->up();
            $this->mysqlDropFixtureIndex();
        }
    }
}
