<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Gives societies a public identifier for URLs, keeping the numeric key.
 *
 * `societies.id` is untouched: 40 tables carry a foreign key onto it, and
 * migrating those to a string key would be a far larger and riskier change
 * than the problem warrants. `public_id` is additive - the integer stays
 * the join key, the ULID becomes the only thing a browser or API client
 * ever sees.
 *
 * Runs in the four steps the column needs to arrive safely on a table that
 * already has rows:
 *
 *   1. add it nullable          (existing rows cannot satisfy NOT NULL yet)
 *   2. backfill every row       (one ULID each, generated per row)
 *   3. add the unique index     (proves the backfill produced no collision)
 *   4. enforce NOT NULL         (only now that every row has a value)
 *
 * Step 4 is engine-specific. MySQL can MODIFY the column in place. SQLite
 * cannot, so the table is rebuilt - which is why the index is created after
 * the swap: SQLite index names are unique across the whole database, not per
 * table, so declaring them on the staging table collides with the live ones.
 *
 * societies has no outgoing foreign keys of its own, and the 40 incoming
 * ones reference it by name, so they survive the drop-and-rename.
 */
return new class extends Migration
{
    private const COLUMN = 'public_id';
    private const LENGTH = 26; // ULID is always 26 characters

    /** The six indexes societies already carries, recreated after a rebuild. */
    private const EXISTING_INDEXES = [
        ['unique', 'slug', 'societies_slug_unique'],
        ['unique', 'subdomain', 'societies_subdomain_unique'],
        ['index', ['status', 'subscription_ends_at'], 'societies_status_subscription_ends_at_index'],
        ['index', ['admin_id', 'status'], 'societies_admin_id_status_index'],
        ['index', ['subscription_plan_id', 'status'], 'societies_subscription_plan_id_status_index'],
        ['index', 'property_type', 'societies_property_type_index'],
    ];

    public function up(): void
    {
        if (!Schema::hasTable('societies')) {
            return;
        }

        // 1. nullable
        if (!Schema::hasColumn('societies', self::COLUMN)) {
            Schema::table('societies', function (Blueprint $table) {
                $table->string(self::COLUMN, self::LENGTH)->nullable()->after('id');
            });
        }

        // 2. backfill - one ULID per row, never a shared or derived value
        $filled = 0;
        DB::table('societies')->whereNull(self::COLUMN)->orderBy('id')
            ->select('id')->chunk(200, function ($rows) use (&$filled) {
                foreach ($rows as $row) {
                    DB::table('societies')->where('id', $row->id)
                        ->update([self::COLUMN => (string) Str::ulid()]);
                    $filled++;
                }
            });

        // 3. refuse to continue if anything is still missing or duplicated -
        //    a unique NOT NULL column must not be half-applied.
        $missing = DB::table('societies')->whereNull(self::COLUMN)->count();
        if ($missing > 0) {
            throw new RuntimeException("Backfill incomplete: {$missing} societies still have no " . self::COLUMN);
        }

        $total    = DB::table('societies')->count();
        $distinct = DB::table('societies')->distinct()->count(self::COLUMN);
        if ($total !== $distinct) {
            throw new RuntimeException("Backfill produced duplicates: {$total} rows, {$distinct} distinct values");
        }

        if ($filled > 0) {
            echo "  backfilled {$filled} societies with a public_id\n";
        }

        // 4. unique + NOT NULL
        if (DB::getDriverName() === 'sqlite') {
            $this->rebuildSqlite(withPublicId: true);

            return;
        }

        $this->addUniqueIndexIfMissing();

        DB::statement('ALTER TABLE societies MODIFY public_id CHAR(' . self::LENGTH . ') NOT NULL');
    }

    public function down(): void
    {
        if (!Schema::hasTable('societies') || !Schema::hasColumn('societies', self::COLUMN)) {
            return;
        }

        if (DB::getDriverName() === 'sqlite') {
            $this->rebuildSqlite(withPublicId: false);

            return;
        }

        Schema::table('societies', function (Blueprint $table) {
            $table->dropUnique('societies_public_id_unique');
            $table->dropColumn(self::COLUMN);
        });
    }

    private function addUniqueIndexIfMissing(): void
    {
        try {
            Schema::table('societies', function (Blueprint $table) {
                $table->unique(self::COLUMN, 'societies_public_id_unique');
            });
        } catch (\Throwable $e) {
            // already present
        }
    }

    /**
     * SQLite cannot alter a column to NOT NULL, so the table is recreated
     * with the desired shape and the rows copied across. Columns are listed
     * explicitly so ordering cannot shift a value into the wrong column.
     */
    private function rebuildSqlite(bool $withPublicId): void
    {
        Schema::dropIfExists('societies_rebuild');

        Schema::create('societies_rebuild', function (Blueprint $table) use ($withPublicId) {
            $table->id();
            if ($withPublicId) {
                $table->string(self::COLUMN, self::LENGTH);
            }
            $table->string('name');
            $table->string('slug');
            $table->string('subdomain')->nullable();
            $table->text('address');
            $table->string('city');
            $table->string('state');
            $table->string('country');
            $table->string('pincode');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->text('description')->nullable();
            $table->string('logo')->nullable();
            $table->text('settings')->nullable();
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');
            $table->dateTime('trial_ends_at')->nullable();
            $table->dateTime('subscription_ends_at')->nullable();
            $table->timestamps();
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->unsignedBigInteger('subscription_plan_id')->nullable();
            $table->string('property_type', 20)->default('both');

            // Indexes are created after the swap - see the class docblock.
        });

        $shared = 'id, name, slug, subdomain, address, city, state, country, pincode, '
            . 'phone, email, website, description, logo, settings, status, trial_ends_at, '
            . 'subscription_ends_at, created_at, updated_at, admin_id, subscription_plan_id, '
            . 'property_type';

        $columns = $withPublicId ? self::COLUMN . ', ' . $shared : $shared;

        // 40 tables have a foreign key onto societies; the swap must not fire them.
        Schema::disableForeignKeyConstraints();

        try {
            DB::statement("INSERT INTO societies_rebuild ({$columns}) SELECT {$columns} FROM societies");
            Schema::drop('societies');
            Schema::rename('societies_rebuild', 'societies');
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        // The old table is gone, so its index names are free again.
        Schema::table('societies', function (Blueprint $table) use ($withPublicId) {
            foreach (self::EXISTING_INDEXES as [$kind, $columns, $name]) {
                $kind === 'unique' ? $table->unique($columns, $name) : $table->index($columns, $name);
            }

            if ($withPublicId) {
                $table->unique(self::COLUMN, 'societies_public_id_unique');
            }
        });
    }
};
