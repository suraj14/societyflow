<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * On MySQL / MariaDB with explicit_defaults_for_timestamp OFF (MariaDB 10.4 and MySQL 5.7 default), the first
 * `timestamp` column of a table that is NOT NULL and has no default silently becomes
 * `DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP`. Three columns of ours were declared that way:
 *
 *   mobile_otps.expires_at                              every wrong guess / "mark used" rewrote the expiry to the
 *                                                       database clock, which (database in the local zone, application
 *                                                       in UTC) read as hours in the future - an expired code stayed valid
 *   notice_reads.read_at                                a later UPDATE of the row changed the time it was read
 *   society_document_acknowledgements.acknowledged_at   same: the time someone acknowledged a document was overwritten
 *
 * Found by the MariaDB run of OtpLifecycleTest (SQLite has no such behaviour). The fix keeps each column NOT NULL with an
 * explicit default and NO "on update". Nothing else about the data changes; SQLite needs nothing.
 *
 * Rows already written keep whatever value they have: an acknowledgement or read time that was overwritten by the old
 * behaviour cannot be reconstructed.
 */
return new class extends Migration
{
    private const COLUMNS = [
        ['mobile_otps', 'expires_at'],
        ['notice_reads', 'read_at'],
        ['society_document_acknowledgements', 'acknowledged_at'],
    ];

    public function up(): void
    {
        if (!in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        foreach (self::COLUMNS as [$table, $column]) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, $column)) {
                DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP");
            }
        }
    }

    public function down(): void
    {
        // Putting the automatic rewrite back would reintroduce the defect; there is nothing to undo.
    }
};
