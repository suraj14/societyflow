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
        Schema::table('system_settings', function (Blueprint $table) {
            // Drop the old unique constraint
            $table->dropUnique(['group', 'key']);
            
            // Add new unique constraint that includes society_id
            $table->unique(['society_id', 'group', 'key'], 'system_settings_society_group_key_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            // Drop the new unique constraint
            $table->dropUnique('system_settings_society_group_key_unique');
            
            // Restore the old unique constraint
            $table->unique(['group', 'key']);
        });
    }
};