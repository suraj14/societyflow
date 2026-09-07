<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class FixMaintenanceBillsSchema extends Command
{
    protected $signature = 'fix:maintenance-bills-schema';
    protected $description = 'Fix maintenance_bills table schema by adding missing bill_type and paid_date columns';

    public function handle()
    {
        $this->info('=== FIXING MAINTENANCE_BILLS TABLE SCHEMA ===');
        $this->newLine();
        
        // Check current state
        $this->info('1. Checking current table structure...');
        
        if (!Schema::hasTable('maintenance_bills')) {
            $this->error('ERROR: maintenance_bills table does not exist!');
            return 1;
        }
        
        $hasBillType = Schema::hasColumn('maintenance_bills', 'bill_type');
        $hasPaidDate = Schema::hasColumn('maintenance_bills', 'paid_date');
        
        $this->line("   - bill_type column: " . ($hasBillType ? "EXISTS" : "MISSING"));
        $this->line("   - paid_date column: " . ($hasPaidDate ? "EXISTS" : "MISSING"));
        $this->newLine();
        
        if ($hasBillType && $hasPaidDate) {
            $this->info('✓ All columns already exist! No changes needed.');
            return 0;
        }
        
        // Add missing columns
        $this->info('2. Adding missing columns...');
        
        DB::beginTransaction();
        
        try {
            if (!$hasBillType) {
                $this->line('   - Adding bill_type column...');
                DB::statement("ALTER TABLE maintenance_bills ADD COLUMN bill_type VARCHAR(255) DEFAULT 'maintenance'");
                $this->info('   ✓ bill_type column added');
            }
            
            if (!$hasPaidDate) {
                $this->line('   - Adding paid_date column...');
                DB::statement("ALTER TABLE maintenance_bills ADD COLUMN paid_date DATE NULL");
                $this->info('   ✓ paid_date column added');
            }
            
            // Add index for performance
            $this->line('   - Adding index for better performance...');
            try {
                DB::statement("CREATE INDEX idx_maintenance_bills_society_bill_type ON maintenance_bills(society_id, bill_type)");
                $this->info('   ✓ Index added');
            } catch (\Exception $e) {
                $this->line('   - Index may already exist, skipping...');
            }
            
            // Update existing records
            $this->line('   - Updating existing records...');
            $updated = DB::table('maintenance_bills')
                ->whereNull('bill_type')
                ->update(['bill_type' => 'maintenance']);
            $this->info("   ✓ Updated $updated existing records");
            
            DB::commit();
            
            $this->newLine();
            $this->info('3. Verifying changes...');
            
            $hasBillTypeNow = Schema::hasColumn('maintenance_bills', 'bill_type');
            $hasPaidDateNow = Schema::hasColumn('maintenance_bills', 'paid_date');
            
            $this->line("   - bill_type column: " . ($hasBillTypeNow ? "EXISTS ✓" : "MISSING ✗"));
            $this->line("   - paid_date column: " . ($hasPaidDateNow ? "EXISTS ✓" : "MISSING ✗"));
            
            if ($hasBillTypeNow && $hasPaidDateNow) {
                $this->newLine();
                $this->info('✅ SUCCESS: All columns have been added successfully!');
                
                // Record the migration as run
                $this->newLine();
                $this->info('4. Recording migration as completed...');
                $migrationExists = DB::table('migrations')
                    ->where('migration', '2026_02_02_120000_add_bill_type_and_paid_date_to_maintenance_bills')
                    ->exists();
                    
                if (!$migrationExists) {
                    DB::table('migrations')->insert([
                        'migration' => '2026_02_02_120000_add_bill_type_and_paid_date_to_maintenance_bills',
                        'batch' => DB::table('migrations')->max('batch') + 1
                    ]);
                    $this->info('   ✓ Migration recorded in migrations table');
                } else {
                    $this->line('   - Migration already recorded');
                }
                
                $this->newLine();
                $this->info('🎉 RENT FORM DATABASE SCHEMA FIX COMPLETE!');
                $this->info('The rent form should now work properly.');
                return 0;
            } else {
                $this->newLine();
                $this->error('❌ FAILED: Some columns are still missing!');
                return 1;
            }
            
        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }
    }
}