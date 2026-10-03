<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Two different things were being forced through one "property-specific"
 * shape: a resident's own maid/cook/driver (genuinely tied to one flat) and
 * a society-wide employee - a common-area cleaner, the society's own
 * electrician - who has no single property and should never be asked for
 * one. `access_scope` makes that explicit instead of guessing from a null
 * flat_id; `flat_id` becomes nullable only for society-scoped passes
 * (enforced in AccessPassController, not just here).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('access_passes', function (Blueprint $table) {
            $table->enum('access_scope', ['property', 'society'])->default('property')->after('pass_type');
        });

        if (DB::getDriverName() === 'sqlite') {
            $this->makeFlatIdNullableOnSqlite();

            return;
        }

        DB::statement('ALTER TABLE access_passes MODIFY flat_id BIGINT UNSIGNED NULL');
    }

    public function down(): void
    {
        Schema::table('access_passes', function (Blueprint $table) {
            $table->dropColumn('access_scope');
        });
    }

    private function makeFlatIdNullableOnSqlite(): void
    {
        Schema::create('access_passes_rebuild', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained()->onDelete('cascade');
            $table->foreignId('flat_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('person_name');
            $table->string('person_phone')->nullable();
            $table->string('person_photo')->nullable();
            $table->string('category')->nullable();
            $table->enum('pass_type', ['recurring_staff', 'temporary_service']);
            $table->enum('access_scope', ['property', 'society'])->default('property');
            $table->string('token', 64)->unique();
            $table->json('allowed_days')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->date('valid_from');
            $table->date('valid_until')->nullable();
            $table->json('blocked_dates')->nullable();
            $table->enum('status', ['active', 'revoked'])->default('active');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index(['society_id', 'status']);
            $table->index(['flat_id', 'status']);
        });

        $columns = 'id, society_id, flat_id, person_name, person_phone, person_photo, category, '
            . 'pass_type, access_scope, token, allowed_days, start_time, end_time, valid_from, '
            . 'valid_until, blocked_dates, status, created_by, revoked_at, last_used_at, created_at, updated_at';

        Schema::disableForeignKeyConstraints();

        try {
            DB::statement("INSERT INTO access_passes_rebuild ({$columns}) SELECT {$columns} FROM access_passes");
            Schema::drop('access_passes');
            Schema::rename('access_passes_rebuild', 'access_passes');
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }
};
