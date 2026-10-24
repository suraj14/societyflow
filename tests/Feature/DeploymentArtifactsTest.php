<?php

namespace Tests\Feature;

use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

/**
 * The automatic-deployment files are code that can damage a live site, so their safety properties are pinned here: what may trigger a
 * production deploy, that host keys are verified, that no destructive artisan command or secret appears, that migrations stay a human
 * decision, and that every setting the scripts need is documented. These read files only; nothing is executed against any server.
 */
class DeploymentArtifactsTest extends TestCase
{
    private function root(string $path = ''): string
    {
        return base_path($path);
    }

    private function pipeline(): array
    {
        return Yaml::parseFile($this->root('bitbucket-pipelines.yml'));
    }

    /** @return list<string> shell scripts of the deployment tooling */
    private function scripts(): array
    {
        return array_merge(glob($this->root('scripts/deploy/server/*.sh')), glob($this->root('scripts/deploy/ci/*.sh')));
    }

    /** the executable lines of a script: comments and blank lines removed */
    private function code(string $file): string
    {
        $lines = array_filter(file($file, FILE_IGNORE_NEW_LINES), fn ($l) => !preg_match('/^\s*(#|$)/', $l));

        return implode("\n", $lines);
    }

    public function test_only_the_designated_branch_can_deploy_and_only_by_a_manual_step(): void
    {
        $p = $this->pipeline()['pipelines'];

        $deploying = fn (array $steps) => array_filter($steps, fn ($s) => isset($s['step']['deployment']));
        $this->assertSame([], $deploying($p['default']), 'the default pipeline (any other branch) must not deploy');
        foreach ($p['pull-requests'] as $steps) {
            $this->assertSame([], $deploying($steps), 'a pull request must never deploy');
        }
        $this->assertSame(['production-readiness'], array_keys($p['branches']), 'exactly one branch is wired to production');
        $step = array_values($deploying($p['branches']['production-readiness']))[0]['step'];
        $this->assertSame('production', $step['deployment']);
        $this->assertSame('manual', $step['trigger'], 'production stays a manual button until the setup checklist is signed off');
        $this->assertContains('deploy.sh', array_map(fn ($l) => basename(explode(' ', trim($l))[1] ?? ''), array_filter($step['script'], fn ($l) => str_contains($l, 'deploy.sh'))));
    }

    public function test_tests_run_before_a_package_is_built_and_before_any_deploy(): void
    {
        $steps = $this->pipeline()['pipelines']['branches']['production-readiness'];
        $names = array_map(fn ($s) => $s['step']['name'], $steps);

        $this->assertStringContainsString('test', strtolower($names[0]));
        $this->assertStringContainsString('package', strtolower($names[1]));
        $this->assertStringContainsString('deploy', strtolower($names[2]));
        $this->assertStringContainsString('phpunit', implode("\n", $steps[0]['step']['script']));
    }

    public function test_host_keys_are_verified_and_no_secret_or_key_is_written_into_the_files(): void
    {
        $everything = file_get_contents($this->root('bitbucket-pipelines.yml'));
        foreach ($this->scripts() as $file) {
            $everything .= "\n" . file_get_contents($file);
        }

        $this->assertStringNotContainsString('StrictHostKeyChecking=no', $everything);
        $this->assertStringNotContainsString('UserKnownHostsFile=/dev/null', $everything);
        $this->assertStringContainsString('StrictHostKeyChecking=yes', file_get_contents($this->root('scripts/deploy/ci/deploy.sh')));
        $this->assertDoesNotMatchRegularExpression('/-----BEGIN [A-Z ]*PRIVATE KEY-----/', $everything);
        $this->assertDoesNotMatchRegularExpression('/\b(?:password|passwd|secret|token)\s*=\s*[\'"][^\'"$]{8,}[\'"]/i', $this->code($this->root('scripts/deploy/ci/deploy.sh')), 'no literal credential in the deploy script');
    }

    public function test_no_script_runs_a_destructive_artisan_or_database_command(): void
    {
        foreach ($this->scripts() as $file) {
            $code = $this->code($file);
            $this->assertDoesNotMatchRegularExpression('/\b(?:art|artisan)\s+(?:migrate:(?:fresh|refresh|reset|rollback)|db:wipe|db:seed|storage:setup)\b/', $code, basename($file) . ' runs a command that must never run on production');
            $this->assertDoesNotMatchRegularExpression('/\b(?:DROP|TRUNCATE)\s+(?:DATABASE|TABLE)\b/i', $code, basename($file));
            $this->assertDoesNotMatchRegularExpression('/\brm\s+-[a-z]*r[a-z]*f?\s+["\']?(?:\$APP_DIR|\$\{APP_DIR\}|\/home)\b/', $code, basename($file) . ' deletes under the application or home directory');
            $this->assertDoesNotMatchRegularExpression('/rsync\b[^\n]*--delete/', $code, basename($file));
        }
    }

    public function test_migrations_only_run_after_a_typed_confirmation_and_never_in_the_automatic_mode(): void
    {
        $apply = file_get_contents($this->root('scripts/deploy/server/apply-release.sh'));

        $this->assertMatchesRegularExpression('/confirm "Run these migrations[^\n]*"\s+"MIGRATE"\n\s*art migrate --force/', $apply, 'art migrate --force must directly follow the MIGRATE confirmation');
        $this->assertSame(1, preg_match_all('/art migrate --force/', $apply), 'one migrate --force, behind the confirmation');
        $this->assertStringContainsString('exit 3', $apply, 'a pending migration stops the non-interactive deploy with exit code 3');
        $this->assertMatchesRegularExpression('/PENDING_N" != 0[^\n]*\n(?:[^\n]*\n){0,4}\s*exit 3/', $apply);
        $this->assertStringContainsString('--non-interactive', $apply);
        $this->assertStringContainsString('--skip-db-backup is not allowed with --non-interactive', $apply);
        $this->assertStringContainsString('flock', $apply, 'two deployments must not run at once');
        $this->assertStringContainsString('rollback-code.sh', $apply, 'a failed smoke check restores the previous code');
    }

    public function test_the_package_builder_works_from_commits_with_a_closed_allow_list(): void
    {
        $build = file_get_contents($this->root('scripts/deploy/ci/build-release.sh'));

        $this->assertStringContainsString('git ls-files', $build, 'only committed files can be packaged');
        $this->assertStringContainsString('git show "HEAD:$f"', $build, 'file CONTENT comes from the commit, not the working tree');
        preg_match('/git ls-files -z -- ([^\\\\\n]+)/', $build, $m);
        $roots = preg_split('/\s+/', trim($m[1]));
        $this->assertEqualsCanonicalizing(['app', 'bootstrap/app.php', 'config', 'database/migrations', 'database/seeders', 'database/factories', 'lang', 'public', 'resources', 'routes', 'artisan', 'composer.json', 'composer.lock'], $roots);
        foreach (['.env', 'storage', 'vendor', 'tests', 'docs', 'societyflowmob', 'public/storage'] as $never) {
            $this->assertNotContains($never, $roots);
        }
        $this->assertStringContainsString('(Hash::make|bcrypt)', $build, 'seeders with hard-coded passwords are left out');
        $this->assertStringContainsString('BEGIN [A-Z ]*PRIVATE KEY', $build, 'the package is scanned for secrets');
    }

    public function test_the_deploy_script_demands_its_settings_and_the_guide_documents_every_one_of_them(): void
    {
        $deploy = file_get_contents($this->root('scripts/deploy/ci/deploy.sh'));
        $guide = file_get_contents($this->root('docs/BITBUCKET_PIPELINES_SETUP.md'));

        preg_match_all('/\bPROD_[A-Z_]+\b/', $deploy, $m);
        $variables = array_values(array_unique($m[0]));
        $this->assertGreaterThanOrEqual(6, count($variables));
        foreach ($variables as $name) {
            $this->assertStringContainsString($name, $guide, "the setup guide does not explain $name");
        }
        $this->assertStringContainsString('apply --non-interactive', str_replace(['--apply --non-interactive'], ['apply --non-interactive'], $deploy));
        $this->assertStringContainsString('dry-run', $deploy);
    }

    public function test_shell_scripts_have_unix_line_endings_and_git_keeps_them_that_way(): void
    {
        $attributes = file_get_contents($this->root('.gitattributes'));
        $this->assertStringContainsString('scripts/deploy/server/*.sh text eol=lf', $attributes);
        $this->assertStringContainsString('scripts/deploy/ci/*.sh text eol=lf', $attributes);

        foreach ($this->scripts() as $file) {
            $this->assertStringNotContainsString("\r", file_get_contents($file), basename($file) . ' has CRLF line endings; bash on the server would fail');
            $this->assertStringStartsWith('#!/usr/bin/env bash', file_get_contents($file));
        }
    }

    public function test_every_script_parses_with_bash(): void
    {
        $bash = trim((string) @shell_exec(PHP_OS_FAMILY === 'Windows' ? 'where bash 2>NUL' : 'command -v bash'));
        if ($bash === '') {
            $this->markTestSkipped('bash is not available here; the pipeline runs this on Linux');
        }
        foreach ($this->scripts() as $file) {
            $out = shell_exec('bash -n ' . escapeshellarg($file) . ' 2>&1');
            $this->assertSame('', trim((string) $out), basename($file) . ": $out");
        }
    }
}
