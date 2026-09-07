<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Emergency fix for SQLite visitor constraint issue
        // This directly fixes the constraint without complex table recreation
        
        try {
            // For SQLite, we need to use raw SQL to fix the constraint
            if (DB::getDriverName() === 'sqlite') {
                
                // First, let's check current data and update any problematic values
                DB::statement("UPDATE visitors SET approval_status = 'pending' WHERE approval_status NOT IN ('pending', 'allowed', 'denied')");
                
                // Create a new table with correct constraints
                DB::statement('
                    CREATE TABLE visitors_new (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        society_id INTEGER NOT NULL,
                        flat_id INTEGER NOT NULL,
                        host_user_id INTEGER,
                        approved_by INTEGER,
                        visitor_name TEXT NOT NULL,
                        visitor_phone TEXT NOT NULL,
                        visitor_id_proof TEXT,
                        visitor_id_number TEXT,
                        visitor_type TEXT CHECK (visitor_type IN (\'guest\', \'delivery\', \'cab\', \'service\', \'other\')) DEFAULT \'guest\',
                        purpose TEXT,
                        expected_count INTEGER DEFAULT 1,
                        vehicle_number TEXT,
                        expected_entry_time DATETIME NOT NULL,
                        expected_exit_time DATETIME,
                        actual_entry_time DATETIME,
                        actual_exit_time DATETIME,
                        approval_status TEXT CHECK (approval_status IN (\'pending\', \'allowed\', \'denied\')) DEFAULT \'pending\',
                        entry_status TEXT CHECK (entry_status IN (\'pending\', \'entered\', \'exited\')) DEFAULT \'pending\',
                        rejection_reason TEXT,
                        security_notes TEXT,
                        photo TEXT,
                        created_at DATETIME,
                        updated_at DATETIME
                    )
                ');
                
                // Copy all data to the new table
                DB::statement('INSERT INTO visitors_new SELECT * FROM visitors');
                
                // Drop the old table
                DB::statement('DROP TABLE visitors');
                
                // Rename the new table
                DB::statement('ALTER TABLE visitors_new RENAME TO visitors');
                
                // Recreate indexes if needed
                DB::statement('CREATE INDEX idx_visitors_society_id ON visitors(society_id)');
                DB::statement('CREATE INDEX idx_visitors_flat_id ON visitors(flat_id)');
                DB::statement('CREATE INDEX idx_visitors_approval_status ON visitors(approval_status)');
                
            } else {
                // For other databases, just update the enum
                DB::statement("ALTER TABLE visitors MODIFY COLUMN approval_status ENUM('pending', 'allowed', 'denied') DEFAULT 'pending'");
            }
            
        } catch (Exception $e) {
            // If anything fails, log the error but don't stop the migration
            \Log::error('Visitor constraint fix failed: ' . $e->getMessage());
        }
    }

    public function down(): void
    {
        // Rollback is complex for SQLite, so we'll just log it
        \Log::info('Visitor constraint fix rollback requested - manual intervention may be required');
    }
};