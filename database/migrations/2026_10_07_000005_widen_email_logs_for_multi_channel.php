<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Widens `email_logs` so SMS/WhatsApp sends can be logged in the same
 * delivery-log table as email, instead of a second parallel table:
 * `email_template_id` and `recipient_email` become nullable (an SMS has
 * neither), and `recipient_phone` is added.
 *
 * SQLite cannot relax a NOT NULL constraint with a plain ALTER TABLE, so
 * this follows the project's own established rebuild pattern (see
 * 2026_09_07_000003_repair_visitors_table.php): build the replacement
 * table, copy every existing row by explicit column list, verify the row
 * count matches before committing to the rename, then swap. On MySQL this
 * is a plain MODIFY instead, no rebuild needed.
 *
 * Every existing row, its id, and its timestamps are preserved - this does
 * not touch the 382 rows already in this project's dev database.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('email_logs')) {
            return;
        }

        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('email_logs', function (Blueprint $table) {
                $table->unsignedBigInteger('email_template_id')->nullable()->change();
                $table->string('recipient_email')->nullable()->change();
            });
            if (!Schema::hasColumn('email_logs', 'recipient_phone')) {
                Schema::table('email_logs', function (Blueprint $table) {
                    $table->string('recipient_phone', 20)->nullable()->after('recipient_email');
                });
            }

            return;
        }

        $this->rebuildSqlite();
    }

    public function down(): void
    {
        // Deliberately not reversible: SMS/WhatsApp rows written after this
        // migration have no recipient_email and would violate the old NOT
        // NULL constraint on rollback. The forward state is strictly more
        // permissive and loses no data.
    }

    private function rebuildSqlite(): void
    {
        $before = DB::table('email_logs')->count();

        Schema::dropIfExists('email_logs_rebuild');

        Schema::create('email_logs_rebuild', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('email_template_id')->nullable();
            $table->unsignedBigInteger('society_id');
            $table->string('channel', 20)->default('email');
            $table->string('recipient_email')->nullable();
            $table->string('recipient_phone', 20)->nullable();
            $table->string('recipient_name')->nullable();
            $table->string('trigger_action');
            $table->string('notification_key')->nullable();
            $table->text('variables')->nullable();
            $table->string('status')->default('pending');
            $table->string('provider_reference', 191)->nullable();
            $table->text('error_message')->nullable();
            $table->integer('retry_count')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->foreign('society_id')->references('id')->on('societies')->onDelete('cascade');
            $table->foreign('email_template_id')->references('id')->on('email_templates')->onDelete('cascade');

            // Unnamed: the original table (still named email_logs at this
            // point, not yet dropped) holds indexes with the canonical
            // names below, and SQLite index names must be unique
            // database-wide at any given moment. Letting Laravel derive
            // names from email_logs_rebuild avoids the collision; the
            // columns, not the names, are what the app relies on.
            $table->index(['notification_key', 'channel']);
            $table->index(['trigger_action', 'created_at']);
            $table->index(['society_id', 'status']);
        });

        $columns = 'id, email_template_id, society_id, channel, recipient_email, '
            . 'recipient_name, trigger_action, notification_key, variables, status, '
            . 'provider_reference, error_message, retry_count, sent_at, created_at, updated_at';

        Schema::disableForeignKeyConstraints();

        try {
            DB::statement("INSERT INTO email_logs_rebuild ({$columns}) SELECT {$columns} FROM email_logs");

            $after = DB::table('email_logs_rebuild')->count();
            if ($after !== $before) {
                throw new \RuntimeException("email_logs rebuild row count mismatch: had {$before}, copied {$after}. Aborting before the swap.");
            }

            Schema::drop('email_logs');
            Schema::rename('email_logs_rebuild', 'email_logs');
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }
};
