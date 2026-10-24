<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `email_logs.status` was created as ENUM('pending','sent','failed','bounced'). The delivery log now also records `blocked` (safety switch /
 * allow-list) and `simulated` (log-only mailer). SQLite got a plain string column from the 2026_10_07_000005 table rebuild, but MySQL/MariaDB
 * kept the ENUM, so every blocked or simulated e-mail failed to log ("Data truncated for column 'status'") and its job reported a failure.
 * Existing values are strings in the new column, so no row changes. SQLite is already correct and is left alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('email_logs') || !in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        DB::statement("ALTER TABLE email_logs MODIFY status VARCHAR(20) NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        // Deliberately empty: narrowing back to the ENUM would truncate any blocked/simulated rows written since.
    }
};
