<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Services\SmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Two things that only went wrong in production:
 *
 *  1. env() read outside a config file returns null once `php artisan config:cache` has run. SmsService read the
 *     Fast2SMS key (and route / sender / template / OTP id) that way, so the .env fallback worked on a laptop and stopped
 *     in production. It now reads config('sms.*').
 *  2. A Fast2SMS key in the server environment used to switch "Integration active" back on for OTP, so a society that
 *     had switched SMS off still had codes sent. Off now means off.
 */
class SmsConfigCacheSafetyTest extends TestCase
{
    use RefreshDatabase;

    private array $calls = [];
    private $society;

    protected function setUp(): void
    {
        parent::setUp();
        $this->society = $this->makeSociety('Alpha');
        SmsService::$transportOverride = function ($url, $data, $headers = []) {
            $this->calls[] = ['url' => $url, 'data' => $data, 'headers' => $headers];

            return [['return' => true, 'request_id' => 'r1'], 200];
        };
    }

    protected function tearDown(): void
    {
        SmsService::$transportOverride = null;
        parent::tearDown();
    }

    public function test_no_application_code_calls_env_directly(): void
    {
        $offenders = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(app_path(), \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            foreach (file($file->getPathname()) as $n => $line) {
                if (preg_match('/(?<![A-Za-z_>:])env\(/', $line) && !str_contains(ltrim($line), '//') && !str_contains(ltrim($line), '*')) {
                    $offenders[] = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getPathname()) . ':' . ($n + 1);
                }
            }
        }

        $this->assertSame([], $offenders, 'env() outside config/ is null under config:cache - read config() instead');
    }

    public function test_the_environment_key_is_used_as_a_fallback_only_through_config(): void
    {
        config(['sms.fast2sms.api_key' => 'ENVKEY-abcdefghijklmnop', 'sms.fast2sms.route' => 'otp', 'sms.fast2sms.otp_id' => 'otpid12345']);
        SystemSetting::set('sms', 'sms_enabled', true, 'boolean', $this->society->id);   // enabled, but no key saved for the society

        $r = SmsService::sendOtp('9811113333', '4821', $this->society->id);

        $this->assertTrue($r['sent']);
        $this->assertSame('https://www.fast2sms.com/dev/otp/send', $this->calls[0]['url']);
        $this->assertContains('authorization: ENVKEY-abcdefghijklmnop', $this->calls[0]['headers']);
    }

    public function test_a_societys_own_key_wins_over_the_environment(): void
    {
        config(['sms.fast2sms.api_key' => 'ENVKEY-abcdefghijklmnop', 'sms.fast2sms.route' => 'otp', 'sms.fast2sms.otp_id' => 'otpid12345']);
        SystemSetting::set('sms', 'sms_enabled', true, 'boolean', $this->society->id);
        SystemSetting::set('sms', 'fast2sms_api_key', 'SOCIETY-KEY-qrstuvwxyz0123', 'encrypted', $this->society->id);

        SmsService::sendOtp('9811113333', '4821', $this->society->id);

        $this->assertContains('authorization: SOCIETY-KEY-qrstuvwxyz0123', $this->calls[0]['headers']);
    }

    public function test_switching_the_integration_off_stops_otp_even_when_the_server_has_a_key(): void
    {
        config(['sms.fast2sms.api_key' => 'ENVKEY-abcdefghijklmnop', 'sms.fast2sms.route' => 'otp', 'sms.fast2sms.otp_id' => 'otpid12345']);
        SystemSetting::set('sms', 'sms_enabled', false, 'boolean', $this->society->id);

        $r = SmsService::sendOtp('9811113333', '4821', $this->society->id);

        $this->assertFalse($r['sent']);
        $this->assertSame('skipped_disabled', $r['status']);
        $this->assertSame([], $this->calls, 'nothing left the server');
    }

    public function test_the_default_is_off_when_nothing_was_ever_saved(): void
    {
        config(['sms.fast2sms.api_key' => 'ENVKEY-abcdefghijklmnop']);

        $r = SmsService::sendOtp('9811113333', '4821', $this->society->id);

        $this->assertSame('skipped_disabled', $r['status']);
        $this->assertSame([], $this->calls);
    }
}
