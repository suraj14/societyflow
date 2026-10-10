<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `service_requests` could be created (ServiceApiController::requestService)
 * but never listed, assigned, progressed or completed - no screen, web or
 * mobile, ever read the row back. This adds the columns a real lifecycle
 * needs: who it is assigned to and when, when work started, and how it
 * ended (completed or cancelled, with a reason).
 *
 * Purely additive. `status` is already a plain string column (see the
 * create migration) with no CHECK constraint, so the new values
 * ('in_progress' already existed in the comment, this adds nothing there)
 * need no widening.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('service_requests', 'assigned_provider_id')) {
                $table->foreignId('assigned_provider_id')->nullable()
                    ->after('service_id')
                    ->constrained('service_providers')->nullOnDelete();
            }
            if (!Schema::hasColumn('service_requests', 'assigned_by')) {
                $table->foreignId('assigned_by')->nullable()
                    ->after('assigned_provider_id')
                    ->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('service_requests', 'assigned_at')) {
                $table->timestamp('assigned_at')->nullable()->after('status');
            }
            if (!Schema::hasColumn('service_requests', 'started_at')) {
                $table->timestamp('started_at')->nullable()->after('assigned_at');
            }
            if (!Schema::hasColumn('service_requests', 'completed_at')) {
                $table->timestamp('completed_at')->nullable()->after('started_at');
            }
            if (!Schema::hasColumn('service_requests', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('completed_at');
            }
            if (!Schema::hasColumn('service_requests', 'cancellation_reason')) {
                $table->string('cancellation_reason', 500)->nullable()->after('cancelled_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('service_requests', function (Blueprint $table) {
            foreach (['assigned_provider_id', 'assigned_by', 'assigned_at', 'started_at', 'completed_at', 'cancelled_at', 'cancellation_reason'] as $col) {
                if (Schema::hasColumn('service_requests', $col)) {
                    if (in_array($col, ['assigned_provider_id', 'assigned_by'], true)) {
                        $table->dropConstrainedForeignId($col);
                    } else {
                        $table->dropColumn($col);
                    }
                }
            }
        });
    }
};
