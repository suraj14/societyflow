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

    // ---------------------------------------------------------------------------------------------- apply-release.sh: dry run, then apply

    private function skipWhenRoot(): void
    {
        if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
            $this->markTestSkipped('apply-release.sh refuses to run as root, on purpose; this runs on a developer machine');
        }
    }

    /**
     * A throw-away "live application" (only the files the pre-flight looks for) and a tiny release package for it, plus a php wrapper that
     * claims the extensions are loaded and otherwise runs the real PHP.
     *
     * @return array{id:string, app:string, releases:string, backups:string, bin:string, package:string, pkgDir:string, php:string}
     */
    private function stagingWorkspace(string $id = '20261024-130000-aaaaaaaaaa'): array
    {
        $dir = $this->scratchDir();
        $app = $dir . DIRECTORY_SEPARATOR . 'live';
        foreach (['bootstrap', 'public', 'vendor', 'storage'] as $d) {
            mkdir($app . DIRECTORY_SEPARATOR . $d, 0700, true);
        }
        file_put_contents($app . '/artisan', "<?php\n");
        file_put_contents($app . '/.env', "APP_ENV=testing\n");
        file_put_contents($app . '/bootstrap/app.php', "<?php\n");
        file_put_contents($app . '/public/index.php', "<?php\n");
        file_put_contents($app . '/vendor/autoload.php', "<?php\n");
        file_put_contents($app . '/app-file.txt', "live version\n");

        $bin = $dir . DIRECTORY_SEPARATOR . 'bin';
        mkdir($bin, 0700, true);
        file_put_contents(
            $bin . '/php',
            "#!/usr/bin/env bash\ncase \"\$1\" in\n  -m) printf '%s\\n' ctype dom fileinfo filter gd hash iconv json libxml mbstring openssl pcre pdo_mysql session simplexml tokenizer xml xmlreader xmlwriter zip zlib curl;;\n  *) exec \"" . $this->posix(PHP_BINARY) . "\" \"\$@\";;\nesac\n",
        );
        $this->assertSame(0, $this->bashSnippet('chmod +x ' . escapeshellarg($this->posix($bin . '/php')))['code']);

        $workspace = ['id' => $id, 'app' => $app, 'releases' => $dir . DIRECTORY_SEPARATOR . 'releases', 'backups' => $dir . DIRECTORY_SEPARATOR . 'backups', 'bin' => $bin,
            'package' => $dir . DIRECTORY_SEPARATOR . 'incoming' . DIRECTORY_SEPARATOR . "societyflow-$id.tar.gz", 'pkgDir' => $dir . DIRECTORY_SEPARATOR . 'pkg', 'php' => $bin . '/php'];
        $this->buildPackage($workspace, 'new version');

        return $workspace;
    }

    /** (Re)builds the package in $ws['package'] with the given content for app-file.txt. */
    private function buildPackage(array $ws, string $content): void
    {
        $this->assertSame(0, $this->bashSnippet('rm -rf ' . escapeshellarg($this->posix($ws['pkgDir'])))['code']);
        mkdir($ws['pkgDir'] . '/app', 0700, true);
        @mkdir(dirname($ws['package']), 0700, true);
        file_put_contents($ws['pkgDir'] . '/app-file.txt', $content . "\n");
        file_put_contents($ws['pkgDir'] . '/app/Example.php', "<?php\n// {$content}\n");
        file_put_contents($ws['pkgDir'] . '/RELEASE.txt', "release_id={$ws['id']}\nbuilt_utc=2026-10-24 13:00:00\nsource_head=aaaaaaaaaabbbbbbbbbbccccccccccdddddddddd\nfiles=3\n");
        $pkg = escapeshellarg($this->posix($ws['pkgDir']));
        $out = escapeshellarg($this->posix($ws['package']));
        $incoming = escapeshellarg($this->posix(dirname($ws['package'])));
        $list = escapeshellarg($this->posix(dirname($ws['package']) . '/list.txt'));
        // same shape as scripts/deploy/ci/build-release.sh: member names WITHOUT a leading ./, RELEASE.sha256 hashing every other file
        $r = $this->bashSnippet(
            "cd $pkg && find . -type f ! -name RELEASE.sha256 | sed 's|^\\./||' | LC_ALL=C sort | while IFS= read -r p; do sha256sum -- \"\$p\"; done > RELEASE.sha256"
            . " && find . -type f | sed 's|^\\./||' | LC_ALL=C sort > $list && tar -czf $out -C $pkg -T $list"
            . " && cd $incoming && sha256sum " . escapeshellarg(basename($ws['package'])) . ' > ' . escapeshellarg(basename($ws['package']) . '.sha256'),
        );
        $this->assertSame(0, $r['code'], $r['err']);
    }

    /** @param list<string> $extra @return array{code:int,out:string,err:string} */
    private function applyRelease(array $ws, array $extra = []): array
    {
        return $this->runScript(
            base_path('scripts/deploy/server/apply-release.sh'),
            array_merge(['--package', $this->posix($ws['package']), '--app-dir', $this->posix($ws['app']), '--releases-dir', $this->posix($ws['releases']),
                '--backup-dir', $this->posix($ws['backups']), '--site-user', 'siteuser'], $extra),
            ['PHP_BIN' => $this->posix($ws['php']), 'COMPOSER_BIN' => 'composer'],
            $ws['bin'],
        );
    }

    /** a fingerprint of everything under the fake live application */
    private function treeOf(string $dir): string
    {
        $lines = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)) as $file) {
            $lines[] = str_replace('\\', '/', substr($file->getPathname(), strlen($dir))) . ' ' . sha1_file($file->getPathname());
        }
        sort($lines);

        return implode("\n", $lines);
    }

    public function test_a_dry_run_followed_by_a_second_run_reuses_its_own_unpacked_folder(): void
    {
        $this->skipWhenRoot();
        $ws = $this->stagingWorkspace();

        $first = $this->applyRelease($ws);
        $this->assertSame(0, $first['code'], $first['out'] . $first['err']);
        $this->assertStringContainsString('DRY RUN complete', $first['out']);
        $this->assertStringContainsString("release {$ws['id']} unpacked", $first['out']);

        $second = $this->applyRelease($ws);
        $this->assertSame(0, $second['code'], 'the second run must not trip over the folder the first one made: ' . $second['out'] . $second['err']);
        $this->assertStringContainsString('was already unpacked by its dry run; it is identical to the package', $second['out']);
    }

    public function test_the_real_run_after_the_dry_run_gets_past_unpacking_and_stops_before_changing_anything_when_it_cannot_read_the_database(): void
    {
        $this->skipWhenRoot();
        $ws = $this->stagingWorkspace();
        $before = $this->treeOf($ws['app']);
        $this->assertSame(0, $this->applyRelease($ws)['code']);

        // what deploy.sh does next: the same package, --apply --non-interactive. The fake app has no database, so the automated gate stops it.
        $apply = $this->applyRelease($ws, ['--apply', '--non-interactive', '--confirm-release', $ws['id']]);

        $this->assertSame(3, $apply['code'], 'exit 3 = the automated deploy refuses to go on: ' . $apply['out'] . $apply['err']);
        $this->assertStringContainsString('was already unpacked by its dry run', $apply['out']);
        $this->assertStringContainsString('nothing was changed', $apply['err']);
        $this->assertSame($before, $this->treeOf($ws['app']), 'the live application must be byte-for-byte unchanged');
        $this->assertFileDoesNotExist($ws['app'] . '/storage/framework/down', 'the site must not have been put into maintenance mode');
    }

    /** @dataProvider tamperedStagingFolders */
    public function test_a_staging_folder_that_is_not_exactly_this_package_is_refused(string $tamper): void
    {
        $this->skipWhenRoot();
        $ws = $this->stagingWorkspace();
        $this->assertSame(0, $this->applyRelease($ws)['code']);
        $stage = $ws['releases'] . DIRECTORY_SEPARATOR . $ws['id'];
        $this->assertDirectoryExists($stage);
        $before = $this->treeOf($ws['app']);

        match ($tamper) {
            'an edited file'          => file_put_contents($stage . '/app/Example.php', "<?php\n// something else\n"),
            'an extra file'           => file_put_contents($stage . '/app/Injected.php', "<?php\n"),
            'a missing file'          => unlink($stage . '/app-file.txt'),
            'another package under the same release id' => $this->buildPackage($ws, 'a different build'),
        };

        $r = $this->applyRelease($ws);

        $this->assertSame(1, $r['code'], $r['out'] . $r['err']);
        $this->assertStringContainsString('NOT identical to this package', $r['err']);
        $this->assertSame($before, $this->treeOf($ws['app']), 'the live application must be untouched');
    }

    public static function tamperedStagingFolders(): array
    {
        return [
            'an edited file'                            => ['an edited file'],
            'an extra file'                             => ['an extra file'],
            'a missing file'                            => ['a missing file'],
            'another package under the same release id' => ['another package under the same release id'],
        ];
    }

    // ---------------------------------------------------------------------------------------------- inspect-server.sh: will PHP serve the new code?

    /**
     * @dataProvider opcacheSettings
     * @param array{ini?:string, confd?:string, pool?:string, noPool?:bool} $tree
     */
    public function test_the_server_inspection_warns_when_php_would_keep_serving_old_code(array $tree, string $expectedMarker, string $expectedText): void
    {
        $dir = $this->scratchDir();
        $etc = $dir . '/etc-php/8.2/fpm';
        mkdir($etc . '/conf.d', 0700, true);
        mkdir($etc . '/pool.d', 0700, true);
        mkdir($dir . '/app', 0700, true);
        file_put_contents($etc . '/php.ini', ($tree['ini'] ?? '') . "\n");
        file_put_contents($etc . '/conf.d/10-opcache.ini', ($tree['confd'] ?? '') . "\n");
        if (empty($tree['noPool'])) {
            file_put_contents($etc . '/pool.d/siteuser.conf', "[siteuser]\nuser = siteuser\ngroup = siteuser\nlisten = /run/php/x.sock\n" . ($tree['pool'] ?? '') . "\n");
        }
        $bin = $this->scratchDir('sfbin');

        $r = $this->runScript(base_path('scripts/deploy/server/inspect-server.sh'), [$this->posix($dir . '/app'), 'siteuser', 'example.invalid'], ['PHP_ETC' => $this->posix($dir . '/etc-php'), 'PHP_BIN' => '/nonexistent'], $bin);

        $this->assertSame(0, $r['code'], $r['err']);
        $this->assertSame(1, preg_match('/==== PHP - will the web runtime pick up new files[^\n]*\n(.*?)\n==== Nginx/s', $r['out'], $m), 'the OPcache section is missing: ' . $r['out']);
        $this->assertStringContainsString($expectedMarker, $m[1]);
        $this->assertStringContainsString($expectedText, $m[1]);
    }

    public static function opcacheSettings(): array
    {
        return [
            'PHP defaults'                                  => [[], '[ok]', 'new code is served within about 2 s'],
            'validate_timestamps off in php.ini'            => [['ini' => 'opcache.validate_timestamps=0'], '[!!]', 'will keep serving the old code'],
            'php.ini off but the site pool turns it on'     => [['ini' => 'opcache.validate_timestamps=0', 'pool' => 'php_admin_value[opcache.validate_timestamps] = 1'], '[ok]', 'validate_timestamps=1'],
            'the site pool switches it off with a flag'     => [['confd' => 'opcache.validate_timestamps=1', 'pool' => 'php_admin_flag[opcache.validate_timestamps] = off'], '[!!]', 'will keep serving the old code'],
            'a long revalidate interval'                    => [['confd' => "opcache.validate_timestamps=1\nopcache.revalidate_freq=60"], '[!!]', 'can take up to 60 s'],
            'OPcache disabled'                              => [['confd' => 'opcache.enable=0'], '[ok]', 'new code is served at once'],
            'no pool for the site user (cannot be checked)' => [['ini' => 'opcache.validate_timestamps=0', 'noPool' => true], 'could not be checked', 'validate_timestamps'],
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
