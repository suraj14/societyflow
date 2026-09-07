<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class ClearAllCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cache:clear-all';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear ALL caches - application, config, routes, views, and browser cache';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔄 Starting comprehensive cache clear...');

        try {
            // Clear application cache
            $this->info('Clearing application cache...');
            Artisan::call('cache:clear');
            $this->line('✓ Application cache cleared');

            // Clear config cache
            $this->info('Clearing config cache...');
            Artisan::call('config:clear');
            $this->line('✓ Config cache cleared');

            // Clear route cache
            $this->info('Clearing route cache...');
            Artisan::call('route:clear');
            $this->line('✓ Route cache cleared');

            // Clear view cache
            $this->info('Clearing view cache...');
            Artisan::call('view:clear');
            $this->line('✓ View cache cleared');

            // Clear compiled classes
            $this->info('Clearing compiled classes...');
            Artisan::call('optimize:clear');
            $this->line('✓ Compiled classes cleared');

            // Clear session files
            $this->info('Clearing session files...');
            $sessionPath = storage_path('framework/sessions');
            if (is_dir($sessionPath)) {
                array_map('unlink', glob($sessionPath . '/*'));
                $this->line('✓ Session files cleared');
            }

            // Clear cache directory
            $this->info('Clearing cache directory...');
            $cachePath = storage_path('framework/cache');
            if (is_dir($cachePath)) {
                $this->clearDirectory($cachePath);
                $this->line('✓ Cache directory cleared');
            }

            $this->info('');
            $this->info('✅ All caches cleared successfully!');
            $this->info('');
            $this->info('Next steps:');
            $this->line('1. Hard refresh browser: Ctrl+Shift+R (Windows/Linux) or Cmd+Shift+R (Mac)');
            $this->line('2. Clear browser cache if issues persist');
            $this->line('3. Test CRUD operations without page reload');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('❌ Error clearing cache: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Recursively clear directory
     */
    private function clearDirectory($path)
    {
        if (!is_dir($path)) return;

        $files = scandir($path);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;

            $filePath = $path . '/' . $file;
            if (is_dir($filePath)) {
                $this->clearDirectory($filePath);
                @rmdir($filePath);
            } else {
                @unlink($filePath);
            }
        }
    }
}
