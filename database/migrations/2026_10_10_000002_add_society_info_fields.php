<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The informational data "Society Info" shows that the schema had nowhere to
 * keep. Deliberately the smallest additive change, not a committee module:
 *
 *   societies.registration_number  the society's registration number
 *   societies.registered_on        the date it was registered / established
 *   users.committee_designation    "President", "Secretary", "Treasurer", ...
 *
 * A committee member is simply an existing user of the society with a
 * designation set. Their name, photo and phone come from their ordinary
 * profile - no second person record, no duplicate account, no new table, and
 * nothing here manages elections, meetings or voting.
 *
 * All three are nullable with no default: NULL means "not recorded", which the
 * screens show as "Not available" - nothing is invented for existing rows.
 * Plain ALTER ADD COLUMN on both engines (SQLite needs no table rebuild for a
 * nullable column), so no existing row is rewritten.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('societies')) {
            Schema::table('societies', function (Blueprint $table) {
                if (!Schema::hasColumn('societies', 'registration_number')) {
                    $table->string('registration_number', 100)->nullable();
                }
                if (!Schema::hasColumn('societies', 'registered_on')) {
                    $table->date('registered_on')->nullable();
                }
            });
        }

        if (Schema::hasTable('users') && !Schema::hasColumn('users', 'committee_designation')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('committee_designation', 100)->nullable();
            });
        }
    }

    public function down(): void
    {
        // These three columns are new and hold only what an admin typed into
        // them since this migration ran, so rolling back drops exactly that
        // and nothing that existed before. (Contrast the avatar backfill,
        // which copies pre-existing data and therefore cannot be undone
        // safely.)
        if (Schema::hasTable('societies')) {
            // One Schema::table per column: SQLite refuses several dropColumn
            // calls in a single blueprint, which made this rollback fail there.
            foreach (['registration_number', 'registered_on'] as $column) {
                if (Schema::hasColumn('societies', $column)) {
                    Schema::table('societies', function (Blueprint $table) use ($column) {
                        $table->dropColumn($column);
                    });
                }
            }
        }

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'committee_designation')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('committee_designation');
            });
        }
    }
};
