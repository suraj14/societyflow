<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The Society Info migration's down() once failed on SQLite ("multiple calls
 * to dropColumn"). A migration that cannot be rolled back is a deploy risk, so
 * every reversible migration added for the 2026-10 release must run down() and
 * up() cleanly on whichever engine the suite runs on.
 */
class PendingMigrationsRollbackTest extends TestCase
{
    use RefreshDatabase;

    private function migration(string $name): object
    {
        return require database_path("migrations/{$name}.php");
    }

    public function test_society_info_migration_rolls_back_and_reapplies(): void
    {
        $m = $this->migration('2026_10_10_000002_add_society_info_fields');

        $this->assertTrue(Schema::hasColumn('societies', 'registration_number'));
        $this->assertTrue(Schema::hasColumn('societies', 'registered_on'));
        $this->assertTrue(Schema::hasColumn('users', 'committee_designation'));

        $m->down();

        $this->assertFalse(Schema::hasColumn('societies', 'registration_number'));
        $this->assertFalse(Schema::hasColumn('societies', 'registered_on'));
        $this->assertFalse(Schema::hasColumn('users', 'committee_designation'));
        $this->assertTrue(Schema::hasColumn('societies', 'name'), 'existing columns are untouched');

        $m->up();

        $this->assertTrue(Schema::hasColumn('societies', 'registration_number'));
        $this->assertTrue(Schema::hasColumn('societies', 'registered_on'));
        $this->assertTrue(Schema::hasColumn('users', 'committee_designation'));
    }

    public function test_tenant_rule_migration_rolls_back_and_reapplies_without_losing_rows(): void
    {
        $society = $this->makeSociety();
        $building = $this->makeBuilding($society);
        $flat = $this->makeFlat($society, $building, '301');
        \App\Models\Tenant::create(['society_id' => $society->id, 'flat_id' => $flat->id, 'name' => 'T', 'email' => 't@t.local', 'phone' => '9000000001', 'move_in_date' => now(), 'status' => 'active']);
        $m = $this->migration('2026_10_10_000003_align_tenant_delete_rules_and_flat_number_unique');

        try {
            $m->down();
            $this->assertSame(1, \App\Models\Tenant::count());

            $m->up();
            $this->assertSame(1, \App\Models\Tenant::count());
            $this->assertSame(1, \App\Models\Flat::count());
        } finally {
            // On MySQL/MariaDB the migration's DDL implicitly COMMITs, so the rows above survive the test's
            // rollback and leak into every later test that shares this database (a tenant-list assertion
            // elsewhere then saw a stray "T"). Remove exactly what this test created.
            \App\Models\Tenant::where('society_id', $society->id)->delete();
            \App\Models\Flat::where('society_id', $society->id)->delete();
            \Illuminate\Support\Facades\DB::table('buildings')->where('society_id', $society->id)->delete();
            \App\Models\Society::whereKey($society->id)->delete();
        }
    }
}
