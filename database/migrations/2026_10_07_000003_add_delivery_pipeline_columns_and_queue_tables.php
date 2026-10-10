<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two additions for the queued/idempotent notification pipeline:
 *
 *  1. `email_logs` gains `channel`, `notification_key` and
 *     `provider_reference`. The table already recorded every send
 *     attempt (recipient, status, error, timestamp) - it was the delivery
 *     log this project needed, just email-only and without a dedupe key.
 *     Widening it, rather than creating a parallel table, is what keeps
 *     this one delivery log rather than two.
 *
 *  2. The standard `jobs`/`failed_jobs` tables, needed the moment
 *     QUEUE_CONNECTION is switched from `sync` to `database` - not done by
 *     this migration (see DispatchEventNotification's docblock for why).
 *     Creating them now means that switch, whenever it is made, needs no
 *     further migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('email_logs', 'channel')) {
                $table->string('channel', 20)->default('email')->after('society_id');
            }
            if (!Schema::hasColumn('email_logs', 'notification_key')) {
                // Not unique at the DB level: SQLite's handling of a
                // partial/nullable unique index differs enough from
                // MySQL's that relying on one here would behave
                // differently per engine (CLAUDE.md §8). Duplicate
                // prevention is enforced in code
                // (DispatchEventNotification checks this column before
                // sending) and at the queue level (ShouldBeUnique).
                $table->string('notification_key', 255)->nullable()->after('trigger_action');
                $table->index(['notification_key', 'channel'], 'email_logs_dedupe_idx');
            }
            if (!Schema::hasColumn('email_logs', 'provider_reference')) {
                $table->string('provider_reference', 191)->nullable()->after('status');
            }
        });

        if (!Schema::hasTable('jobs')) {
            Schema::create('jobs', function (Blueprint $table) {
                $table->id();
                $table->string('queue')->index();
                $table->longText('payload');
                $table->unsignedTinyInteger('attempts');
                $table->unsignedInteger('reserved_at')->nullable();
                $table->unsignedInteger('available_at');
                $table->unsignedInteger('created_at');
            });
        }

        if (!Schema::hasTable('job_batches')) {
            Schema::create('job_batches', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->string('name');
                $table->integer('total_jobs');
                $table->integer('pending_jobs');
                $table->integer('failed_jobs');
                $table->longText('failed_job_ids');
                $table->mediumText('options')->nullable();
                $table->integer('cancelled_at')->nullable();
                $table->integer('created_at');
                $table->integer('finished_at')->nullable();
            });
        }

        if (!Schema::hasTable('failed_jobs')) {
            Schema::create('failed_jobs', function (Blueprint $table) {
                $table->id();
                $table->string('uuid')->unique();
                $table->text('connection');
                $table->text('queue');
                $table->longText('payload');
                $table->longText('exception');
                $table->timestamp('failed_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        Schema::table('email_logs', function (Blueprint $table) {
            if (Schema::hasColumn('email_logs', 'notification_key')) {
                $table->dropIndex('email_logs_dedupe_idx');
                $table->dropColumn('notification_key');
            }
            if (Schema::hasColumn('email_logs', 'channel')) {
                $table->dropColumn('channel');
            }
            if (Schema::hasColumn('email_logs', 'provider_reference')) {
                $table->dropColumn('provider_reference');
            }
        });

        Schema::dropIfExists('failed_jobs');
        Schema::dropIfExists('job_batches');
        Schema::dropIfExists('jobs');
    }
};
