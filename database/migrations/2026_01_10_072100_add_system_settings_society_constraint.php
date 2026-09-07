<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check if the constraint already exists (PRAGMA is SQLite-only;
        // running it on MySQL broke `php artisan migrate` on fresh installs)
        $hasConstraint = false;

        if (DB::getDriverName() === 'sqlite') {
            $indexes = DB::select("PRAGMA index_list(system_settings)");
            foreach ($indexes as $index) {
                if (str_contains($index->name, 'society_group_key_unique')) {
                    $hasConstraint = true;
                    break;
                }
            }
        } else {
            $indexes = DB::select("SHOW INDEX FROM system_settings");
            foreach ($indexes as $index) {
                if (str_contains($index->Key_name ?? '', 'society_group_key_unique')) {
                    $hasConstraint = true;
                    break;
                }
            }
        }

        if (!$hasConstraint) {
            Schema::table('system_settings', function (Blueprint $table) {
                // Add new unique constraint that includes society_id
                $table->unique(['society_id', 'group', 'key'], 'system_settings_society_group_key_unique');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->dropUnique('system_settings_society_group_key_unique');
        });
    }
};