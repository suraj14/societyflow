<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gate approval workflow: who asked, who decided and when, who let the visitor in and out, and any override.
 *
 * `approval_status` keeps its three values (pending / allowed / denied) and `entry_status` its three (pending / entered /
 * exited): both are CHECK-constrained on SQLite and every screen reads them. The mobile app's richer states
 * (pending_approval, approved, rejected, checked_in, checked_out) are derived from the pair, see Visitor::workflowStatus().
 *
 * All new columns are nullable, so existing rows are untouched and nothing is deleted. visitor_logs.action gains
 * 'override', 'escalated' and 'notified' (the per-channel notification outcome of an event) - a SQLite table rebuild,
 * the same pattern as 2026_09_18_000003_widen_visitor_logs_action_enum.
 */
return new class extends Migration
{
    private const ACTIONS = ['created', 'allowed', 'denied', 'entry', 'exit', 'updated', 'override', 'escalated', 'notified'];

    public function up(): void
    {
        Schema::table('visitors', function (Blueprint $table) {
            $table->unsignedBigInteger('requested_by')->nullable()->after('host_user_id');      // the guard / staff member who logged the visitor at the gate
            $table->unsignedBigInteger('decided_by')->nullable()->after('approved_by');          // whoever allowed or denied (owner, tenant, admin)
            $table->timestamp('decided_at')->nullable()->after('decided_by');
            $table->string('decision_role', 20)->nullable()->after('decided_at');                // resident | admin | override
            $table->unsignedBigInteger('checked_in_by')->nullable()->after('actual_entry_time');
            $table->unsignedBigInteger('checked_out_by')->nullable()->after('actual_exit_time');
            $table->unsignedBigInteger('override_by')->nullable();
            $table->timestamp('override_at')->nullable();
            $table->text('override_reason')->nullable();
            $table->timestamp('escalated_at')->nullable();                                      // no answer in time: the office was told (never auto-admitted)
            $table->string('client_request_id', 64)->nullable();                                // the gate app's own id for a submission, so a retried POST is one visitor

            $table->index(['society_id', 'approval_status', 'entry_status'], 'visitors_workflow_idx');
            $table->unique(['society_id', 'client_request_id'], 'visitors_client_request_unique');
        });

        if (!Schema::hasTable('visitor_logs')) {
            return;
        }

        if (DB::getDriverName() === 'sqlite') {
            $this->rebuildSqlite();

            return;
        }

        $list = "'" . implode("','", self::ACTIONS) . "'";
        DB::statement("ALTER TABLE visitor_logs MODIFY action ENUM({$list}) NOT NULL DEFAULT 'created'");
    }

    public function down(): void
    {
        // Forward-only: the columns hold audit history that must not be dropped by a rollback.
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

            // Named explicitly: SQLite keeps an index's name when its table is renamed, so the earlier rebuild left one called
            // visitor_logs_rebuild_visitor_id_action_index behind, and the default name for this table's index is the same.
            $table->index(['visitor_id', 'action'], 'visitor_logs_visitor_id_action_index');
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
