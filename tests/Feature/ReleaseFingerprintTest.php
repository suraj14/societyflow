<?php

namespace Tests\Feature;

use App\Support\ReleaseFingerprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A deployment must prove, from outside, that the live site serves the release it just shipped - not assume it. The packaged deploy puts a
 * RELEASE.txt in the application root; the public health answer carries an OPAQUE fingerprint of its release id in a response header, which
 * scripts/deploy/server/smoke-check.sh --expect-release compares with the id it deployed. The health BODY must not change (the phone app's
 * server discovery pins it: HealthEndpointTest).
 */
class ReleaseFingerprintTest extends TestCase
{
    use RefreshDatabase;

    private const ID = '20261024-120336-2280154fb7';

    /** sha256(ID) cut to 16 hex characters by the shell, exactly as the pipeline computes it: `printf %s ID | sha256sum | cut -c1-16` */
    private const SHELL_VALUE = 'b6a3ad0d61e60075';

    private ?string $file = null;

    protected function tearDown(): void
    {
        if ($this->file !== null && is_file($this->file)) {
            @unlink($this->file);
        }

        parent::tearDown();
    }

    private function releaseFile(string $contents): void
    {
        $this->file = tempnam(sys_get_temp_dir(), 'sfrel');
        file_put_contents($this->file, $contents);
        config(['app.release_file' => $this->file]);
    }

    private function assertBodyUnchanged($response): void
    {
        $response->assertOk()->assertExactJson(['application' => 'societyflow', 'api' => 'v1', 'status' => 'ok']);
    }

    public function test_a_site_without_a_release_file_sends_no_fingerprint(): void
    {
        config(['app.release_file' => sys_get_temp_dir() . '/definitely-not-a-release-file-' . uniqid()]);

        $response = $this->getJson('/api/v1/health');

        $this->assertBodyUnchanged($response);
        $response->assertHeaderMissing(ReleaseFingerprint::HEADER);
        $this->assertNull(ReleaseFingerprint::current());
    }

    public function test_the_health_answer_carries_the_fingerprint_of_the_release_in_release_txt(): void
    {
        $this->releaseFile("release_id=" . self::ID . "\nbuilt_utc=2026-10-24T12:03:36Z\nsource_head=2280154fb770f3787535c033c95c860057a907eb\n");

        $response = $this->getJson('/api/v1/health');

        $this->assertBodyUnchanged($response);
        $response->assertHeader(ReleaseFingerprint::HEADER, self::SHELL_VALUE);
        $this->assertSame(self::SHELL_VALUE, ReleaseFingerprint::of(self::ID), 'PHP and the shell (sha256sum | cut -c1-16) must agree, or the pipeline can never match');
    }

    public function test_the_fingerprint_is_opaque_and_reveals_no_build_time_or_commit(): void
    {
        $this->releaseFile("release_id=" . self::ID . "\nsource_head=2280154fb770f3787535c033c95c860057a907eb\n");

        $response = $this->getJson('/api/v1/health');
        $everything = $response->getContent() . json_encode($response->headers->all());

        $this->assertMatchesRegularExpression('/^[0-9a-f]{16}$/', $response->headers->get(ReleaseFingerprint::HEADER));
        foreach ([self::ID, '2280154', '20261024', '120336'] as $secret) {
            $this->assertStringNotContainsString($secret, $everything, "the health answer must not reveal '$secret'");
        }
    }

    public function test_only_the_release_id_line_is_used_whatever_else_the_file_holds_and_windows_line_endings_are_fine(): void
    {
        $this->releaseFile("built_by=someone\r\nrelease_id=" . self::ID . "\r\nfiles=775\r\n");

        $this->assertSame(self::SHELL_VALUE, ReleaseFingerprint::current());
    }

    /** @dataProvider unusableReleaseFiles */
    public function test_a_release_file_without_a_well_formed_release_id_is_ignored(string $contents): void
    {
        $this->releaseFile($contents);

        $response = $this->getJson('/api/v1/health');

        $this->assertBodyUnchanged($response);
        $response->assertHeaderMissing(ReleaseFingerprint::HEADER);
    }

    public static function unusableReleaseFiles(): array
    {
        return [
            'empty file'                  => [''],
            'no release_id line'          => ["built_utc=2026-10-24T12:03:36Z\nfiles=775\n"],
            'empty release id'            => ["release_id=\n"],
            'too short'                   => ["release_id=ab\n"],
            'spaces in the id'            => ["release_id=2026 10 24\n"],
            'markup in the id'            => ["release_id=<script>alert(1)</script>\n"],
            'id of absurd length'         => ['release_id=' . str_repeat('a', 500) . "\n"],
            'id not at the start of line' => ["  release_id=" . self::ID . "\n"],
            'binary garbage'              => ["\x00\xff\xfe\x80 not a release file \x00\x01\x02"],
        ];
    }

    public function test_the_fingerprint_is_only_on_the_health_endpoint(): void
    {
        $this->releaseFile("release_id=" . self::ID . "\n");

        $this->get('/login')->assertHeaderMissing(ReleaseFingerprint::HEADER);
        $this->getJson('/api/v1/password-policy')->assertHeaderMissing(ReleaseFingerprint::HEADER);
    }

    public function test_the_release_file_defaults_to_release_txt_in_the_application_root(): void
    {
        $this->assertSame(base_path('RELEASE.txt'), config('app.release_file'), 'the packaged deploy writes RELEASE.txt next to artisan');
    }
}
