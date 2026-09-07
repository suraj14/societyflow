<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The mobile API accepts complaints with no category (category is a
 * nullable free-text hint matched against ComplaintCategory), but
 * complaints.complaint_category_id was NOT NULL — so every mobile
 * complaint without a matched category failed with a 500.
 *
 * Makes the column nullable on BOTH drivers. The foreign key becomes
 * ON DELETE SET NULL: deleting a category must not cascade-delete
 * complaint history.
 *
 * SQLite cannot ALTER a column in place, so it gets an explicit table
 * rebuild (the pattern already used by fix_flats_status_constraint_final).
 * The rebuild is written out column by column so the priority/status CHECK
 * constraints survive — letting doctrine/dbal ->change() do it would drop
 * them silently and widen both columns to unconstrained strings.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('complaints', 'complaint_category_id') === false) {
            return;
        }

        if (DB::getDriverName() === 'sqlite') {
            $this->rebuildSqlite(nullable: true);

            return;
        }

        Schema::table('complaints', function (Blueprint $table) {
            $table->dropForeign(['complaint_category_id']);
        });

        DB::statement('ALTER TABLE complaints MODIFY complaint_category_id BIGINT UNSIGNED NULL');

        Schema::table('complaints', function (Blueprint $table) {
            $table->foreign('complaint_category_id')
                ->references('id')->on('complaint_categories')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            // Rows added while the column was nullable would violate the
            // restored NOT NULL constraint, so drop them into the
            // "uncategorised" bucket is not possible — refuse instead of
            // destroying data. Rolling this back needs a manual decision.
            if (DB::table('complaints')->whereNull('complaint_category_id')->exists()) {
                throw new \RuntimeException(
                    'Cannot roll back: complaints with a NULL complaint_category_id exist. '
                    . 'Assign them a category first, then retry.'
                );
            }

            $this->rebuildSqlite(nullable: false);

            return;
        }

        Schema::table('complaints', function (Blueprint $table) {
            $table->dropForeign(['complaint_category_id']);
        });

        DB::statement('ALTER TABLE complaints MODIFY complaint_category_id BIGINT UNSIGNED NOT NULL');

        Schema::table('complaints', function (Blueprint $table) {
            $table->foreign('complaint_category_id')
                ->references('id')->on('complaint_categories')
                ->onDelete('cascade');
        });
    }

    /**
     * Rebuild the complaints table with complaint_category_id nullable
     * (or not), preserving every column, CHECK constraint, index and row.
     */
    private function rebuildSqlite(bool $nullable): void
    {
        Schema::dropIfExists('complaints_rebuild');

        Schema::create('complaints_rebuild', function (Blueprint $table) use ($nullable) {
            $table->id();
            $table->unsignedBigInteger('society_id');

            $category = $table->unsignedBigInteger('complaint_category_id');
            if ($nullable) {
                $category->nullable();
            }

            $table->unsignedBigInteger('flat_id')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->string('complaint_number');
            $table->string('title');
            $table->text('description');
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            $table->enum('status', ['open', 'in_progress', 'resolved', 'closed', 'cancelled'])->default('open');
            $table->text('attachments')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->dateTime('resolved_at')->nullable();
            $table->timestamps();

            $table->unique('complaint_number');
            $table->index(['society_id', 'status']);
            $table->index(['complaint_category_id', 'status']);
            $table->index(['flat_id', 'status']);
            $table->index(['created_by', 'status']);
            $table->index(['assigned_to', 'status']);
            $table->index(['priority', 'status']);

            $table->foreign('society_id')->references('id')->on('societies')->onDelete('cascade');
            $table->foreign('flat_id')->references('id')->on('flats')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('assigned_to')->references('id')->on('users')->onDelete('set null');

            if ($nullable) {
                $table->foreign('complaint_category_id')
                    ->references('id')->on('complaint_categories')->onDelete('set null');
            } else {
                $table->foreign('complaint_category_id')
                    ->references('id')->on('complaint_categories')->onDelete('cascade');
            }
        });

        $columns = 'id, society_id, complaint_category_id, flat_id, created_by, assigned_to, '
            . 'complaint_number, title, description, priority, status, attachments, '
            . 'resolution_notes, resolved_at, created_at, updated_at';

        // complaint_updates has a foreign key onto complaints; the swap has to
        // happen with enforcement off or SQLite rejects dropping the parent.
        Schema::disableForeignKeyConstraints();

        try {
            DB::statement("INSERT INTO complaints_rebuild ({$columns}) SELECT {$columns} FROM complaints");
            Schema::drop('complaints');
            Schema::rename('complaints_rebuild', 'complaints');
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }
};
