<?php

namespace Tests\Feature;

use App\Support\ReleaseFingerprint;
use Tests\Concerns\RunsDeploymentScripts;
use Tests\TestCase;

/**
 * The deployment scripts, EXECUTED (DeploymentArtifactsTest only reads them). Nothing leaves this machine: ssh/scp are recorders that fail,
 * and the "live site" is a throw-away PHP server on 127.0.0.1. What is proven here:
 *  - deploy.sh opens no connection at all unless the pipeline runs on the designated production branch at the commit the package was
 *    built from - a custom pipeline can be started on any branch from Bitbucket, so the script has to refuse for itself;
 *  - smoke-check.sh --expect-release really compares the release the live site reports with the one just deployed, instead of printing
 *    a line and passing whatever the site serves.
 */
class DeploymentScriptsBehaviourTest extends TestCase
{
    use RunsDeploymentScripts;

    private const COMMIT = '1111111111222222222233333333334444444444';
    private const RELEASE = '20261024-120336-1111111111';

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireBash();
    }

    protected function tearDown(): void
    {
        $this->cleanUpDeploymentScripts();
        parent::tearDown();
    }

    // ---------------------------------------------------------------------------------------------- deploy.sh: which branch may deploy

    /** @return array{0:string,1:string,2:string} build dir, fake bin dir, calls log */
    private function deployWorkspace(): array
    {
        $dir = $this->scratchDir();
        [$bin, $log] = $this->fakeTransport($dir);
        $build = $dir . DIRECTORY_SEPARATOR . 'build';
        $pkg = $dir . DIRECTORY_SEPARATOR . 'pkg';
        mkdir($build . DIRECTORY_SEPARATOR . 'server-tools', 0700, true);
        mkdir($pkg, 0700, true);
        file_put_contents($pkg . DIRECTORY_SEPARATOR . 'RELEASE.txt', 'release_id=' . self::RELEASE . "\nsource_head=" . self::COMMIT . "\n");
        file_put_contents($build . DIRECTORY_SEPARATOR . 'server-tools' . DIRECTORY_SEPARATOR . 'apply-release.sh', "#!/usr/bin/env bash\n");
        $tar = $this->bashSnippet(sprintf('tar -czf %s -C %s RELEASE.txt', escapeshellarg($this->posix($build . '/societyflow-' . self::RELEASE . '.tar.gz')), escapeshellarg($this->posix($pkg))));
        $this->assertSame(0, $tar['code'], $tar['err']);

        return [$build, $bin, $log];
    }

    /** @param array<string,string> $env @return array{code:int,out:string,err:string,calls:string} */
    private function deploy(array $env, string $mode = 'dry-run'): array
    {
        [$build, $bin, $log] = $this->deployWorkspace();
        $base = ['PROD_SSH_HOST' => 'example.invalid', 'PROD_SSH_USER' => 'siteuser', 'PROD_APP_DIR' => '/home/siteuser/htdocs/site', 'PROD_URL' => 'https://example.invalid'];
        $result = $this->runScript(base_path('scripts/deploy/ci/deploy.sh'), [$this->posix($build), $mode], $env + $base, $bin);
        $result['calls'] = trim((string) file_get_contents($log));

        return $result;
    }

    /** @dataProvider refusedDeployments */
    public function test_deploy_refuses_before_opening_any_connection(array $env, string $mode, string $message): void
    {
        $r = $this->deploy($env, $mode);

        $this->assertSame(2, $r['code'], $r['err']);
        $this->assertStringContainsString($message, $r['err']);
        $this->assertSame('', $r['calls'], 'a refused deployment must not call ssh or scp at all');
    }

    public static function refusedDeployments(): array
    {
        $right = ['BITBUCKET_BRANCH' => 'production-readiness', 'BITBUCKET_COMMIT' => self::COMMIT];

        return [
            'a feature branch'                              => [['BITBUCKET_BRANCH' => 'feature/x', 'BITBUCKET_COMMIT' => self::COMMIT], 'dry-run', "only the branch 'production-readiness'"],
            'the integration branch'                        => [['BITBUCKET_BRANCH' => 'integration/main-merge', 'BITBUCKET_COMMIT' => self::COMMIT], 'dry-run', 'REFUSED'],
            'main, while production-readiness is designated' => [['BITBUCKET_BRANCH' => 'main', 'BITBUCKET_COMMIT' => self::COMMIT], 'apply', 'REFUSED'],
            'no branch at all (a tag, or not Bitbucket)'    => [['BITBUCKET_COMMIT' => self::COMMIT], 'apply', 'REFUSED'],
            'an empty branch name'                          => [['BITBUCKET_BRANCH' => '', 'BITBUCKET_COMMIT' => self::COMMIT], 'apply', 'REFUSED'],
            'a branch that only starts like the designated' => [['BITBUCKET_BRANCH' => 'production-readiness-2', 'BITBUCKET_COMMIT' => self::COMMIT], 'apply', 'REFUSED'],
            'the right branch but no commit id'             => [['BITBUCKET_BRANCH' => 'production-readiness'], 'apply', 'BITBUCKET_COMMIT is not set'],
            'a package built from another commit'           => [['BITBUCKET_BRANCH' => 'production-readiness', 'BITBUCKET_COMMIT' => '9999999999999999999999999999999999999999'], 'apply', 'built from commit'],
            'the designated branch moved to main'           => [$right + ['PROD_DEPLOY_BRANCH' => 'main'], 'apply', "only the branch 'main'"],
            'characters that could escape a command line'   => [['PROD_DEPLOY_BRANCH' => 'main;rm', 'BITBUCKET_BRANCH' => 'main', 'BITBUCKET_COMMIT' => self::COMMIT], 'apply', 'characters that are not allowed'],
        ];
    }

    /** @dataProvider permittedDeployments */
    public function test_deploy_goes_ahead_only_on_the_designated_branch_at_the_packaged_commit(array $env, string $mode): void
    {
        $r = $this->deploy($env, $mode);

        $this->assertNotSame(2, $r['code'], 'the guard must let the designated branch through: ' . $r['err']);
        $this->assertStringContainsString('ssh', $r['calls'], 'the first thing a permitted deployment does is open the connection');
        $this->assertStringContainsString('SSH login failed', $r['err'], 'the fake ssh refuses, so the run stops right there');
        $this->assertStringContainsString('(commit 111111111122', $r['out']);
    }

    public static function permittedDeployments(): array
    {
        return [
            'dry run on the designated branch'        => [['BITBUCKET_BRANCH' => 'production-readiness', 'BITBUCKET_COMMIT' => self::COMMIT], 'dry-run'],
            'apply on the designated branch'          => [['BITBUCKET_BRANCH' => 'production-readiness', 'BITBUCKET_COMMIT' => self::COMMIT], 'apply'],
            'apply once main is designated'           => [['PROD_DEPLOY_BRANCH' => 'main', 'BITBUCKET_BRANCH' => 'main', 'BITBUCKET_COMMIT' => self::COMMIT], 'apply'],
        ];
    }

    // ---------------------------------------------------------------------------------------------- smoke-check.sh: which release is live

    /** @param list<string> $extra @return array{code:int,out:string,err:string} */
    private function smoke(string $siteTag, int $from, array $extra): array
    {
        $url = $this->startStubSite($siteTag, $from);
        $bin = $this->scratchDir('sfbin');

        return $this->runScript(base_path('scripts/deploy/server/smoke-check.sh'), array_merge(['--url', $url], $extra), [], $bin);
    }

    private function fingerprint(string $releaseId = self::RELEASE): string
    {
        return ReleaseFingerprint::of($releaseId);
    }

    public function test_without_an_expected_release_the_other_checks_still_pass_against_a_healthy_site(): void
    {
        $r = $this->smoke('', 1, []);

        $this->assertSame(0, $r['code'], $r['out']);
        $this->assertStringContainsString('smoke check PASSED', $r['out']);
        $this->assertStringNotContainsString('release', strtolower($r['out']), 'nothing about releases is checked unless asked');
    }

    public function test_the_site_reporting_the_deployed_release_passes(): void
    {
        $r = $this->smoke($this->fingerprint(), 1, ['--expect-release', self::RELEASE, '--release-wait', '0']);

        $this->assertSame(0, $r['code'], $r['out']);
        $this->assertStringContainsString('the live site reports release ' . self::RELEASE, $r['out']);
    }

    public function test_a_site_that_reports_no_release_at_all_fails(): void
    {
        $r = $this->smoke('', 1, ['--expect-release', self::RELEASE, '--release-wait', '0']);

        $this->assertSame(1, $r['code'], $r['out']);
        $this->assertStringContainsString('NO release fingerprint', $r['out']);
        $this->assertStringContainsString('smoke check FAILED', $r['out']);
    }

    public function test_a_site_still_serving_the_previous_release_fails(): void
    {
        $r = $this->smoke($this->fingerprint('20261001-000000-aaaaaaaaaa'), 1, ['--expect-release', self::RELEASE, '--release-wait', '0']);

        $this->assertSame(1, $r['code'], $r['out']);
        $this->assertStringContainsString('another release is being served', $r['out']);
        $this->assertStringContainsString($this->fingerprint('20261001-000000-aaaaaaaaaa'), $r['out'], 'the report names what the site sent, so the operator can match it to deployments.log');
    }

    public function test_php_that_takes_a_moment_to_reload_is_waited_for(): void
    {
        // the first two health answers come from the old code (no header); from the third on the new release is served
        $r = $this->smoke($this->fingerprint(), 3, ['--expect-release', self::RELEASE, '--release-wait', '15', '--release-interval', '1']);

        $this->assertSame(0, $r['code'], $r['out']);
        $this->assertStringContainsString('the live site reports release ' . self::RELEASE, $r['out']);
    }

    public function test_a_release_that_never_appears_fails_when_the_wait_is_over(): void
    {
        $r = $this->smoke('', 1, ['--expect-release', self::RELEASE, '--release-wait', '2', '--release-interval', '1']);

        $this->assertSame(1, $r['code'], $r['out']);
        $this->assertStringContainsString('after 2s', $r['out']);
    }

    /** @dataProvider unusableArguments */
    public function test_unusable_arguments_are_a_usage_error_not_a_pass(array $args, string $message): void
    {
        $r = $this->smoke($this->fingerprint(), 1, $args);

        $this->assertSame(2, $r['code'], $r['out'] . $r['err']);
        $this->assertStringContainsString($message, $r['err']);
    }

    public static function unusableArguments(): array
    {
        return [
            'a release id with spaces and shell characters' => [['--expect-release', 'a b;c'], 'not a plain release id'],
            'a zero interval'                               => [['--expect-release', self::RELEASE, '--release-interval', '0'], 'whole number of seconds'],
            'a non-numeric wait'                            => [['--expect-release', self::RELEASE, '--release-wait', 'soon'], 'number of seconds'],
            'an option without its value'                   => [['--expect-release'], 'needs a value'],
        ];
    }
}
