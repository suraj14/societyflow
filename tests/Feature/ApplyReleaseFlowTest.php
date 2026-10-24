<?php

namespace Tests\Feature;

use App\Support\ReleaseFingerprint;
use Tests\Concerns\RunsDeploymentScripts;
use Tests\TestCase;

/**
 * apply-release.sh and rollback-code.sh, run FOR REAL from start to finish against a throw-away "live application", with only what cannot exist
 * on a test machine replaced by stand-ins: php/artisan (down and up really toggle storage/framework/down), composer, the database backup and
 * record-count scripts, and the smoke check. What is proven is the CONTROL FLOW that protects a live site:
 *  - a good release is copied, checked and recorded;
 *  - unattended (--non-interactive), ANY failure after the files were copied - composer, a failed smoke check - puts the previous code back and
 *    brings the site up again, never leaving it down or half-updated; if that rollback itself fails it says so and leaves the site in maintenance;
 *  - an interactive run does the opposite on purpose: it stops, keeps the site in maintenance mode and tells the person;
 *  - lost records are reported but never "fixed" by silently swapping code.
 */
class ApplyReleaseFlowTest extends TestCase
{
    use RunsDeploymentScripts;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireBash();
        $this->skipWhenRoot();
    }

    protected function tearDown(): void
    {
        $this->cleanUpDeploymentScripts();
        parent::tearDown();
    }

    /** the real apply/rollback scripts plus stand-ins for the helpers that need a database, a web server or a Laravel app */
    private function stubbedTools(array $ws): string
    {
        $tools = dirname($ws['app']) . DIRECTORY_SEPARATOR . 'tools';
        mkdir($tools, 0700, true);
        foreach (['apply-release.sh', 'rollback-code.sh'] as $real) {
            copy(base_path('scripts/deploy/server/' . $real), $tools . DIRECTORY_SEPARATOR . $real);
        }
        file_put_contents($tools . '/db-backup.sh', $this->lf(<<<'SH'
#!/usr/bin/env bash
label=""; while [ $# -gt 0 ]; do case "$1" in --label) label="$2"; shift 2;; *) shift;; esac; done
f="$BACKUP_DIR/db-stub-$label.sql"; echo "-- stub dump" > "$f"
echo "database: stub"; echo "BACKUP_FILE=$f"
SH));
        file_put_contents($tools . '/data-fingerprint.sh', $this->lf(<<<'SH'
#!/usr/bin/env bash
if [ "${1:-}" = "--compare" ]; then
    if [ "${STUB_RECORDS:-ok}" = lost ]; then echo "LOST  owners: rows 10 -> 9"; exit 1; fi
    echo "all tables: nothing lost"; exit 0
fi
printf '# stub fingerprint\nowners\t10\t10\t0\npayments\t5\t5\t100\nusers\t3\t3\t0\n'
SH));
        // fails only when asked to verify the release (the first check); the check made after a rollback has no --expect-release and passes
        file_put_contents($tools . '/smoke-check.sh', $this->lf(<<<'SH'
#!/usr/bin/env bash
echo "$*" >> "$STUB_LOG_DIR/smoke-calls.log"
if [ "${STUB_SMOKE:-pass}" = fail ] && printf '%s' "$*" | grep -q -- '--expect-release'; then echo "  FAIL  stub: the live site is not serving this release"; exit 1; fi
echo "  PASS  stub smoke check"; exit 0
SH));

        return $tools;
    }

    /** @param array<string,string> $env @return array{code:int,out:string,err:string,deployments:string,artisan:string,smoke:string,all:string} */
    private function apply(array $ws, string $tools, array $env = [], bool $interactive = false): array
    {
        $args = ['--package', $this->posix($ws['package']), '--app-dir', $this->posix($ws['app']), '--releases-dir', $this->posix($ws['releases']),
            '--backup-dir', $this->posix($ws['backups']), '--site-user', 'siteuser', '--apply'];
        if (!$interactive) {
            array_push($args, '--non-interactive', '--confirm-release', $ws['id']);
        }
        $env += ['PHP_BIN' => $this->posix($ws['php']), 'COMPOSER_BIN' => $this->posix($ws['bin'] . '/composer'),
            'STUB_LOG_DIR' => $this->posix($ws['logs']), 'STUB_BACKUP_DIR' => $this->posix($ws['backups'])];

        $r = $this->runScript($tools . '/apply-release.sh', $args, $env, $ws['bin'], $interactive ? $ws['id'] . "\n" : null);
        $r['deployments'] = (string) @file_get_contents($ws['backups'] . '/deployments.log');
        $r['artisan'] = (string) @file_get_contents($ws['logs'] . '/artisan-calls.log');
        $r['smoke'] = (string) @file_get_contents($ws['logs'] . '/smoke-calls.log');
        $r['all'] = $r['out'] . $r['err'];

        return $r;
    }

    private function appFile(array $ws): string
    {
        return trim((string) file_get_contents($ws['app'] . '/app-file.txt'));
    }

    private function siteIsInMaintenance(array $ws): bool
    {
        return file_exists($ws['app'] . '/storage/framework/down');
    }

    public function test_a_good_release_is_copied_verified_and_recorded(): void
    {
        $ws = $this->stagingWorkspace();
        $r = $this->apply($ws, $this->stubbedTools($ws));

        $this->assertSame(0, $r['code'], $r['all']);
        $this->assertStringContainsString("DONE. Release {$ws['id']} is live", $r['out']);
        $this->assertSame('new version', $this->appFile($ws));
        $this->assertFalse($this->siteIsInMaintenance($ws), 'the site must be back up');
        $this->assertStringContainsString('release_id=' . $ws['id'], (string) file_get_contents($ws['backups'] . '/LAST_SUCCESSFUL_RELEASE'));
        $this->assertStringContainsString('status=OK', $r['deployments']);
        $this->assertStringContainsString('tag=' . ReleaseFingerprint::of($ws['id']), $r['deployments'], 'the log must carry the tag the live health header shows');
        $this->assertStringContainsString('--expect-release ' . $ws['id'], $r['smoke'], 'the smoke check must be asked to verify THIS release');
        $this->assertMatchesRegularExpression('/artisan down.*artisan up/s', $r['artisan']);
        $this->assertDirectoryDoesNotExist($ws['backups'] . '/.deploy.lock.d', 'the deployment lock must not outlive the run');
    }

    public function test_unattended_a_pending_migration_stops_the_run_with_exit_3_before_anything_changes(): void
    {
        $ws = $this->stagingWorkspace();
        $before = $this->treeOf($ws['app']);
        $r = $this->apply($ws, $this->stubbedTools($ws), ['STUB_MIGRATIONS' => 'pending']);

        $this->assertSame(3, $r['code'], $r['all']);
        $this->assertStringContainsString('does not run migrations', $r['all']);
        $this->assertSame($before, $this->treeOf($ws['app']), 'not one byte of the live application may change');
        $this->assertFalse($this->siteIsInMaintenance($ws));
        $this->assertSame('', $r['artisan'], 'artisan was not even called: no maintenance mode, no cache change');
        $this->assertDirectoryDoesNotExist($ws['backups'], 'nothing was backed up either: the run stopped at the gate');
    }

    public function test_unattended_a_failed_composer_install_puts_the_previous_code_back_and_the_site_up(): void
    {
        $ws = $this->stagingWorkspace();
        $r = $this->apply($ws, $this->stubbedTools($ws), ['STUB_COMPOSER' => 'fail']);

        $this->assertNotSame(0, $r['code']);
        $this->assertSame('live version', $this->appFile($ws), 'the previous code must be restored');
        $this->assertFalse($this->siteIsInMaintenance($ws), 'the site must not be left down');
        $this->assertStringContainsString("release {$ws['id']} was NOT deployed", $r['all']);
        $this->assertStringContainsString('status=ROLLED_BACK', $r['deployments']);
        $this->assertStringNotContainsString('status=OK', $r['deployments']);
        $this->assertFileDoesNotExist($ws['backups'] . '/LAST_SUCCESSFUL_RELEASE', 'a rolled-back release is never recorded as the last good one');
        $this->assertSame(1, substr_count($r['all'], 'restoring the previous code automatically'), 'the rollback must run once, not once per error handler');
        $this->assertStringContainsString('(after rollback)', $r['all'], 'the site is checked again after the rollback');
        $this->assertStringNotContainsString('--expect-release', $r['smoke'], 'after a rollback the OLD release is live, so no release is expected');
    }

    public function test_a_rollback_does_not_leave_the_rolled_back_release_looking_live(): void
    {
        // before: not a packaged release at all (no RELEASE.txt). After the rollback nothing may still say the failed release is live.
        $ws = $this->stagingWorkspace();
        $r = $this->apply($ws, $this->stubbedTools($ws), ['STUB_COMPOSER' => 'fail']);
        $this->assertStringContainsString('status=ROLLED_BACK', $r['deployments']);
        $this->assertFileDoesNotExist($ws['app'] . '/RELEASE.txt', 'the failed release\'s RELEASE.txt would make the health endpoint and inspect-server report it as live');
        $this->assertFileDoesNotExist($ws['app'] . '/RELEASE.sha256');
        $this->assertStringContainsString('removed the RELEASE.txt of the rolled-back release', $r['all']);
    }

    public function test_a_rollback_puts_back_the_previous_release_marker(): void
    {
        // before: a packaged release P is live. After a failed deploy of the new release the marker must name P again, not the failed one.
        $ws = $this->stagingWorkspace();
        file_put_contents($ws['app'] . '/RELEASE.txt', "release_id=20260901-000000-previous\nbuilt_utc=2026-09-01 00:00:00\n");
        $r = $this->apply($ws, $this->stubbedTools($ws), ['STUB_COMPOSER' => 'fail']);

        $this->assertStringContainsString('status=ROLLED_BACK', $r['deployments']);
        $this->assertStringContainsString('previous=20260901-000000-previous', $r['deployments']);
        $this->assertStringContainsString('release_id=20260901-000000-previous', (string) file_get_contents($ws['app'] . '/RELEASE.txt'), 'the marker must name the release that is live again');
        $this->assertStringNotContainsString($ws['id'], (string) file_get_contents($ws['app'] . '/RELEASE.txt'));
    }

    public function test_a_failing_command_that_happens_to_exit_3_is_not_mistaken_for_pending_migrations(): void
    {
        $ws = $this->stagingWorkspace();
        $r = $this->apply($ws, $this->stubbedTools($ws), ['STUB_COMPOSER' => 'exit3']);

        $this->assertSame(1, $r['code'], 'exit code 3 means "pending migrations, nothing was changed" to the pipeline; this release did change the site and was rolled back: ' . $r['all']);
        $this->assertSame('live version', $this->appFile($ws));
        $this->assertStringContainsString('status=ROLLED_BACK', $r['deployments']);
    }

    public function test_unattended_if_the_rollback_itself_fails_it_says_so_and_leaves_the_site_in_maintenance(): void
    {
        $ws = $this->stagingWorkspace();
        $r = $this->apply($ws, $this->stubbedTools($ws), ['STUB_COMPOSER' => 'corrupt']);

        $this->assertNotSame(0, $r['code']);
        $this->assertStringContainsString('AUTOMATIC ROLLBACK FAILED', $r['all']);
        $this->assertStringContainsString('status=ROLLBACK_FAILED', $r['deployments']);
        $this->assertTrue($this->siteIsInMaintenance($ws), 'a half-updated site must not be shown to visitors');
        $this->assertStringContainsString('IN MAINTENANCE MODE', $r['all']);
        $this->assertStringContainsString('rollback-code.sh --backup', $r['all'], 'the person is told how to recover');
    }

    public function test_unattended_a_failed_smoke_check_puts_the_previous_code_back(): void
    {
        $ws = $this->stagingWorkspace();
        $r = $this->apply($ws, $this->stubbedTools($ws), ['STUB_SMOKE' => 'fail']);

        $this->assertNotSame(0, $r['code']);
        $this->assertSame('live version', $this->appFile($ws));
        $this->assertFalse($this->siteIsInMaintenance($ws));
        $this->assertStringContainsString('status=ROLLED_BACK', $r['deployments']);
        $lines = array_values(array_filter(explode("\n", $r['smoke'])));
        $this->assertCount(2, $lines, 'one check of the new release, one after the rollback');
        $this->assertStringContainsString('--expect-release', $lines[0]);
        $this->assertStringNotContainsString('--expect-release', $lines[1]);
    }

    public function test_interactive_a_failure_after_the_copy_keeps_the_site_in_maintenance_and_tells_the_person(): void
    {
        $ws = $this->stagingWorkspace();
        $r = $this->apply($ws, $this->stubbedTools($ws), ['STUB_COMPOSER' => 'fail'], interactive: true);

        $this->assertNotSame(0, $r['code'], $r['all']);
        $this->assertTrue($this->siteIsInMaintenance($ws), 'an interactive run leaves the half-updated site in maintenance mode, on purpose');
        $this->assertSame('new version', $this->appFile($ws), 'nothing is rolled back behind a person\'s back');
        $this->assertStringContainsString('left that way on purpose', $r['all']);
        $this->assertStringContainsString('rollback-code.sh --backup', $r['all']);
        $this->assertStringNotContainsString('ROLLED_BACK', $r['deployments']);
        $this->assertStringNotContainsString('restoring the previous code automatically', $r['all']);
    }

    public function test_lost_records_are_reported_and_the_code_is_not_swapped_behind_the_back(): void
    {
        $ws = $this->stagingWorkspace();
        $r = $this->apply($ws, $this->stubbedTools($ws), ['STUB_RECORDS' => 'lost']);

        $this->assertNotSame(0, $r['code']);
        $this->assertStringContainsString('RECORDS WENT MISSING', $r['all']);
        $this->assertStringContainsString('ATTENTION', $r['all']);
        $this->assertStringContainsString('status=FAILED_CHECKS', $r['deployments']);
        $this->assertSame('new version', $this->appFile($ws), 'a code rollback cannot bring back rows, so it is not done automatically');
        $this->assertFalse($this->siteIsInMaintenance($ws), 'the site stays up for the people using it');
        $this->assertFileDoesNotExist($ws['backups'] . '/LAST_SUCCESSFUL_RELEASE');
    }
}
