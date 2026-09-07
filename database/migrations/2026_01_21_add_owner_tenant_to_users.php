<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Idempotent + ordering-safe: this migration sorts BEFORE
        // 2026_01_21_create_tenants_table, so the tenant_id foreign key used
        // to fail (tenants table absent), leaving the columns half-applied
        // and every re-run dying on "duplicate column". Columns/FK/indexes
        // are now guarded; the tenants FK is added by create_tenants_table.
        if (!Schema::hasColumn('users', 'owner_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unsignedBigInteger('owner_id')->nullable()->after('society_id');
            });
        }
        if (!Schema::hasColumn('users', 'tenant_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unsignedBigInteger('tenant_id')->nullable()->after('owner_id');
            });
        }

        $fks = collect(\Illuminate\Support\Facades\DB::select(
            "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'
               AND REFERENCED_TABLE_NAME IS NOT NULL"
        ))->pluck('CONSTRAINT_NAME');

        Schema::table('users', function (Blueprint $table) use ($fks) {
            if (!$fks->contains('users_owner_id_foreign')) {
                $table->foreign('owner_id')->references('id')->on('owners')->onDelete('set null');
            }
            if (Schema::hasTable('tenants') && !$fks->contains('users_tenant_id_foreign')) {
                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('set null');
            }
        });

        foreach (['owner_id', 'tenant_id'] as $col) {
            try {
                Schema::table('users', function (Blueprint $table) use ($col) {
                    $table->index($col);
                });
            } catch (\Throwable $e) {
                // index already exists — fine
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // SQLite doesn't support dropping foreign keys properly
            // Check if columns exist before dropping
            if (Schema::hasColumn('users', 'owner_id')) {
                $table->dropColumn('owner_id');
            }
            if (Schema::hasColumn('users', 'tenant_id')) {
                $table->dropColumn('tenant_id');
            }
        });
    }
};
