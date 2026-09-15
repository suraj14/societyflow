<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gives users.owner_id the foreign key it was always meant to have.
 *
 * 2026_01_21_add_owner_tenant_to_users declared exactly this constraint:
 *
 *     $table->foreign('owner_id')->references('id')->on('owners')
 *           ->onDelete('set null');
 *
 * On MySQL that works. On SQLite it does nothing at all: Laravel 10's
 * SQLiteGrammar::compileForeign() is an empty method ("Handled on table
 * creation..."), because SQLite has no ALTER TABLE ADD CONSTRAINT. The
 * ADD COLUMN and the ->index() calls in that migration did apply, which is
 * why the live SQLite schema carries users_owner_id_index but no matching
 * foreign key - the failure left no trace to notice.
 *
 * So this is a SQLite-only gap, and the fix has to differ by engine:
 * MySQL only needs the constraint added if the original run did not already
 * create it, while SQLite needs the table rebuilt around it.
 *
 * ON DELETE SET NULL is confirmed as the right behaviour, not assumed:
 *   - the column is nullable, and always has been;
 *   - every consumer guards with `if ($user->owner_id)`, so null is an
 *     expected state rather than an edge case;
 *   - CASCADE would be wrong. Ceasing to be an owner must not delete the
 *     login account - the same person may remain a tenant or a resident.
 *
 * tenant_id has the identical gap from the same migration. It is left alone
 * here deliberately, because it was not part of this task; note that adding
 * it later costs another rebuild of the users table.
 */
return new class extends Migration
{
    private const FK_NAME = 'users_owner_id_foreign';

    public function up(): void
    {
        if (!Schema::hasTable('users') || !Schema::hasColumn('users', 'owner_id')) {
            return;
        }

        // A dangling owner_id would make the constraint impossible to apply.
        // Only rows pointing at an owner that does not exist are touched, and
        // only the link is cleared - this is precisely what SET NULL would
        // have done had the constraint been present when the owner went away.
        $orphans = $this->clearDanglingOwnerLinks();

        if ($orphans > 0) {
            echo "  cleared {$orphans} users.owner_id value(s) pointing at a missing owner\n";
        }

        if (DB::getDriverName() === 'sqlite') {
            $this->rebuildSqlite(withOwnerForeignKey: true);

            return;
        }

        if ($this->hasForeignKey(self::FK_NAME)) {
            return; // the original migration already created it
        }

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('owner_id', self::FK_NAME)
                ->references('id')->on('owners')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('users') || !Schema::hasColumn('users', 'owner_id')) {
            return;
        }

        if (DB::getDriverName() === 'sqlite') {
            $this->rebuildSqlite(withOwnerForeignKey: false);

            return;
        }

        if (!$this->hasForeignKey(self::FK_NAME)) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(self::FK_NAME);
        });
    }

    /**
     * Null any users.owner_id that does not resolve to an owner.
     *
     * @return int rows changed
     */
    private function clearDanglingOwnerLinks(): int
    {
        if (!Schema::hasTable('owners')) {
            return 0;
        }

        return DB::table('users')
            ->whereNotNull('owner_id')
            ->whereNotIn('owner_id', function ($query) {
                $query->select('id')->from('owners');
            })
            ->update(['owner_id' => null]);
    }

    /** MySQL-only; information_schema does not exist on SQLite. */
    private function hasForeignKey(string $name): bool
    {
        if (DB::getDriverName() !== 'mysql') {
            return false;
        }

        return !empty(DB::select(
            "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'
               AND CONSTRAINT_NAME = ?",
            [$name]
        ));
    }

    /**
     * SQLite cannot add or drop a constraint in place, so the table is
     * recreated with the desired shape and the rows copied across.
     *
     * Every column, index and the existing society_id constraint are
     * reproduced exactly; the only difference between the two directions is
     * whether the owner_id foreign key is emitted. Columns are listed
     * explicitly in the copy so ordering cannot silently shift a value into
     * the wrong column.
     */
    private function rebuildSqlite(bool $withOwnerForeignKey): void
    {
        Schema::dropIfExists('users_rebuild');

        Schema::create('users_rebuild', function (Blueprint $table) use ($withOwnerForeignKey) {
            $table->id();
            $table->unsignedBigInteger('society_id')->nullable();
            $table->string('name');
            $table->string('email');
            $table->dateTime('email_verified_at')->nullable();
            $table->string('password');
            $table->string('phone')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->text('address')->nullable();
            $table->string('avatar')->nullable();
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');
            $table->dateTime('last_login_at')->nullable();
            $table->string('remember_token')->nullable();
            $table->timestamps();
            $table->unsignedBigInteger('owner_id')->nullable();
            $table->unsignedBigInteger('tenant_id')->nullable();

            $table->foreign('society_id')->references('id')->on('societies')->onDelete('cascade');

            if ($withOwnerForeignKey) {
                $table->foreign('owner_id', self::FK_NAME)
                    ->references('id')->on('owners')
                    ->nullOnDelete();
            }

            // tenant_id keeps its index but no constraint - see the class
            // docblock; adding one is a separate decision.

            // Indexes are NOT declared here. SQLite index names are unique
            // across the whole database rather than per table, so creating
            // users_email_unique on users_rebuild collides with the one still
            // on users. They are recreated under their proper names below,
            // once dropping the old table has released them.
        });

        $columns = 'id, society_id, name, email, email_verified_at, password, phone, '
            . 'date_of_birth, gender, address, avatar, status, last_login_at, '
            . 'remember_token, created_at, updated_at, owner_id, tenant_id';

        // Many tables carry a foreign key onto users; the swap has to happen
        // with enforcement off, or dropping the old table would fire them.
        Schema::disableForeignKeyConstraints();

        try {
            DB::statement("INSERT INTO users_rebuild ({$columns}) SELECT {$columns} FROM users");
            Schema::drop('users');
            Schema::rename('users_rebuild', 'users');
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        // Now that the old table is gone, its index names are free again.
        Schema::table('users', function (Blueprint $table) {
            $table->unique('email', 'users_email_unique');
            $table->index(['society_id', 'status'], 'users_society_id_status_index');
            $table->index(['email', 'status'], 'users_email_status_index');
            $table->index('owner_id', 'users_owner_id_index');
            $table->index('tenant_id', 'users_tenant_id_index');
        });
    }
};
