<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * visitor_logs.action was created as
 * enum('created','approved','rejected','entry','exit','updated'), but
 * Visitor::allow()/deny() - called by VisitorApiController, the mobile
 * app's approve/deny endpoints - log 'allowed'/'denied', neither of which
 * the column ever accepted. SQLite stores enum as TEXT with no real
 * constraint, so this never surfaced there; on MySQL/MariaDB, approving or
 * denying a visitor from the mobile app would fail on a data-truncation
 * error on the log insert. Found while adding AccessPass's own use of
 * addLog() (CLAUDE.md §8: SQLite's permissiveness hides exactly this class
 * of bug from the default test run).
 *
 * Same rebuild-vs-MODIFY pattern as 2026_09_07_000003_repair_visitors_table.
 */
return new class extends Migration
{
    private const ACTIONS = ['created', 'allowed', 'denied', 'entry', 'exit', 'updated'];

    public function up(): void
    {
        if (!Schema::hasTable('visitor_logs')) {
            return;
        }

        // Existing rows using the old spelling still mean the same thing.
        DB::table('visitor_logs')->where('action', 'approved')->update(['action' => 'allowed']);
        DB::table('visitor_logs')->where('action', 'rejected')->update(['action' => 'denied']);

        if (DB::getDriverName() === 'sqlite') {
            $this->rebuildSqlite();

            return;
        }

        $list = "'" . implode("','", self::ACTIONS) . "'";
        DB::statement("ALTER TABLE visitor_logs MODIFY action ENUM({$list}) NOT NULL DEFAULT 'created'");
    }

    public function down(): void
    {
        // Not reversible: rows already using 'allowed'/'denied' would need
        // lossy remapping back to 'approved'/'rejected', and the forward
        // state is strictly the correct one (it matches what the code
        // actually writes).
    }

    private function rebuildSqlite(): void
    {
        Schema::create('visitor_logs_rebuild', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visitor_id')->constrained('visitors')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->enum('action', self::ACTIONS)->default('created');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['visitor_id', 'action']);
        });

        $columns = 'id, visitor_id, user_id, action, notes, created_at, updated_at';

        Schema::disableForeignKeyConstraints();

        try {
            DB::statement("INSERT INTO visitor_logs_rebuild ({$columns}) SELECT {$columns} FROM visitor_logs");
            Schema::drop('visitor_logs');
            Schema::rename('visitor_logs_rebuild', 'visitor_logs');
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }
};
