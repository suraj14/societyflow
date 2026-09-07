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
        // Delete duplicate services, keeping only the first occurrence of each service name
        DB::statement('
            DELETE FROM services 
            WHERE id NOT IN (
                SELECT MIN(id) 
                FROM (
                    SELECT MIN(id) as id 
                    FROM services 
                    GROUP BY name
                ) as temp
            )
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Cannot reliably reverse this migration
    }
};
