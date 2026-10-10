<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Guard Shift & Multi-Gate Management - additive, new tables only.
 *
 * Deliberately NOT wired into the existing visitor/access-pass gate flows
 * (GuardController::scan()/storeVisitor(), AccessPassService) in this
 * migration or the feature built on top of it. Every society in this
 * project has operated as single-gate with no `gate_id` concept anywhere
 * in the schema; forcing every existing, already-tested guard action to
 * require a gate selection is a cross-cutting change to working code,
 * exactly what this project's own standing rule warns against ("do not
 * rewrite working modules unnecessarily"). This migration adds Gates,
 * Shifts, Shift Assignments (attendance) and Handover as a real,
 * standalone module an Admin can use for duty/roster management and a
 * Guard can use to know their current shift - integrating it into the
 * scan/visitor authorization path is future, separately-scoped work.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained()->onDelete('cascade');
            $table->string('name');
            // A free-text type, not an enum/CHECK constraint - CLAUDE.md §5
            // notes SQLite enum-ish columns become schema-rebuild work to
            // widen later, and a society's gate names/types are exactly the
            // kind of thing that should never need one.
            $table->string('type')->default('main');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['society_id', 'is_active']);
        });

        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->time('start_time');
            $table->time('end_time');
            // end_time < start_time means the shift crosses midnight (e.g.
            // 22:00-06:00) - stored as plain start/end, never two dates;
            // every piece of code that reasons about "is this shift active
            // right now" (GuardShiftService) is what actually has to get
            // the midnight crossing right, not the schema.
            $table->unsignedSmallInteger('grace_period_minutes')->default(15);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['society_id', 'is_active']);
        });

        Schema::create('shift_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained()->onDelete('cascade');
            $table->foreignId('shift_id')->constrained()->onDelete('cascade');
            $table->foreignId('gate_id')->constrained()->onDelete('cascade');
            $table->foreignId('guard_user_id')->constrained('users')->onDelete('cascade');
            // The calendar date the shift *starts* on - for an overnight
            // shift (22:00-06:00) this one row covers 22:00 on `date`
            // through 06:00 the next calendar day, never two rows.
            $table->date('date');
            $table->string('status')->default('scheduled');
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('checked_out_at')->nullable();
            $table->foreignId('replaced_by_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('replacement_reason')->nullable();
            $table->foreignId('replaced_by_admin_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('replaced_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            // The same guard cannot be scheduled on the same shift definition
            // twice for the same date - the real "same guard, overlapping
            // time window" check (different shifts that happen to overlap)
            // is additional application-level logic in GuardShiftService,
            // since that needs each shift's actual time window compared,
            // not just a tuple match.
            $table->unique(['guard_user_id', 'shift_id', 'date'], 'shift_assignments_guard_shift_date_unique');
            $table->index(['society_id', 'date']);
            $table->index(['gate_id', 'date']);
            $table->index(['guard_user_id', 'date']);
        });

        Schema::create('shift_handovers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_assignment_id')->constrained('shift_assignments')->onDelete('cascade');
            $table->foreignId('outgoing_guard_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('incoming_guard_id')->nullable()->constrained('users')->onDelete('set null');
            $table->text('notes')->nullable();
            $table->text('pending_visitors_note')->nullable();
            $table->text('active_passes_note')->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->index(['shift_assignment_id']);
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_handovers');
        Schema::dropIfExists('shift_assignments');
        Schema::dropIfExists('shifts');
        Schema::dropIfExists('gates');
    }
};
