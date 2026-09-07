<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;

class ValidateEnvironment extends Command
{
    protected $signature = 'env:validate';
    protected $description = 'Validate environment configuration for SocietyFlow';

    public function handle()
    {
        $this->info('Validating SocietyFlow environment...');
        
        $issues = [];
        
        // Check APP_KEY
        if (!config('app.key')) {
            $issues[] = 'APP_KEY is not set. Run: php artisan key:generate';
        } else {
            $this->info('✓ APP_KEY is set');
        }
        
        // Check database configuration
        $dbConfig = config('database.connections.' . config('database.default'));
        if (!$dbConfig) {
            $issues[] = 'Database configuration is missing';
        } else {
            $this->info('✓ Database configuration found');
        }
        
        // Check session configuration
        $sessionDriver = config('session.driver');
        $this->info("Session driver: {$sessionDriver}");
        
        if ($sessionDriver === 'database') {
            // Check if sessions table exists
            try {
                \DB::table('sessions')->count();
                $this->info('✓ Sessions table exists');
            } catch (\Exception $e) {
                $issues[] = 'Sessions table not found. Run: php artisan session:table && php artisan migrate';
            }
        }
        
        // Check storage permissions
        $storagePath = storage_path();
        if (!is_writable($storagePath)) {
            $issues[] = "Storage directory is not writable: {$storagePath}";
        } else {
            $this->info('✓ Storage directory is writable');
        }
        
        // Check public storage link
        if (!file_exists(public_path('storage'))) {
            $issues[] = 'Storage link not found. Run: php artisan storage:link';
        } else {
            $this->info('✓ Storage link exists');
        }
        
        // Check cache configuration
        $cacheDriver = config('cache.default');
        $this->info("Cache driver: {$cacheDriver}");
        
        // Check queue configuration
        $queueDriver = config('queue.default');
        $this->info("Queue driver: {$queueDriver}");
        
        // Check file permissions
        $directories = [
            storage_path('app'),
            storage_path('framework'),
            storage_path('logs'),
            bootstrap_path('cache'),
        ];
        
        foreach ($directories as $dir) {
            if (!is_writable($dir)) {
                $issues[] = "Directory not writable: {$dir}";
            }
        }
        
        if (empty($issues)) {
            $this->info('🎉 Environment validation passed!');
            return 0;
        } else {
            $this->error('❌ Environment validation failed:');
            foreach ($issues as $issue) {
                $this->error("  - {$issue}");
            }
            return 1;
        }
    }
}