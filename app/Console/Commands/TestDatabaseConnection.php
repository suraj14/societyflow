<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Exception;

class TestDatabaseConnection extends Command
{
    protected $signature = 'db:test';
    protected $description = 'Test database connection and basic operations';

    public function handle()
    {
        $this->info('Testing database connection...');
        
        try {
            // Test basic connection
            DB::connection()->getPdo();
            $this->info('✓ Database connection successful');
            
            // Test session table
            $sessionCount = DB::table('sessions')->count();
            $this->info("✓ Sessions table accessible (count: {$sessionCount})");
            
            // Test users table
            $userCount = DB::table('users')->count();
            $this->info("✓ Users table accessible (count: {$userCount})");
            
            // Test transaction
            DB::beginTransaction();
            $this->info('✓ Transaction started');
            DB::rollBack();
            $this->info('✓ Transaction rolled back');
            
            // Test insert/update/delete
            $testId = DB::table('sessions')->insertGetId([
                'id' => 'test_session_' . time(),
                'user_id' => null,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Test Agent',
                'payload' => 'test_payload',
                'last_activity' => time()
            ]);
            $this->info('✓ Insert operation successful');
            
            DB::table('sessions')->where('id', 'test_session_' . time())->delete();
            $this->info('✓ Delete operation successful');
            
            $this->info('🎉 All database tests passed!');
            
        } catch (Exception $e) {
            $this->error('❌ Database test failed: ' . $e->getMessage());
            return 1;
        }
        
        return 0;
    }
}