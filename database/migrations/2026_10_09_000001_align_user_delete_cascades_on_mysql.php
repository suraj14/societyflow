<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fixes docs/SCHEMA_PARITY.md's P0 finding: deleting a user destroyed gate
 * and residency history on MariaDB.
 *
 *   | | SQLite | MariaDB (before this migration) |
 *   |---|---|---|
 *   | residents.user_id ON DELETE   | SET NULL | CASCADE |
 *   | visitors.host_user_id ON DELETE | SET NULL | CASCADE |
 *
 * Both columns were declared CASCADE in the 2024 original and deliberately
 * changed to SET NULL afterwards - residents.user_id in 2026_01_16,
 * visitors.host_user_id in 2026_01_10 (twice) and again in 2026_09_07 -
 * but every one of those migrations only rebuilt the SQLite copy of the
 * table (SQLiteGrammar::compileForeign() is a no-op on an *existing*
 * table, so a rebuild is the only way SQLite ever gets a constraint
 * change). MySQL/MariaDB kept running the original 2024 CASCADE the whole
 * time, with nothing logged to notice.
 *
 * SET NULL is correct, not merely convenient: every one of the four
 * visitor-creation call sites always sets a host (confirmed by grep across
 * Api/VisitorApiController, GuardController, VillaOwner/VisitorController,
 * VisitorController - see docs/SCHEMA_PARITY.md P1-15), so NOT NULL was
 * right *at insert*. It is wrong *at delete*: a visitor log is a security
 * record, and OwnerController::destroy / Admin\TenantController::destroy
 * delete the linked user as routine operation, not an edge case. Losing the
 * visitor/residency row because the account was removed is silent,
 * irreversible data loss. No application code anywhere expects the
 * CASCADE - both controllers above delete the owner/tenant row themselves
 * rather than relying on it.
 *
 * visitors.host_user_id must become nullable before SET NULL can be
 * applied to it (MySQL refuses ON DELETE SET NULL on a NOT NULL column) -
 * this is the second half of the same fix, tracked as P1-15.
 *
 * SQLite already has both columns correct (confirmed in
 * 2026_01_16_make_resident_user_id_nullable.php and
 * 2026_09_07_000003_repair_visitors_table.php) - this migration only
 * touches MySQL/MariaDB.
 */
return new class extends Migration
{
    private const FIXES = [
        ['table' => 'residents', 'column' => 'user_id', 'fk' => 'residents_user_id_foreign', 'ref' => 'users', 'needsNullable' => false],
        ['table' => 'visitors', 'column' => 'host_user_id', 'fk' => 'visitors_host_user_id_foreign', 'ref' => 'users', 'needsNullable' => true],
    ];

    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return; // already SET NULL + nullable on both columns
        }

        foreach (self::FIXES as $fix) {
            if (!Schema::hasTable($fix['table']) || !Schema::hasColumn($fix['table'], $fix['column'])) {
                continue;
            }

            $this->dropExistingForeignKey($fix['table'], $fix['column']);

            if ($fix['needsNullable']) {
                Schema::table($fix['table'], function (Blueprint $table) use ($fix) {
                    $table->unsignedBigInteger($fix['column'])->nullable()->change();
                });
            }

            Schema::table($fix['table'], function (Blueprint $table) use ($fix) {
                $table->foreign($fix['column'], $fix['fk'])
                    ->references('id')->on($fix['ref'])
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        // Deliberately not reversible: restoring CASCADE would reintroduce
        // the exact silent data-loss bug this migration exists to close,
        // and visitors.host_user_id going back to NOT NULL would fail the
        // instant a row this migration already allowed to be null exists.
    }

    /**
     * Drops whichever foreign key currently sits on $table.$column,
     * regardless of its name - the original 2024 migration may have named
     * it differently from this file's own convention, and some deployments
     * went through an intermediate rebuild that could have renamed it too.
     */
    private function dropExistingForeignKey(string $table, string $column): void
    {
        $constraints = DB::select(
            'SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
               AND REFERENCED_TABLE_NAME IS NOT NULL',
            [$table, $column]
        );

        foreach ($constraints as $constraint) {
            DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$constraint->CONSTRAINT_NAME}`");
        }
    }
};
