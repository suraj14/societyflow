<?php

namespace Tests\Concerns;

/**
 * Runs the deployment shell scripts (scripts/deploy) for real, so their safety rules are proven by behaviour and not only by reading the text.
 * Everything stays local: ssh and scp are replaced by recorders, the "live site" is a throw-away PHP built-in server on 127.0.0.1, and every
 * script starts from an empty environment (nothing of a real BITBUCKET_* / PROD_* setting can leak in). Skips cleanly where no suitable bash
 * exists; the Bitbucket pipeline runs these on Linux, a Windows developer needs Git for Windows.
 */
trait RunsDeploymentScripts
{
    private ?string $bash = null;

    /** @var list<string> */
    private array $scratchDirs = [];

    /** @var list<resource> */
    private array $stubProcesses = [];

    protected function requireBash(): void
    {
        $this->bash = $this->findBash();
        if ($this->bash === null) {
            $this->markTestSkipped('no usable bash here (Linux, or Git for Windows); the pipeline runs this on Linux');
        }
        foreach (['curl', 'sha256sum', 'tar', 'awk', 'sed', 'grep', 'mktemp'] as $tool) {
            if ($this->bashSnippet('command -v ' . $tool)['code'] !== 0) {
                $this->markTestSkipped("$tool is not available inside bash here");
            }
        }
    }

    private function findBash(): ?string
    {
        $candidates = [];
        if (PHP_OS_FAMILY !== 'Windows') {
            $candidates = preg_split('/\R/', trim($this->execute(['sh', '-c', 'command -v bash'])['out'])) ?: [];
        } else {
            // System32\bash.exe is the WSL launcher (a different filesystem view): never that one.
            foreach (preg_split('/\R/', trim($this->execute(['where', 'bash'])['out'])) ?: [] as $p) {
                if ($p !== '' && stripos($p, 'system32') === false) {
                    $candidates[] = $p;
                }
            }
            $git = trim((string) strtok($this->execute(['where', 'git'])['out'], "\r\n"));
            if ($git !== '') {
                $candidates[] = dirname($git, 2) . '\\bin\\bash.exe';
            }
            foreach (['ProgramFiles', 'ProgramW6432', 'LOCALAPPDATA'] as $env) {
                if ($base = getenv($env)) {
                    $candidates[] = $base . '\\Git\\bin\\bash.exe';
                }
            }
        }

        foreach ($candidates as $candidate) {
            if ($candidate !== '' && is_file($candidate)) {
                $kernel = trim($this->execute([$candidate, '-c', 'uname -s'])['out']);
                // Linux, or MSYS / MINGW (Git for Windows). WSL reports Linux too but cannot see Windows paths, so on Windows insist on MSYS.
                if (PHP_OS_FAMILY === 'Windows' ? preg_match('/^(MINGW|MSYS)/', $kernel) : $kernel !== '') {
                    return $candidate;
                }
            }
        }

        return null;
    }

    /** A path as bash understands it: unchanged on Linux, C:\x\y -> /c/x/y for Git for Windows. */
    protected function posix(string $path): string
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            return $path;
        }

        return preg_replace_callback('#^([A-Za-z]):/#', fn ($m) => '/' . strtolower($m[1]) . '/', str_replace('\\', '/', $path));
    }

    protected function scratchDir(string $label = 'sfdeploy'): string
    {
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $label . '-' . bin2hex(random_bytes(6));
        mkdir($dir, 0700, true);

        return $this->scratchDirs[] = $dir;
    }

    /**
     * @param list<string> $argv command and arguments
     * @return array{code:int, out:string, err:string}
     */
    protected function execute(array $argv, ?array $env = null, int $timeoutSeconds = 90, ?string $stdin = null): array
    {
        $dir = $this->scratchDir('sfrun');
        $outFile = $dir . DIRECTORY_SEPARATOR . 'out.txt';
        $errFile = $dir . DIRECTORY_SEPARATOR . 'err.txt';
        $proc = proc_open($argv, [0 => ['pipe', 'r'], 1 => ['file', $outFile, 'w'], 2 => ['file', $errFile, 'w']], $pipes, null, $env);
        $this->assertIsResource($proc, 'could not start ' . $argv[0]);
        if ($stdin !== null) {
            fwrite($pipes[0], $stdin);
        }
        fclose($pipes[0]);

        $deadline = microtime(true) + $timeoutSeconds;
        $code = -1;
        while (true) {
            $status = proc_get_status($proc);
            if (!$status['running']) {
                $code = $status['exitcode'];
                break;
            }
            if (microtime(true) > $deadline) {
                proc_terminate($proc);
                $this->fail('timed out after ' . $timeoutSeconds . 's: ' . implode(' ', array_slice($argv, 0, 4)));
            }
            usleep(50_000);
        }
        proc_close($proc);

        return ['code' => $code, 'out' => (string) @file_get_contents($outFile), 'err' => (string) @file_get_contents($errFile)];
    }

    /** @return array{code:int, out:string, err:string} */
    protected function bashSnippet(string $script): array
    {
        return $this->execute([$this->bash, '-c', $script]);
    }

    /**
     * Runs a script from an EMPTY environment (env -i): only PATH (the fake ssh/scp directory first), HOME and the variables given.
     *
     * @param list<string> $args
     * @param array<string,string> $env
     * @return array{code:int, out:string, err:string}
     */
    protected function runScript(string $script, array $args, array $env, string $fakeBinDir, ?string $stdin = null): array
    {
        $argv = [$this->bash, '-c', 'fake="$1"; shift; exec env -i PATH="$fake:$PATH" HOME="$fake" "$@"', 'bash', $this->posix($fakeBinDir)];
        foreach ($env as $name => $value) {
            $argv[] = $name . '=' . $value;
        }
        array_push($argv, 'bash', $this->posix($script), ...$args);

        return $this->execute($argv, null, 90, $stdin);
    }

    /** Script text with Unix line endings, whatever the checkout did to this PHP file (git on Windows may have made it CRLF). */
    protected function lf(string $text): string
    {
        return str_replace("\r\n", "\n", $text);
    }

    protected function skipWhenRoot(): void
    {
        if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
            $this->markTestSkipped('apply-release.sh refuses to run as root, on purpose; this runs on a developer machine');
        }
    }

    /**
     * A throw-away "live application" (the files the pre-flight and the code backup look for), a tiny release package for it, and stand-ins for
     * what cannot run here: a php wrapper (claims the extensions are loaded, plays `artisan` - down/up make and remove storage/framework/down,
     * every call is logged - and answers the migrations probe with "nothing pending", otherwise runs the real PHP) and a composer whose
     * outcome STUB_COMPOSER chooses (ok / fail / corrupt = fail after damaging the code backup).
     *
     * @return array{id:string, app:string, releases:string, backups:string, bin:string, package:string, pkgDir:string, php:string, logs:string}
     */
    protected function stagingWorkspace(string $id = '20261024-130000-aaaaaaaaaa'): array
    {
        $dir = $this->scratchDir();
        $app = $dir . DIRECTORY_SEPARATOR . 'live';
        foreach (['bootstrap', 'bootstrap/cache', 'public', 'vendor', 'storage', 'storage/logs', 'storage/framework', 'storage/app/public'] as $d) {
            mkdir($app . DIRECTORY_SEPARATOR . $d, 0700, true);
        }
        file_put_contents($app . '/artisan', "<?php\n");
        file_put_contents($app . '/.env', "APP_ENV=testing\nAPP_URL=http://127.0.0.1:9\n");
        file_put_contents($app . '/bootstrap/app.php', "<?php\n");
        file_put_contents($app . '/public/index.php', "<?php\n");
        file_put_contents($app . '/vendor/autoload.php', "<?php\n");
        file_put_contents($app . '/app-file.txt', "live version\n");

        $bin = $dir . DIRECTORY_SEPARATOR . 'bin';
        $logs = $dir . DIRECTORY_SEPARATOR . 'logs';
        mkdir($bin, 0700, true);
        mkdir($logs, 0700, true);
        file_put_contents($bin . '/php', $this->lf(<<<'SH'
#!/usr/bin/env bash
case "$1" in
  -m) printf '%s\n' ctype dom fileinfo filter gd hash iconv json libxml mbstring openssl pcre pdo_mysql session simplexml tokenizer xml xmlreader xmlwriter zip zlib curl;;
  artisan)
    shift
    echo "artisan $*" >> "$STUB_LOG_DIR/artisan-calls.log"
    case "$1" in
      down) mkdir -p storage/framework; : > storage/framework/down;;
      up) rm -f storage/framework/down;;
      --version) echo "Laravel Framework (stub)";;
    esac
    exit 0;;
  -r) if printf '%s' "$2" | grep -q 'migrations'; then
        # the "which migrations are pending?" probe: STUB_MIGRATIONS = none (default) | pending (one is) | unreadable (the database cannot be read)
        case "${STUB_MIGRATIONS:-none}" in pending) echo "2026_10_24_000001_example_pending_migration";; unreadable) exit 4;; esac
        exit 0
      fi
      exec "__REAL_PHP__" "$@";;
  *) exec "__REAL_PHP__" "$@";;
esac
SH));
        file_put_contents($bin . '/php', str_replace('__REAL_PHP__', $this->posix(PHP_BINARY), (string) file_get_contents($bin . '/php')));
        file_put_contents($bin . '/composer', $this->lf(<<<'SH'
#!/usr/bin/env bash
case "${STUB_COMPOSER:-ok}" in
  ok) echo "stub composer: installed"; exit 0;;
  fail) echo "stub composer: Your lock file does not contain a compatible set of packages" >&2; exit 2;;
  exit3) echo "stub composer: fails with exit code 3" >&2; exit 3;;
  corrupt) for f in "$STUB_BACKUP_DIR"/code-*.tar.gz; do : > "$f"; done; echo "stub composer: failed, and the code backup got damaged" >&2; exit 2;;
esac
SH));
        $this->assertSame(0, $this->bashSnippet('chmod +x ' . escapeshellarg($this->posix($bin . '/php')) . ' ' . escapeshellarg($this->posix($bin . '/composer')))['code']);

        $workspace = ['id' => $id, 'app' => $app, 'releases' => $dir . DIRECTORY_SEPARATOR . 'releases', 'backups' => $dir . DIRECTORY_SEPARATOR . 'backups', 'bin' => $bin, 'logs' => $logs,
            'package' => $dir . DIRECTORY_SEPARATOR . 'incoming' . DIRECTORY_SEPARATOR . "societyflow-$id.tar.gz", 'pkgDir' => $dir . DIRECTORY_SEPARATOR . 'pkg', 'php' => $bin . '/php'];
        $this->buildPackage($workspace, 'new version');

        return $workspace;
    }

    /** (Re)builds the package in $ws['package'] with the given content for app-file.txt, in the shape scripts/deploy/ci/build-release.sh makes. */
    protected function buildPackage(array $ws, string $content): void
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
        // member names WITHOUT a leading ./ and RELEASE.sha256 hashing every other file - the shape build-release.sh produces
        $r = $this->bashSnippet(
            "cd $pkg && find . -type f ! -name RELEASE.sha256 | sed 's|^\\./||' | LC_ALL=C sort | while IFS= read -r p; do sha256sum -- \"\$p\"; done > RELEASE.sha256"
            . " && find . -type f | sed 's|^\\./||' | LC_ALL=C sort > $list && tar -czf $out -C $pkg -T $list"
            . " && cd $incoming && sha256sum " . escapeshellarg(basename($ws['package'])) . ' > ' . escapeshellarg(basename($ws['package']) . '.sha256'),
        );
        $this->assertSame(0, $r['code'], $r['err']);
    }

    /** a fingerprint of everything under a folder (path + content hash of every file) */
    protected function treeOf(string $dir): string
    {
        $lines = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)) as $file) {
            $lines[] = str_replace('\\', '/', substr($file->getPathname(), strlen($dir))) . ' ' . sha1_file($file->getPathname());
        }
        sort($lines);

        return implode("\n", $lines);
    }

    /** Fake ssh and scp: each call is appended to the returned log file and fails, so a run never reaches a server. */
    protected function fakeTransport(string $dir): array
    {
        $bin = $dir . DIRECTORY_SEPARATOR . 'bin';
        $log = $dir . DIRECTORY_SEPARATOR . 'calls.log';
        $result = $this->bashSnippet(sprintf(
            'mkdir -p %1$s && : > %2$s && for t in ssh scp; do printf \'#!/usr/bin/env bash\\necho "%%s $*" >> %2$s\\nexit 1\\n\' "$t" > %1$s/$t; chmod +x %1$s/$t; done',
            escapeshellarg($this->posix($bin)),
            escapeshellarg($this->posix($log)),
        ));
        $this->assertSame(0, $result['code'], 'could not create the fake ssh/scp: ' . $result['err']);

        return [$bin, $log];
    }

    /**
     * A stand-in for the live site's public endpoints, served by PHP's built-in server. Sends X-SocietyFlow-Release: $tag on the health
     * answer ('' = never) from the $from-th health request on.
     */
    protected function startStubSite(string $tag, int $from = 1): string
    {
        $dir = $this->scratchDir('sfstub');
        $router = $dir . DIRECTORY_SEPARATOR . 'router.php';
        $counter = $dir . DIRECTORY_SEPARATOR . 'counter.txt';
        file_put_contents($counter, '0');
        file_put_contents($router, <<<'PHP'
<?php
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
switch ($path) {
    case '/':
    case '/owners':
        header('Location: /login', true, 302);
        return true;
    case '/login':
        echo '<html><body><form method="post"><input name="password"></form></body></html>';
        return true;
    case '/api/v1/health':
        $counterFile = getenv('STUB_COUNTER');
        $n = (int) @file_get_contents($counterFile) + 1;
        file_put_contents($counterFile, (string) $n);
        header('Content-Type: application/json');
        $tag = getenv('STUB_TAG');
        if ($tag !== false && $tag !== '' && $n >= (int) getenv('STUB_TAG_FROM')) {
            header('X-SocietyFlow-Release: ' . $tag);
        }
        echo '{"application":"societyflow","api":"v1","status":"ok"}';
        return true;
}
http_response_code(404);
echo 'not found';
PHP);

        $probe = stream_socket_server('tcp://127.0.0.1:0');
        $port = (int) substr(strrchr((string) stream_socket_get_name($probe, false), ':'), 1);
        fclose($probe);

        $env = array_merge(getenv(), ['STUB_TAG' => $tag, 'STUB_TAG_FROM' => (string) $from, 'STUB_COUNTER' => $counter]);
        $proc = proc_open([PHP_BINARY, '-S', "127.0.0.1:$port", $router], [0 => ['pipe', 'r'], 1 => ['file', $dir . DIRECTORY_SEPARATOR . 'srv.out', 'w'], 2 => ['file', $dir . DIRECTORY_SEPARATOR . 'srv.err', 'w']], $pipes, null, $env);
        $this->assertIsResource($proc, 'could not start the stub site');
        fclose($pipes[0]);
        $this->stubProcesses[] = $proc;

        for ($i = 0; $i < 50; $i++) {
            $socket = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.2);
            if ($socket) {
                fclose($socket);

                return "http://127.0.0.1:$port";
            }
            usleep(100_000);
        }
        $this->fail('the stub site did not start listening');
    }

    /** Stops the stub site(s) and deletes the scratch folders; call from tearDown(). */
    protected function cleanUpDeploymentScripts(): void
    {
        foreach ($this->stubProcesses as $proc) {
            if (is_resource($proc)) {
                proc_terminate($proc);
                proc_close($proc);
            }
        }
        $this->stubProcesses = [];

        foreach ($this->scratchDirs as $dir) {
            if (is_dir($dir)) {
                $items = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
                foreach ($items as $item) {
                    $item->isDir() && !$item->isLink() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
                }
                @rmdir($dir);
            }
        }
        $this->scratchDirs = [];
    }
}
