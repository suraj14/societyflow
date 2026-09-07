<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // For SQLite, we can't change enum columns, so we just update the data
        // The enum constraint is already defined in the table creation
        
        // First, update existing data
        DB::table('visitors')
            ->where('approval_status', 'approved')
            ->update(['approval_status' => 'allowed']);
            
        DB::table('visitors')
            ->where('approval_status', 'rejected')
            ->update(['approval_status' => 'denied']);
    }

    public function down(): void
    {
        // Revert data changes
        DB::table('visitors')
            ->where('approval_status', 'allowed')
            ->update(['approval_status' => 'approved']);
            
        DB::table('visitors')
            ->where('approval_status', 'denied')
            ->update(['approval_status' => 'rejected']);
    }
};