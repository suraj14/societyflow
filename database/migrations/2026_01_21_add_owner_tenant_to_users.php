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
        Schema::table('users', function (Blueprint $table) {
            // Link user to owner (for owner role)
            $table->unsignedBigInteger('owner_id')->nullable()->after('society_id');
            $table->foreign('owner_id')->references('id')->on('owners')->onDelete('set null');
            
            // Link user to tenant (for tenant role)
            $table->unsignedBigInteger('tenant_id')->nullable()->after('owner_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('set null');
            
            // Add indexes for performance
            $table->index('owner_id');
            $table->index('tenant_id');
        });
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
