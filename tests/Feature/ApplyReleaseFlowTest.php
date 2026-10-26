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
    private function apply(array $ws, string $tools, array $env = [], bool $interactive = false, array $extraArgs = []): array
    {
        $args = array_merge(['--package', $this->posix($ws['package']), '--app-dir', $this->posix($ws['app']), '--releases-dir', $this->posix($ws['releases']),
            '--backup-dir', $this->posix($ws['backups']), '--site-user', 'siteuser', '--apply'], $extraArgs);
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

    public function test_the_checks_use_the_given_public_address_not_the_one_in_env(): void
    {
        // the real server's .env still names a development address (http://192.168.29.143:8000); .env is never edited, so --app-url carries the truth
        $ws = $this->stagingWorkspace();
        $r = $this->apply($ws, $this->stubbedTools($ws), extraArgs: ['--app-url', 'https://societyflow.example.test/']);

        $this->assertSame(0, $r['code'], $r['all']);
        $this->assertStringContainsString('--url https://societyflow.example.test --expect-release', $r['smoke'], 'trailing slash removed, address from the option');
        $this->assertStringNotContainsString('127.0.0.1:9', $r['smoke'], 'the .env address must not be used');
        $this->assertStringContainsString('APP_URL=http://127.0.0.1:9', (string) file_get_contents($ws['app'] . '/.env'), '.env itself is untouched');
    }

    public function test_after_a_rollback_the_restored_site_is_also_checked_at_the_given_address(): void
    {
        $ws = $this->stagingWorkspace();
        $r = $this->apply($ws, $this->stubbedTools($ws), ['STUB_COMPOSER' => 'fail'], extraArgs: ['--app-url', 'https://societyflow.example.test']);

        $this->assertStringContainsString('status=ROLLED_BACK', $r['deployments']);
        $this->assertStringContainsString('--url https://societyflow.example.test', $r['smoke']);
        $this->assertStringNotContainsString('127.0.0.1:9', $r['smoke']);
    }

    public function test_a_malformed_public_address_is_refused_before_anything_happens(): void
    {
        $ws = $this->stagingWorkspace();
        $before = $this->treeOf($ws['app']);
        $r = $this->apply($ws, $this->stubbedTools($ws), extraArgs: ['--app-url', 'https://x.test/;rm -rf /']);

        $this->assertSame(2, $r['code']);
        $this->assertStringContainsString('--app-url must be a plain http(s) address', $r['all']);
        $this->assertSame($before, $this->treeOf($ws['app']));
    }

    /**
     * The failed production deploy: `tar: Cannot change mode to rwxr-xr-x: Operation not permitted`. tar -p chmods every EXISTING directory to the
     * archive's mode, which an account that does not own them cannot do. Here the live folder has a deliberately different mode (750); a copy that
     * leaves it at 750 never tried to chmod it (and so cannot fail on a folder it does not own).
     */
    public function test_copying_the_release_leaves_the_modes_of_existing_folders_alone(): void
    {
        $ws = $this->stagingWorkspace();
        $dir = $this->posix($ws['app'] . '/app');
        $this->assertSame(0, $this->bashSnippet("mkdir -p '$dir' && chmod 750 '$dir'")['code']);
        if (trim($this->bashSnippet("stat -c %a '$dir'")['out']) !== '750') {
            $this->markTestSkipped('this file system does not keep Unix modes (Windows); the pipeline runs this on Linux');
        }

        $r = $this->apply($ws, $this->stubbedTools($ws));

        $this->assertSame(0, $r['code'], $r['all']);
        $this->assertSame('new version', $this->appFile($ws), 'the files were still copied');
        $this->assertSame('750', trim($this->bashSnippet("stat -c %a '$dir'")['out']), 'an existing folder must not be chmod-ed by the copy');
        $this->assertStringNotContainsString('Cannot change mode', $r['all']);
    }

    /** The mode test above needs Unix permissions and is skipped on Windows, so the cause itself is also pinned textually. */
    public function test_no_script_extracts_with_permission_preserving_flags_over_existing_folders(): void
    {
        foreach (['apply-release.sh', 'rollback-code.sh'] as $name) {
            $script = (string) file_get_contents(base_path('scripts/deploy/server/' . $name));
            $extractions = array_filter(explode("\n", $script), fn ($l) => preg_match('/\btar\b[^|#]*\s-(?:[a-z]*x[a-z]*)\b/', $l) && !str_starts_with(ltrim($l), '#') && !str_contains($l, 'tar -xzOf') && !str_contains($l, 'verify'));
            $this->assertNotEmpty($extractions, $name . ' extracts something');
            foreach ($extractions as $line) {
                if (str_contains($line, '-C "$STAGE"')) {
                    continue;   // unpacking into the private staging folder (created by this script) is not the live application
                }
                $this->assertStringContainsString('--no-overwrite-dir', $line, $name . ': ' . trim($line));
                $this->assertStringContainsString('--no-same-permissions', $line, $name . ': ' . trim($line));
                $this->assertDoesNotMatchRegularExpression('/\s-[a-z]*p[a-z]*\s|--same-permissions|--preserve-permissions/', $line, $name . ': ' . trim($line));
            }
        }
    }

    public function test_the_laravel_runtime_folders_are_created_when_the_server_lacks_them(): void
    {
        $ws = $this->stagingWorkspace();   // has storage/framework but none of cache/data, sessions, views
        $this->assertDirectoryDoesNotExist($ws['app'] . '/storage/framework/cache/data');

        $r = $this->apply($ws, $this->stubbedTools($ws));

        $this->assertSame(0, $r['code'], $r['all']);
        foreach (['storage/framework/cache/data', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs', 'bootstrap/cache'] as $d) {
            $this->assertDirectoryExists($ws['app'] . '/' . $d, $d);
        }
    }

    public function test_a_cache_folder_this_account_cannot_write_stops_the_deploy_before_anything_changes(): void
    {
        $ws = $this->stagingWorkspace();
        $bad = $this->posix($ws['app'] . '/storage/framework/cache/data/1b/f0');
        $this->assertSame(0, $this->bashSnippet("mkdir -p '$bad' && chmod 555 '$bad'")['code']);
        if ($this->bashSnippet("[ -w '$bad' ]")['code'] === 0) {
            $this->bashSnippet("chmod 755 '$bad'");
            $this->markTestSkipped('this file system cannot make a folder read-only for its owner here; the pipeline runs this on Linux');
        }
        $before = $this->treeOf($ws['app']);

        $r = $this->apply($ws, $this->stubbedTools($ws));
        $this->bashSnippet("chmod 755 '$bad'");   // so the scratch folder can be removed

        $this->assertNotSame(0, $r['code'], $r['all']);
        $this->assertStringContainsString('not writable by', $r['all']);
        $this->assertStringContainsString('chown -R', $r['all'], 'it must say how to repair it');
        $this->assertSame($before, $this->treeOf($ws['app']), 'nothing may have changed');
        $this->assertFalse($this->siteIsInMaintenance($ws));
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
