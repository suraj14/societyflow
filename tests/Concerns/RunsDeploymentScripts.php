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
    protected function execute(array $argv, ?array $env = null, int $timeoutSeconds = 90): array
    {
        $dir = $this->scratchDir('sfrun');
        $outFile = $dir . DIRECTORY_SEPARATOR . 'out.txt';
        $errFile = $dir . DIRECTORY_SEPARATOR . 'err.txt';
        $proc = proc_open($argv, [0 => ['pipe', 'r'], 1 => ['file', $outFile, 'w'], 2 => ['file', $errFile, 'w']], $pipes, null, $env);
        $this->assertIsResource($proc, 'could not start ' . $argv[0]);
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
    protected function runScript(string $script, array $args, array $env, string $fakeBinDir): array
    {
        $argv = [$this->bash, '-c', 'fake="$1"; shift; exec env -i PATH="$fake:$PATH" HOME="$fake" "$@"', 'bash', $this->posix($fakeBinDir)];
        foreach ($env as $name => $value) {
            $argv[] = $name . '=' . $value;
        }
        array_push($argv, 'bash', $this->posix($script), ...$args);

        return $this->execute($argv);
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
