<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * (The SMTP user name is an identifier the admin edits in a plain field, so it is deliberately not in this list; passwords and API keys are.)
 * Provider credentials saved by a society never come back out: not in any Settings tab, not in the mobile/API payloads a resident or admin can read. */
class ProviderSecretsNotExposedTest extends TestCase
{
    use RefreshDatabase;

    private const SECRETS = [
        ['sms', 'fast2sms_api_key', 'F2S-SECRET-KEY-aaaa1111bbbb2222'],
        ['whatsapp', 'whatsapp_api_key', 'WA-SECRET-KEY-cccc3333dddd4444'],
        ['email', 'mail_password', 'SMTP-SECRET-PASS-eeee5555ffff6666'],
        ['sms', 'bulksmsplans_api_password', 'BULK-SECRET-hhhh8888iiii9999'],
    ];

    public function test_no_settings_tab_and_no_api_payload_contains_a_saved_credential(): void
    {
        $society = $this->makeSociety('Secretive');
        $admin = $this->makeUserWithRole($society, 'Admin');
        $owner = $this->makeUserWithRole($society, 'Owner');
        Permission::firstOrCreate(['name' => 'manage_settings', 'guard_name' => 'web']);
        $admin->givePermissionTo('manage_settings');
        foreach (self::SECRETS as [$group, $key, $value]) {
            SystemSetting::set($group, $key, $value, 'encrypted', $society->id);
        }

        $leaks = [];
        foreach (['general', 'email', 'sms', 'whatsapp', 'security', 'features', 'notifications'] as $tab) {
            $html = $this->actingAs($admin)->get(route('settings.index', ['tab' => $tab]))->getContent();
            foreach (self::SECRETS as [, , $value]) {
                if (str_contains($html, $value)) {
                    $leaks[] = "settings tab $tab shows a saved credential";
                }
            }
        }

        foreach ([$admin, $owner] as $user) {
            Sanctum::actingAs($user);
            foreach (['/api/v1/auth/me', '/api/v1/profile', '/api/v1/society/info', '/api/v1/dashboard'] as $url) {
                $body = $this->getJson($url)->getContent();
                foreach (self::SECRETS as [, , $value]) {
                    if (str_contains($body, $value)) {
                        $leaks[] = "$url as {$user->getRoleNames()->first()} contains a saved credential";
                    }
                }
            }
        }

        $this->assertSame([], array_values(array_unique($leaks)));
    }

    public function test_the_credentials_are_stored_encrypted_at_rest(): void
    {
        $society = $this->makeSociety('Vault');
        SystemSetting::set('sms', 'fast2sms_api_key', 'F2S-SECRET-KEY-aaaa1111bbbb2222', 'encrypted', $society->id);

        $raw = \Illuminate\Support\Facades\DB::table('system_settings')->where('key', 'fast2sms_api_key')->value('value');

        $this->assertStringNotContainsString('F2S-SECRET-KEY', (string) $raw, 'the stored value is not the plain secret');
        $this->assertSame('F2S-SECRET-KEY-aaaa1111bbbb2222', SystemSetting::get('sms', 'fast2sms_api_key', null, $society->id), 'but the application can read it back');
    }
}
