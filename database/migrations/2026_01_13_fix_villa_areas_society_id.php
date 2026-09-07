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
        // Check if villa_areas table exists
        if (Schema::hasTable('villa_areas')) {
            // Find villa areas with NULL society_id and assign them to a default society
            // This is a data cleanup migration
            
            // First, let's see if there are any villa areas without society_id
            $nullSocietyAreas = DB::table('villa_areas')->whereNull('society_id')->get();
            
            if ($nullSocietyAreas->count() > 0) {
                // Get the first society (usually the default one)
                $defaultSociety = DB::table('societies')->first();
                
                if ($defaultSociety) {
                    // Update all NULL society_id to the default society
                    DB::table('villa_areas')
                        ->whereNull('society_id')
                        ->update(['society_id' => $defaultSociety->id]);
                }
            }
            
            // Verify all villa areas have a valid society_id
            // Remove any villa areas that still have NULL society_id (shouldn't happen after above)
            DB::table('villa_areas')->whereNull('society_id')->delete();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This migration only cleans up data, no need to reverse
    }
};
