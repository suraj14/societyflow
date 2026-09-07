<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class VerifyInstantCrudSetup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'verify:instant-crud';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verify Instant CRUD setup is complete and working';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔍 Verifying Instant CRUD Setup...');
        $this->info('');

        $checks = [
            'Middleware' => $this->checkMiddleware(),
            'Cache Configuration' => $this->checkCacheConfig(),
            'Session Configuration' => $this->checkSessionConfig(),
            'JavaScript Files' => $this->checkJavaScriptFiles(),
            'Cache Directories' => $this->checkCacheDirectories(),
            'Session Directories' => $this->checkSessionDirectories(),
        ];

        $passed = 0;
        $failed = 0;

        foreach ($checks as $check => $result) {
            if ($result) {
                $this->line("✅ $check");
                $passed++;
            } else {
                $this->line("❌ $check");
                $failed++;
            }
        }

        $this->info('');
        $this->info("Results: $passed passed, $failed failed");
        $this->info('');

        if ($failed === 0) {
            $this->info('✅ All checks passed! Instant CRUD is ready to use.');
            $this->info('');
            $this->info('Next steps:');
            $this->line('1. Run: php artisan cache:clear-all');
            $this->line('2. Hard refresh browser: Ctrl+Shift+R');
            $this->line('3. Add data-instant attribute to your forms');
            $this->line('4. Test CRUD operations');
            return Command::SUCCESS;
        } else {
            $this->error('❌ Some checks failed. Please review the issues above.');
            return Command::FAILURE;
        }
    }

    private function checkMiddleware(): bool
    {
        $kernelPath = app_path('Http/Kernel.php');
        if (!file_exists($kernelPath)) {
            return false;
        }

        $content = file_get_contents($kernelPath);
        return strpos($content, 'PreventAllCaching') !== false;
    }

    private function checkCacheConfig(): bool
    {
        $configPath = config_path('cache.php');
        if (!file_exists($configPath)) {
            return false;
        }

        $cacheDriver = config('cache.default');
        return $cacheDriver === 'file' || $cacheDriver === 'array';
    }

    private function checkSessionConfig(): bool
    {
        $sessionDriver = config('session.driver');
        $sessionLifetime = config('session.lifetime');

        return $sessionDriver === 'file' && $sessionLifetime > 0;
    }

    private function checkJavaScriptFiles(): bool
    {
        $jsFile = public_path('js/instant-crud-handler.js');
        return file_exists($jsFile);
    }

    private function checkCacheDirectories(): bool
    {
        $cachePath = storage_path('framework/cache');
        return is_dir($cachePath) && is_writable($cachePath);
    }

    private function checkSessionDirectories(): bool
    {
        $sessionPath = storage_path('framework/sessions');
        return is_dir($sessionPath) && is_writable($sessionPath);
    }
}
