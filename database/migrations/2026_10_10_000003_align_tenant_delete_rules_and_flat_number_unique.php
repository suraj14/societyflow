<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * docs/SCHEMA_PARITY.md P1-17 and P1-18. Each engine had a different gap, so
 * each engine gets a different fix; the end state is identical.
 *
 * P1-17 - a tenancy must survive either side being deleted (rent history hangs
 * off it). The latest declared intent for both columns is ON DELETE SET NULL
 * (2026_01_22 for user_id, 2026_01_28 for flat_id), but the engines drifted in
 * opposite directions because each migration only rebuilt one engine's copy:
 *
 *                       SQLite      MariaDB
 *   tenants.user_id     CASCADE     SET NULL   -> SQLite fixed here
 *   tenants.flat_id     SET NULL    CASCADE    -> MariaDB fixed here
 *
 * P1-18 - flats(building_id, flat_number) was UNIQUE on SQLite only
 * (2024_01_01_000019 dropped it on MySQL and every later re-creation sat
 * inside an SQLite-only rebuild), so two "101"s in one building were possible
 * in production and impossible in development. Villas have no building (NULL)
 * and are unaffected: NULLs never collide in a unique index.
 *
 * Safety:
 *  - No row is changed or deleted. The SQLite rebuild copies every row and
 *    refuses to swap the table in unless the row count matches.
 *  - The SQLite rebuild edits only the user_id clause of the table's LIVE
 *    definition, so every other column, check constraint and index is kept
 *    exactly - including on a database that has drifted from a fresh install.
 *  - If duplicate flat numbers already exist the unique index is NOT added and
 *    the migration stops with the offending rows listed; nothing is merged or
 *    deleted on the operator's behalf.
 *  - Idempotent: each step first checks whether it is already done.
 */
return new class extends Migration
{
    private const USER_FK_PATTERN =
        '/(foreign\s+key\s*\(\s*["`]?user_id["`]?\s*\)\s*references\s*["`]?users["`]?\s*\(\s*["`]?id["`]?\s*\)\s*on\s+delete\s+)cascade/i';

    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            $this->sqliteTenantUserSetNull();

            return;
        }

        $this->mysqlTenantFlatSetNull();
        $this->mysqlFlatNumberUnique();
    }

    public function down(): void
    {
        // The unique index is a pure additive constraint, so it can be
        // removed again. The two delete rules are deliberately NOT put back
        // to CASCADE: that would reinstate the exact silent loss of tenancy
        // (and rent history) this migration exists to stop, and a rollback
        // is the moment least likely to be reviewed for that.
        if (DB::getDriverName() !== 'sqlite' && Schema::hasTable('flats')) {
            if ($this->indexExists('flats', 'flats_building_id_flat_number_unique')) {
                // MySQL/MariaDB refuse to drop an index that the building_id
                // foreign key is relying on (error 1553). When the unique index
                // is the only one led by building_id, give the FK a plain one
                // first - otherwise this rollback fails on the engine that
                // matters.
                $otherLeadingIndex = DB::selectOne(
                    'SELECT COUNT(*) AS n FROM information_schema.statistics
                      WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?
                        AND seq_in_index = 1 AND index_name <> ?',
                    ['flats', 'building_id', 'flats_building_id_flat_number_unique']
                )->n;

                if ((int) $otherLeadingIndex === 0) {
                    DB::statement('ALTER TABLE flats ADD INDEX flats_building_id_index (building_id)');
                }

                DB::statement('ALTER TABLE flats DROP INDEX flats_building_id_flat_number_unique');
            }
        }
    }

    // ----------------------------------------------------------------- SQLite

    private function sqliteTenantUserSetNull(): void
    {
        if (!Schema::hasTable('tenants')) {
            return;
        }

        $sql = DB::selectOne("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = 'tenants'")?->sql;

        // Already SET NULL (or no such constraint): nothing to do.
        if (!$sql || !preg_match(self::USER_FK_PATTERN, $sql)) {
            return;
        }

        $rebuild = preg_replace(self::USER_FK_PATTERN, '$1set null', $sql, 1);
        $rebuild = preg_replace('/^\s*create\s+table\s+["`]?tenants["`]?/i', 'CREATE TABLE "tenants_rebuild"', $rebuild, 1);

        // Captured before the drop; the names become free again afterwards.
        $indexes = DB::table('sqlite_master')
            ->where('type', 'index')->where('tbl_name', 'tenants')->whereNotNull('sql')
            ->pluck('sql')->all();

        $expected = DB::table('tenants')->count();

        Schema::dropIfExists('tenants_rebuild');

        // Nothing references tenants, but the swap must not fire any
        // constraint on the users/flats/owners side while the table is absent.
        Schema::disableForeignKeyConstraints();

        try {
            DB::transaction(function () use ($rebuild, $indexes, $expected) {
                DB::statement($rebuild);
                DB::statement('INSERT INTO "tenants_rebuild" SELECT * FROM "tenants"');

                $copied = DB::table('tenants_rebuild')->count();
                if ($copied !== $expected) {
                    throw new RuntimeException("tenants rebuild copied {$copied} of {$expected} rows; the original table was left untouched.");
                }

                DB::statement('DROP TABLE "tenants"');
                DB::statement('ALTER TABLE "tenants_rebuild" RENAME TO "tenants"');

                foreach ($indexes as $createIndex) {
                    DB::statement($createIndex);
                }
            });
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    // ---------------------------------------------------------- MySQL/MariaDB

    private function mysqlTenantFlatSetNull(): void
    {
        if (!Schema::hasTable('tenants') || !Schema::hasColumn('tenants', 'flat_id')) {
            return;
        }

        if ($this->deleteRule('tenants', 'flat_id') === 'SET NULL') {
            return;
        }

        // SET NULL needs a nullable column; keep the exact type it has now.
        $column = DB::selectOne(
            'SELECT COLUMN_TYPE ct, IS_NULLABLE n FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            ['tenants', 'flat_id']
        );
        if ($column && $column->n !== 'YES') {
            DB::statement("ALTER TABLE tenants MODIFY flat_id {$column->ct} NULL");
        }

        foreach ($this->foreignKeysOn('tenants', 'flat_id') as $name) {
            DB::statement("ALTER TABLE tenants DROP FOREIGN KEY `{$name}`");
        }

        DB::statement(
            'ALTER TABLE tenants ADD CONSTRAINT tenants_flat_id_foreign
             FOREIGN KEY (flat_id) REFERENCES flats (id) ON DELETE SET NULL'
        );
    }

    private function mysqlFlatNumberUnique(): void
    {
        if (!Schema::hasTable('flats')) {
            return;
        }

        if ($this->hasUniqueIndexOn('flats', ['building_id', 'flat_number'])) {
            return;
        }

        $duplicates = DB::select(
            'SELECT building_id, flat_number, COUNT(*) AS copies FROM flats
             WHERE building_id IS NOT NULL
             GROUP BY building_id, flat_number HAVING COUNT(*) > 1
             ORDER BY building_id, flat_number LIMIT 20'
        );

        if ($duplicates) {
            $list = collect($duplicates)->map(fn ($d) => "building {$d->building_id} / flat {$d->flat_number} x{$d->copies}")->implode('; ');

            throw new RuntimeException(
                'Cannot enforce one flat number per building: duplicates already exist (' . $list . '). '
                . 'Nothing was changed. Renumber or merge the duplicate flats, then run the migration again.'
            );
        }

        DB::statement('ALTER TABLE flats ADD UNIQUE flats_building_id_flat_number_unique (building_id, flat_number)');
    }

    // ---------------------------------------------------------------- helpers

    private function deleteRule(string $table, string $column): ?string
    {
        return DB::selectOne(
            'SELECT rc.DELETE_RULE rule FROM information_schema.KEY_COLUMN_USAGE kcu
             JOIN information_schema.REFERENTIAL_CONSTRAINTS rc
               ON rc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME AND rc.CONSTRAINT_SCHEMA = kcu.CONSTRAINT_SCHEMA
             WHERE kcu.TABLE_SCHEMA = DATABASE() AND kcu.TABLE_NAME = ? AND kcu.COLUMN_NAME = ?
               AND kcu.REFERENCED_TABLE_NAME IS NOT NULL',
            [$table, $column]
        )?->rule;
    }

    /** @return string[] */
    private function foreignKeysOn(string $table, string $column): array
    {
        return array_map(
            fn ($r) => $r->name,
            DB::select(
                'SELECT CONSTRAINT_NAME name FROM information_schema.KEY_COLUMN_USAGE
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL',
                [$table, $column]
            )
        );
    }

    private function indexExists(string $table, string $name): bool
    {
        return DB::selectOne(
            'SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1',
            [$table, $name]
        ) !== null;
    }

    /** True when a UNIQUE index covers exactly these columns, in this order. */
    private function hasUniqueIndexOn(string $table, array $columns): bool
    {
        $rows = DB::select(
            'SELECT INDEX_NAME i, SEQ_IN_INDEX s, COLUMN_NAME c FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND NON_UNIQUE = 0
             ORDER BY INDEX_NAME, SEQ_IN_INDEX',
            [$table]
        );

        $byIndex = [];
        foreach ($rows as $r) {
            $byIndex[$r->i][] = $r->c;
        }

        foreach ($byIndex as $cols) {
            if ($cols === $columns) {
                return true;
            }
        }

        return false;
    }
};
