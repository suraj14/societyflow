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
 * Makes the column nullable. The foreign key becomes ON DELETE SET NULL:
 * deleting a category must not cascade-delete complaint history.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            // SQLite can't alter constraints in place; application-level
            // validation covers dev SQLite databases.
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
};
