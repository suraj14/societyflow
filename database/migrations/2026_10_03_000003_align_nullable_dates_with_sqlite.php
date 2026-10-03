<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * P1-19 (docs/SCHEMA_PARITY.md): tenants.move_in_date and
 * notices.publish_date are NOT NULL on MySQL/MariaDB but nullable on SQLite,
 * because the SQLite-only table rebuilds that relaxed them never had a MySQL
 * branch. Code paths that omit either value (a tenant record created before
 * the move-in is known; a draft notice) work in development and throw
 * "Field doesn't have a default value" in production. The declared intent
 * in both rebuild migrations is nullable, so MySQL is brought in line.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return; // SQLite already matches
        }

        if (Schema::hasColumn('tenants', 'move_in_date')) {
            DB::statement('ALTER TABLE tenants MODIFY move_in_date DATE NULL');
        }

        if (Schema::hasColumn('notices', 'publish_date')) {
            DB::statement('ALTER TABLE notices MODIFY publish_date DATE NULL');
        }
    }

    public function down(): void
    {
        // Not reversed: NOT NULL cannot be restored while null rows may exist.
    }
};
