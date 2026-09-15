<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gives flats.villa_area_id its foreign key on MySQL.
 *
 * Same cause as flats.owner_id in 2026_09_15_000003: the migrations that
 * declare the constraint (2026_01_23_120000 and 2026_01_27_111259) do so
 * inside SQLite-only table rebuilds, so MySQL never received it. A villa
 * could therefore point at a villa area that no longer exists.
 *
 * ON DELETE CASCADE is the canonical rule, chosen on evidence rather than
 * preference:
 *
 *   - the live SQLite schema carries CASCADE;
 *   - the three most recent declarations all say CASCADE (the original
 *     2024_01_01_000019 said SET NULL but was superseded by every rebuild
 *     since);
 *   - flats.building_id -> buildings is CASCADE on BOTH engines already,
 *     and a villa area is to villas exactly what a building is to
 *     apartments, so the two containers behave alike;
 *   - VillaAreaController::destroy refuses to delete an area that still has
 *     villas, so in normal operation the cascade never fires - it is a
 *     backstop against direct database deletion, not a routine path.
 *
 * The column keeps its nullability: apartments have no villa area, so NULL
 * is the normal state for most rows.
 *
 * No column is altered and the table is not rebuilt; MySQL takes a single
 * ADD CONSTRAINT.
 */
return new class extends Migration
{
    private const FK_NAME = 'flats_villa_area_id_foreign';

    public function up(): void
    {
        if (!Schema::hasTable('flats') || !Schema::hasColumn('flats', 'villa_area_id')) {
            return;
        }

        if (DB::getDriverName() === 'sqlite') {
            return; // the rebuild migrations already created it
        }

        if ($this->foreignKeyExists()) {
            return;
        }

        $this->assertColumnMatchesVillaAreaId();

        // A dangling value would make the constraint impossible to apply.
        // Only references to a villa area that does not exist are cleared;
        // flats with no villa area are already NULL and are left alone.
        $ids = DB::table('flats')
            ->whereNotNull('villa_area_id')
            ->whereNotIn('villa_area_id', function ($query) {
                $query->select('id')->from('villa_areas');
            })
            ->pluck('id');

        if ($ids->isNotEmpty()) {
            echo '  clearing ' . $ids->count() . ' dangling flats.villa_area_id value(s) on flat ids: '
                . $ids->implode(', ') . "\n";
            DB::table('flats')->whereIn('id', $ids)->update(['villa_area_id' => null]);
        }

        DB::statement(
            'ALTER TABLE flats ADD CONSTRAINT ' . self::FK_NAME
            . ' FOREIGN KEY (villa_area_id) REFERENCES villa_areas (id) ON DELETE CASCADE'
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return; // nothing was added here
        }

        if (!Schema::hasTable('flats') || !$this->foreignKeyExists()) {
            return;
        }

        // Removes only the constraint this migration added.
        DB::statement('ALTER TABLE flats DROP FOREIGN KEY ' . self::FK_NAME);
    }

    private function foreignKeyExists(): bool
    {
        return DB::selectOne(
            'SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?
               AND REFERENCED_TABLE_NAME IS NOT NULL',
            ['flats', self::FK_NAME]
        ) !== null;
    }

    /** Refuse rather than silently MODIFY a column whose type has drifted. */
    private function assertColumnMatchesVillaAreaId(): void
    {
        $types = collect(DB::select(
            'SELECT TABLE_NAME, COLUMN_TYPE, IS_NULLABLE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND ((TABLE_NAME = ? AND COLUMN_NAME = ?) OR (TABLE_NAME = ? AND COLUMN_NAME = ?))',
            ['flats', 'villa_area_id', 'villa_areas', 'id']
        ))->keyBy('TABLE_NAME');

        $flat = $types->get('flats');
        $area = $types->get('villa_areas');

        if (!$flat || !$area) {
            throw new RuntimeException('Cannot read column types for flats.villa_area_id / villa_areas.id');
        }

        if (strtolower($flat->COLUMN_TYPE) !== strtolower($area->COLUMN_TYPE)) {
            throw new RuntimeException(
                "flats.villa_area_id is {$flat->COLUMN_TYPE} but villa_areas.id is {$area->COLUMN_TYPE}; "
                . 'they must match before a foreign key can be added.'
            );
        }

        if ($flat->IS_NULLABLE !== 'YES') {
            throw new RuntimeException(
                'flats.villa_area_id must stay nullable - apartments have no villa area.'
            );
        }
    }
};
