<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Repairs two regressions in the visitors table.
 *
 * 1. visitor_type was never widened. VisitorApiController::store validates
 *    against 11 types (guest, delivery, cab, service, maid, driver, cook,
 *    electrician, plumber, emergency, other) but the column constraint only
 *    ever allowed 5, so registering a maid/driver/cook/electrician/plumber/
 *    emergency visitor from the mobile app died on a constraint violation.
 *
 * 2. 2026_01_10_105000_emergency_fix_visitor_constraint rebuilt the table
 *    with raw SQL and dropped every foreign key plus the composite indexes
 *    on (host_user_id, approval_status) and (expected_entry_time,
 *    entry_status) — which are exactly the indexes the visitor list and
 *    dashboard endpoints filter on. Both are restored here.
 *
 * Rows are preserved. Any visitor_type value outside the allowed set is
 * normalised to 'other' before the rebuild so the new constraint can be
 * applied without data loss.
 */
return new class extends Migration
{
    private const TYPES = [
        'guest', 'delivery', 'cab', 'service', 'maid', 'driver',
        'cook', 'electrician', 'plumber', 'emergency', 'other',
    ];

    public function up(): void
    {
        if (!Schema::hasTable('visitors')) {
            return;
        }

        // Normalise anything the new constraint would reject.
        DB::table('visitors')->whereNotIn('visitor_type', self::TYPES)->update(['visitor_type' => 'other']);

        if (DB::getDriverName() === 'sqlite') {
            $this->rebuildSqlite();

            return;
        }

        $list = "'" . implode("','", self::TYPES) . "'";
        DB::statement("ALTER TABLE visitors MODIFY visitor_type ENUM({$list}) NOT NULL DEFAULT 'guest'");

        $this->restoreMissingIndexes();
    }

    public function down(): void
    {
        // Deliberately not reversible: rolling back would re-narrow
        // visitor_type and force lossy remapping of live rows (a recorded
        // "plumber" visit cannot be restored once collapsed to "other").
        // The forward state is strictly more permissive and carries the
        // foreign keys the table should always have had.
    }

    /**
     * MySQL keeps its indexes across a MODIFY, but the composite indexes were
     * lost long before this migration on databases that went through the
     * SQLite emergency rebuild and were later dumped to MySQL. Add whichever
     * are absent, ignoring "already exists".
     */
    private function restoreMissingIndexes(): void
    {
        $wanted = [
            'visitors_host_user_id_approval_status_index' => ['host_user_id', 'approval_status'],
            'visitors_expected_entry_time_entry_status_index' => ['expected_entry_time', 'entry_status'],
            'visitors_flat_id_expected_entry_time_index' => ['flat_id', 'expected_entry_time'],
            'visitors_society_id_entry_status_index' => ['society_id', 'entry_status'],
        ];

        foreach ($wanted as $name => $columns) {
            try {
                Schema::table('visitors', function (Blueprint $table) use ($name, $columns) {
                    $table->index($columns, $name);
                });
            } catch (\Throwable $e) {
                // Index already present — nothing to do.
            }
        }
    }

    private function rebuildSqlite(): void
    {
        Schema::dropIfExists('visitors_rebuild');

        Schema::create('visitors_rebuild', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('society_id');
            $table->unsignedBigInteger('flat_id');
            $table->unsignedBigInteger('host_user_id')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->string('visitor_name');
            $table->string('visitor_phone');
            $table->string('visitor_id_proof')->nullable();
            $table->string('visitor_id_number')->nullable();
            $table->enum('visitor_type', self::TYPES)->default('guest');
            $table->text('purpose')->nullable();
            $table->integer('expected_count')->default(1);
            $table->string('vehicle_number')->nullable();
            $table->dateTime('expected_entry_time');
            $table->dateTime('expected_exit_time')->nullable();
            $table->dateTime('actual_entry_time')->nullable();
            $table->dateTime('actual_exit_time')->nullable();
            $table->enum('approval_status', ['pending', 'allowed', 'denied'])->default('pending');
            $table->enum('entry_status', ['pending', 'entered', 'exited'])->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->text('security_notes')->nullable();
            $table->string('photo')->nullable();
            $table->timestamps();

            // Foreign keys the emergency rebuild dropped.
            $table->foreign('society_id')->references('id')->on('societies')->onDelete('cascade');
            $table->foreign('flat_id')->references('id')->on('flats')->onDelete('cascade');
            $table->foreign('host_user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');

            // Indexes the emergency rebuild dropped.
            $table->index(['society_id', 'entry_status']);
            $table->index(['flat_id', 'expected_entry_time']);
            $table->index(['host_user_id', 'approval_status']);
            $table->index(['expected_entry_time', 'entry_status']);
            $table->index(['visitor_phone', 'expected_entry_time']);
            $table->index('approval_status');
        });

        $columns = 'id, society_id, flat_id, host_user_id, approved_by, visitor_name, '
            . 'visitor_phone, visitor_id_proof, visitor_id_number, visitor_type, purpose, '
            . 'expected_count, vehicle_number, expected_entry_time, expected_exit_time, '
            . 'actual_entry_time, actual_exit_time, approval_status, entry_status, '
            . 'rejection_reason, security_notes, photo, created_at, updated_at';

        // visitor_logs has a foreign key onto visitors.
        Schema::disableForeignKeyConstraints();

        try {
            DB::statement("INSERT INTO visitors_rebuild ({$columns}) SELECT {$columns} FROM visitors");
            Schema::drop('visitors');
            Schema::rename('visitors_rebuild', 'visitors');
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }
};
