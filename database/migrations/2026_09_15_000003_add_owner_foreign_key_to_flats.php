<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gives flats.owner_id its foreign key on MySQL.
 *
 * Two migrations declare the constraint:
 *
 *   2026_01_23_120000_add_on_rent_status_to_flats
 *   2026_01_27_111259_fix_flats_status_constraint_final
 *
 * but both do so inside `if (DB::getDriverName() === 'sqlite')` blocks that
 * rebuild the table, because SQLite cannot add a constraint in place. On
 * MySQL those blocks never run, so the column has always been a plain
 * unsignedBigInteger there - no constraint, no index. SQLite ends up
 * correct and MySQL, the production engine, does not.
 *
 * The consequence: deleting an owner leaves flats.owner_id pointing at a
 * row that no longer exists. Because owners.id and users.id overlap, a
 * later owner reusing that number silently inherits the property. This is
 * the same defect class fixed for users.owner_id in 2026_09_15_000001, and
 * the more consequential half.
 *
 * ON DELETE SET NULL is what the existing schema already uses: SQLite
 * carries exactly that rule today, users.owner_id was given the same, and
 * the column is nullable by design - 109 of 121 flats in development have
 * no owner, because most units genuinely have none. CASCADE would delete
 * flats when an owner record is removed, which would be catastrophic and
 * is plainly not the intent.
 *
 * No column is altered: flats.owner_id is already bigint unsigned nullable
 * on MySQL, matching owners.id exactly. The table is not rebuilt.
 */
return new class extends Migration
{
    private const FK_NAME = 'flats_owner_id_foreign';

    public function up(): void
    {
        if (!Schema::hasTable('flats') || !Schema::hasColumn('flats', 'owner_id')) {
            return;
        }

        if (DB::getDriverName() === 'sqlite') {
            // SQLite already has the constraint from the rebuild migrations
            // above. Adding one here would mean recreating a table that 40-odd
            // rows of foreign keys point at, for no gain.
            if (!$this->sqliteHasOwnerForeignKey()) {
                echo "  NOTE: sqlite is missing flats.owner_id -> owners; it is normally created by\n"
                   . "        2026_01_27_111259_fix_flats_status_constraint_final. Not rebuilt here.\n";
            }

            return;
        }

        // Some installs may carry the historical constraint that pointed at
        // `users` (2024_01_01_000019 declared constrained('users')). If one
        // is present it has to go before the correct constraint can be added.
        $existing = $this->foreignKeyTarget(self::FK_NAME);

        if ($existing === 'owners') {
            return; // already correct
        }

        if ($existing !== null) {
            echo "  dropping legacy {$existing} reference on flats.owner_id\n";
            DB::statement('ALTER TABLE flats DROP FOREIGN KEY ' . self::FK_NAME);
        }

        $this->assertColumnMatchesOwnersId();

        // A dangling value would make the constraint impossible to apply.
        // Only references to an owner that does not exist are cleared, which
        // is exactly what SET NULL would have done had the constraint been
        // present when that owner was deleted. Genuinely unassigned flats are
        // already NULL and are not touched.
        $dangling = DB::table('flats')
            ->whereNotNull('owner_id')
            ->whereNotIn('owner_id', function ($query) {
                $query->select('id')->from('owners');
            });

        $ids = $dangling->pluck('id');

        if ($ids->isNotEmpty()) {
            echo '  clearing ' . $ids->count() . ' dangling flats.owner_id value(s) on flat ids: '
                . $ids->implode(', ') . "\n";
            DB::table('flats')->whereIn('id', $ids)->update(['owner_id' => null]);
        }

        DB::statement(
            'ALTER TABLE flats ADD CONSTRAINT ' . self::FK_NAME
            . ' FOREIGN KEY (owner_id) REFERENCES owners (id) ON DELETE SET NULL'
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return; // nothing was added here
        }

        if (!Schema::hasTable('flats') || $this->foreignKeyTarget(self::FK_NAME) === null) {
            return;
        }

        // Removes only the constraint this migration added. The column, its
        // values and every other index are left exactly as they are.
        DB::statement('ALTER TABLE flats DROP FOREIGN KEY ' . self::FK_NAME);
    }

    /**
     * The table a named foreign key on `flats` points at, or null if the
     * constraint does not exist. MySQL/MariaDB only.
     */
    private function foreignKeyTarget(string $name): ?string
    {
        $row = DB::selectOne(
            'SELECT REFERENCED_TABLE_NAME FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?
               AND REFERENCED_TABLE_NAME IS NOT NULL',
            ['flats', $name]
        );

        return $row->REFERENCED_TABLE_NAME ?? null;
    }

    /**
     * Refuse to proceed if the column is not exactly owners.id's type.
     *
     * Silently MODIFYing it could truncate live ids; a loud failure is the
     * correct outcome, since that would mean the schema has drifted in a way
     * this migration was not written to handle.
     */
    private function assertColumnMatchesOwnersId(): void
    {
        $types = collect(DB::select(
            'SELECT TABLE_NAME, COLUMN_TYPE, IS_NULLABLE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND ((TABLE_NAME = ? AND COLUMN_NAME = ?) OR (TABLE_NAME = ? AND COLUMN_NAME = ?))',
            ['flats', 'owner_id', 'owners', 'id']
        ))->keyBy('TABLE_NAME');

        $flat  = $types->get('flats');
        $owner = $types->get('owners');

        if (!$flat || !$owner) {
            throw new RuntimeException('Cannot read column types for flats.owner_id / owners.id');
        }

        if (strtolower($flat->COLUMN_TYPE) !== strtolower($owner->COLUMN_TYPE)) {
            throw new RuntimeException(
                "flats.owner_id is {$flat->COLUMN_TYPE} but owners.id is {$owner->COLUMN_TYPE}; "
                . 'they must match before a foreign key can be added.'
            );
        }

        if ($flat->IS_NULLABLE !== 'YES') {
            throw new RuntimeException(
                'flats.owner_id must stay nullable - most flats genuinely have no owner.'
            );
        }
    }

    private function sqliteHasOwnerForeignKey(): bool
    {
        foreach (DB::select('PRAGMA foreign_key_list(flats)') as $fk) {
            if ($fk->from === 'owner_id' && $fk->table === 'owners') {
                return true;
            }
        }

        return false;
    }
};
