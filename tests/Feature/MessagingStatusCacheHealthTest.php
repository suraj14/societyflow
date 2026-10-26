<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Support\ExternalMessaging;
use Illuminate\Cache\ArrayStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Production: `php artisan messaging:status` died with
 *   file_put_contents(.../storage/framework/cache/data/1b/f0/...): Failed to open stream: No such file or directory
 * Laravel's file cache creates the hashed sub-folder with a SILENT mkdir, so when another account owns the parent (or it is missing and cannot be
 * made) the only symptom is that message. The command now probes the real cache first and reports the real error - it never swallows it.
 */
class MessagingStatusCacheHealthTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sf-cache-' . bin2hex(random_bytes(4));
        mkdir($this->dir, 0700, true);
    }

    protected function tearDown(): void
    {
        $this->useFileCache(null);
        $this->remove($this->dir);
        parent::tearDown();
    }

    private function useFileCache(?string $path): void
    {
        config(['cache.default' => $path === null ? 'array' : 'file', 'cache.stores.file.path' => $path ?? $this->dir]);
        Cache::forgetDriver('file');
        Cache::forgetDriver('array');
    }

    private function remove(string $p): void
    {
        if (is_file($p)) {
            @unlink($p);

            return;
        }
        foreach (is_dir($p) ? (scandir($p) ?: []) : [] as $e) {
            if ($e !== '.' && $e !== '..') {
                $this->remove($p . DIRECTORY_SEPARATOR . $e);
            }
        }
        @rmdir($p);
    }

    public function test_a_cache_folder_that_does_not_exist_yet_is_created_on_first_write_and_the_status_runs(): void
    {
        $this->useFileCache($this->dir . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'data');   // none of cache/data exists

        $this->artisan('messaging:status')
            ->expectsOutputToContain('Write/read test: ok')
            ->expectsOutputToContain('External messaging (effective): OFF')   // off unless explicitly enabled
            ->expectsOutputToContain('Nothing was sent and no provider was contacted')
            ->assertExitCode(0);
        $this->assertSame([], glob($this->dir . '/cache/data/*/*/messaging-status-probe*') ?: [], 'the probe leaves nothing behind');
    }

    /** A cache whose write fails the way production's did (Laravel's silent mkdir failed, then file_put_contents raised this). */
    private function useStore(object $store): void
    {
        Cache::extend('probe-test', fn () => Cache::repository($store));
        config(['cache.default' => 'probe-test', 'cache.stores.probe-test' => ['driver' => 'probe-test']]);
        Cache::forgetDriver('probe-test');
    }

    public function test_a_cache_that_cannot_be_written_is_reported_with_the_real_error_and_the_command_fails(): void
    {
        $this->useStore(new class extends ArrayStore {
            public function put($key, $value, $seconds)
            {
                throw new \ErrorException('file_put_contents(/srv/storage/framework/cache/data/1b/f0/abc): Failed to open stream: No such file or directory');
            }
        });

        $this->artisan('messaging:status')
            ->expectsOutputToContain('Cache write FAILED: file_put_contents(/srv/storage/framework/cache/data/1b/f0/abc): Failed to open stream: No such file or directory')   // the real error, verbatim
            ->expectsOutputToContain('chown -R')                      // and the repair
            ->doesntExpectOutputToContain('Nothing was sent')         // the report stops: the settings it would read go through this cache
            ->assertExitCode(1);
    }

    public function test_a_cache_that_accepts_a_value_but_does_not_keep_it_is_a_failure_too(): void
    {
        $this->useStore(new class extends ArrayStore {
            public function put($key, $value, $seconds)
            {
                return true;   // claims success, stores nothing
            }
        });

        $this->artisan('messaging:status')
            ->expectsOutputToContain('Cache write FAILED: the cache accepted a value but returned something else when it was read back')
            ->assertExitCode(1);
    }

    public function test_the_probe_does_not_depend_on_messaging_and_never_sends_anything(): void
    {
        $this->useFileCache($this->dir . DIRECTORY_SEPARATOR . 'data');
        ExternalMessaging::$outboundGuard = function (string $channel, string $host) {
            throw new \RuntimeException("messaging:status contacted {$channel} {$host}");
        };

        try {
            $this->artisan('messaging:status')->assertExitCode(0);
        } finally {
            ExternalMessaging::$outboundGuard = null;
        }
    }

    public function test_the_status_prints_no_saved_secret(): void
    {
        $society = $this->makeSociety();
        SystemSetting::set('sms', 'fast2sms_api_key', 'SECRET-KEY-VALUE-123', 'string', $society->id);
        SystemSetting::set('email', 'mail_host', 'smtp.example.test', 'string', $society->id);
        SystemSetting::set('email', 'mail_password', 'SECRET-SMTP-PASSWORD-456', 'string', $society->id);
        $this->useFileCache($this->dir . DIRECTORY_SEPARATOR . 'data');

        $this->artisan('messaging:status')
            ->expectsOutputToContain('API key set')
            ->doesntExpectOutputToContain('SECRET-KEY-VALUE-123')
            ->doesntExpectOutputToContain('SECRET-SMTP-PASSWORD-456')
            ->assertExitCode(0);
    }

    public function test_env_example_names_the_cache_variable_laravel_10_actually_reads(): void
    {
        $example = (string) file_get_contents(base_path('.env.example'));

        $this->assertMatchesRegularExpression('/^CACHE_DRIVER=\w+\s*$/m', $example);
        $this->assertDoesNotMatchRegularExpression('/^CACHE_STORE=/m', $example, 'CACHE_STORE is Laravel 11; here it is silently ignored');
        $this->assertStringContainsString("env('CACHE_DRIVER'", (string) file_get_contents(config_path('cache.php')));
    }
}
