<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Console\Output\BufferedOutput;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CredentialAuditCommandTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'sk_test_ThisIsAFakeSecretValue_123456';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMockingConsoleOutput();   // we read the real output to prove no secret is in it
    }

    /** @return array{0:int,1:string} exit code and the command's output */
    private function audit(): array
    {
        $out = new BufferedOutput();
        $exit = \Illuminate\Support\Facades\Artisan::call('credentials:audit', [], $out);

        return [$exit, $out->fetch()];
    }

    private function store(string $key, string $value, bool $encrypted, ?int $society = null): void
    {
        DB::table('system_settings')->insert([
            'group' => 'sms', 'key' => $key, 'value' => $encrypted ? Crypt::encryptString($value) : $value,
            'type' => $encrypted ? 'encrypted' : 'string', 'society_id' => $society, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_encrypted_secrets_pass_and_the_value_is_never_printed(): void
    {
        $society = $this->makeSociety();
        $this->store('fast2sms_api_key', self::SECRET, true, $society->id);

        [$exit, $out] = $this->audit();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('fast2sms_api_key', $out);
        $this->assertStringContainsString('encrypted', $out);
        $this->assertStringNotContainsString(self::SECRET, $out, 'the secret must never be printed');
        $this->assertStringContainsString(substr(hash('sha256', self::SECRET), 0, 8), $out, 'a fingerprint is shown instead');
    }

    public function test_a_plaintext_secret_is_flagged_and_fails_the_audit(): void
    {
        $this->store('whatsapp_api_key', self::SECRET, false, $this->makeSociety()->id);

        [$exit, $out] = $this->audit();

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('PLAINTEXT', $out);
        $this->assertStringNotContainsString(self::SECRET, $out);
    }

    public function test_the_fingerprint_changes_when_the_secret_is_rotated(): void
    {
        $society = $this->makeSociety();
        $this->store('fast2sms_api_key', 'old-secret-value-aaaaaaaa', true, $society->id);
        [, $before] = $this->audit();

        DB::table('system_settings')->where('key', 'fast2sms_api_key')->update(['value' => Crypt::encryptString('new-secret-value-bbbbbbbb')]);
        [, $after] = $this->audit();

        $this->assertStringContainsString(substr(hash('sha256', 'old-secret-value-aaaaaaaa'), 0, 8), $before);
        $this->assertStringNotContainsString(substr(hash('sha256', 'old-secret-value-aaaaaaaa'), 0, 8), $after);
        $this->assertStringContainsString(substr(hash('sha256', 'new-secret-value-bbbbbbbb'), 0, 8), $after);
    }

    public function test_it_is_read_only(): void
    {
        $this->store('fast2sms_api_key', self::SECRET, false, $this->makeSociety()->id);
        $before = DB::table('system_settings')->get()->toJson();

        $this->audit();

        $this->assertSame($before, DB::table('system_settings')->get()->toJson());
    }
}
