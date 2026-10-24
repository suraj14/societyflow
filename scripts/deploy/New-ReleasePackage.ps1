<#
    New-ReleasePackage.ps1 - build a deployable SocietyFlow release package from the LOCAL working tree.

    LOCAL ONLY. It never connects to a server, never uploads, never deletes anything outside its own output folder, and never touches the
    project files, .env, databases or vendor/. It copies an explicit allow-list of application files into a staging folder, validates the
    result, and only then writes the archive and marks the package ready.

        powershell -ExecutionPolicy Bypass -File scripts\deploy\New-ReleasePackage.ps1
        powershell -ExecutionPolicy Bypass -File scripts\deploy\New-ReleasePackage.ps1 -Label hotfix -OutDir D:\releases

    Output: <OutDir>\<release-id>\
        societyflow-<release-id>.tar.gz        the package (application code only; no vendor/, no .env, no storage/, no databases)
        societyflow-<release-id>.tar.gz.sha256 `sha256sum -c` compatible
        INCLUDED.txt / EXCLUDED.txt            exactly what is in and what was left out, and why
        MIGRATIONS-REVIEW.txt                  what each migration's up() does (additive / drops / data writes / raw SQL)
        VALIDATION.txt                         every check and its result
        server-tools\                          the scripts to run ON the server (LF line endings, with a sha256 list)
        READY.txt  or  NOT-READY.txt           the verdict. Do not upload a package that has NOT-READY.txt.

    Inside the package: RELEASE.txt (id, time, source commit) and RELEASE.sha256 (a hash of every file) so the server can prove the
    files on disk are exactly this release (`sha256sum -c RELEASE.sha256`).

    What is deliberately NOT shipped, and why: see EXCLUDED.txt and docs/DEPLOYMENT_CLOUDPANEL.md section 2. In short: the server keeps its
    own .env, storage/ (uploads, private documents, logs), public/storage, databases and vendor/ (installed there with composer).
#>
[CmdletBinding()]
param(
    [string]$OutDir = (Join-Path $env:USERPROFILE 'SocietyFlow-releases'),
    [string]$Label = '',
    [string]$PhpExe = '',
    [switch]$SkipLint,
    # Ship every seeder, including the demo / test ones that create accounts with hard-coded passwords. Not recommended.
    [switch]$KeepAllSeeders
)

$ErrorActionPreference = 'Stop'
$Repo = Split-Path -Parent (Split-Path -Parent $PSScriptRoot)
Set-Location $Repo

function Say($m)  { Write-Host $m }
function Step($m) { Write-Host "`n== $m" -ForegroundColor Cyan }

# ---------------------------------------------------------------- tools
if (-not $PhpExe) {
    $PhpExe = @((Get-Command php -ErrorAction SilentlyContinue).Source, 'G:\xampp\php\php.exe', 'C:\xampp\php\php.exe') | Where-Object { $_ -and (Test-Path $_) } | Select-Object -First 1
}
$tar = (Get-Command tar.exe -ErrorAction SilentlyContinue).Source
if (-not $tar) { throw 'tar.exe was not found (it ships with Windows 10 1803+ and Windows 11).' }
$gitExe = (Get-Command git -ErrorAction SilentlyContinue).Source

# ---------------------------------------------------------------- release id and source facts
$stamp = (Get-Date).ToUniversalTime().ToString('yyyyMMdd-HHmmss')
$sha = 'nogit'; $branch = '?'; $dirty = 0
if ($gitExe) {
    $sha = ((& git rev-parse --short HEAD 2>$null) | Select-Object -First 1)
    $branch = ((& git rev-parse --abbrev-ref HEAD 2>$null) | Select-Object -First 1)
    $dirty = @(& git status --porcelain 2>$null).Count
}
$releaseId = "$stamp-$sha" + $(if ($dirty -gt 0) { '-dirty' } else { '' }) + $(if ($Label) { '-' + ($Label -replace '[^A-Za-z0-9._-]', '') } else { '' })
$out = Join-Path $OutDir $releaseId
if (Test-Path $out) { throw "Output folder already exists: $out" }
$stage = Join-Path $env:TEMP "societyflow-stage-$releaseId"
if (Test-Path $stage) { throw "Staging folder already exists: $stage" }
New-Item -ItemType Directory -Force -Path $out, $stage | Out-Null

Say "Release id : $releaseId"
Say "Source     : $Repo  (branch $branch, HEAD $sha, $dirty uncommitted path(s) - the package is built from the WORKING TREE)"
Say "Output     : $out"
Say "PHP        : $PhpExe"

# ---------------------------------------------------------------- allow-list and exclusion rules
# Everything the application needs to run, and nothing else. Anything not named here is not shipped.
$Roots = @(
    @{ Path = 'app';                 Kind = 'dir'  },
    @{ Path = 'bootstrap/app.php';   Kind = 'file' },
    @{ Path = 'config';              Kind = 'dir'  },
    @{ Path = 'database/migrations'; Kind = 'dir'  },
    @{ Path = 'database/seeders';    Kind = 'dir'  },
    @{ Path = 'database/factories';  Kind = 'dir'; Optional = $true },
    @{ Path = 'lang';                Kind = 'dir'; Optional = $true },
    @{ Path = 'public';              Kind = 'dir'  },
    @{ Path = 'resources';           Kind = 'dir'  },
    @{ Path = 'routes';              Kind = 'dir'  },
    @{ Path = 'artisan';             Kind = 'file' },
    @{ Path = 'composer.json';       Kind = 'file' },
    @{ Path = 'composer.lock';       Kind = 'file' }
)

# Why each top-level entry of the project is not shipped (printed in EXCLUDED.txt; the list is checked against the real folder).
$RootReasons = [ordered]@{
    '.env'                  = 'secrets - the server keeps its own .env'
    '.env.example'          = 'not needed at runtime (template for building a .env by hand)'
    '.env.mysql.template'   = 'not needed at runtime'
    'vendor'                = 'installed on the server with `composer install --no-dev` (the local vendor/ holds dev packages)'
    'storage'               = 'uploads, private documents, logs, sessions, cache and local backups live on the server and must never be overwritten'
    'bootstrap/cache'       = 'generated by artisan on the server (config/route/view caches); the directory already exists there'
    'database/*.sqlite*'    = 'local development databases (real PII) - never shipped'
    'societyflowmob'        = 'the Flutter app (3 GB of build output) - built and released separately'
    'tests'                 = 'development only'
    'docs'                  = 'development documentation'
    'scripts'               = 'developer scripts (the server tools are copied to server-tools\ instead)'
    '.git'                  = 'repository history'
    '.gitignore'            = 'repository metadata'
    '.gitattributes'        = 'repository metadata'
    '.github'               = 'development only'
    '.claude'               = 'development tooling'
    'node_modules'          = 'not used (no front-end build; the layouts load Tailwind/Alpine from CDNs)'
    'composer.phar'         = 'tool, not application code; the server uses its own composer'
    'phpunit.xml'           = 'development only'
    '.phpunit.result.cache' = 'development only'
    '.htaccess'             = 'Apache rule for the project root; CloudPanel serves with Nginx from public/'
    '*.bat, *.ps1 (root)'   = 'local Windows start scripts'
    '*.md, *.txt (root)'    = 'development notes'
    'public/storage'        = 'symlink to storage/app/public on the server: user uploads - never overwritten or followed'
    'public/web.config'     = 'IIS rule; not used on Nginx'
    'public/hot'            = 'dev-server marker'
}

$ExcludedDirNames  = @('.git', '.github', '.idea', '.vscode', '.claude', 'node_modules', 'vendor', 'tests', 'docs', 'scripts', 'societyflowmob', 'storage', 'backups', '.phpunit.cache')
$ExcludedFileRegex = '(?i)^(\.env(\..*)?|thumbs\.db|\.ds_store|\.phpunit\.result\.cache|web\.config|hot|id_(rsa|dsa|ecdsa|ed25519)(\.pub)?)$|\.(sqlite|sqlite3|sqlite-journal|db|sql|gz|tgz|zip|tar|rar|7z|bak|log|tmp|temp|pem|key|crt|csr|p12|pfx|jks|keystore|ppk|apk|aab|orig|rej|swp)$'
$PasswordLiteral   = [regex]'(Hash::make|bcrypt)\(\s*[''"]'
# Seeders that exist to load demo / sample / test data. They do not belong on a production disk even when they generate their passwords.
# (The default `db:seed` path - RolesAndPermissions, SystemSettings, Services, SimpleTwoAdmin - matches none of these.)
$DemoSeederRegex   = [regex]'(?i)(test|demo|sample|dummy|quick|realistic|comprehensive)'

# True when a seeder CODE line (not a comment that merely mentions the old behaviour) hashes a literal password.
function Test-PasswordLiteral([string]$text) {
    foreach ($line in ($text -split "`n")) {
        $t = $line.TrimStart()
        if ($t.StartsWith('//') -or $t.StartsWith('*') -or $t.StartsWith('/*') -or $t.StartsWith('#')) { continue }
        if ($PasswordLiteral.IsMatch($line)) { return $true }
    }
    return $false
}

$Files    = New-Object System.Collections.Generic.List[object]
$Excluded = New-Object System.Collections.Generic.List[object]

function Count-Files($abs) { try { @(Get-ChildItem -LiteralPath $abs -Recurse -File -Force -ErrorAction SilentlyContinue).Count } catch { 0 } }

function Walk([string]$abs, [string]$rel) {
    foreach ($e in (Get-ChildItem -LiteralPath $abs -Force)) {
        $r = "$rel/$($e.Name)"
        if ($e.Attributes -band [IO.FileAttributes]::ReparsePoint) {
            $Excluded.Add([pscustomobject]@{ Path = $r; Reason = 'symlink / junction - not followed (user uploads live behind it)'; Files = 0 }); continue
        }
        if ($e.PSIsContainer) {
            if ($ExcludedDirNames -contains $e.Name) {
                $Excluded.Add([pscustomobject]@{ Path = "$r/"; Reason = 'development / runtime directory'; Files = (Count-Files $e.FullName) }); continue
            }
            Walk $e.FullName $r
            continue
        }
        if ($e.Name -match $ExcludedFileRegex) {
            $Excluded.Add([pscustomobject]@{ Path = $r; Reason = 'secret / database / archive / log / key file pattern'; Files = 1 }); continue
        }
        if (-not $KeepAllSeeders -and $r -like 'database/seeders/*.php') {
            $text = [IO.File]::ReadAllText($e.FullName)
            if (Test-PasswordLiteral $text) {
                $Excluded.Add([pscustomobject]@{ Path = $r; Reason = 'seeder creates an account with a HARD-CODED password (demo / test data) - must not exist on a production disk'; Files = 1 }); continue
            }
            if ($DemoSeederRegex.IsMatch([IO.Path]::GetFileNameWithoutExtension($e.Name))) {
                $Excluded.Add([pscustomobject]@{ Path = $r; Reason = 'demo / sample / test-data seeder - not for production (use -KeepAllSeeders to ship it anyway)'; Files = 1 }); continue
            }
        }
        $Files.Add([pscustomobject]@{ Rel = $r; Abs = $e.FullName; Size = $e.Length })
    }
}

Step '1. Collect files from the allow-list'
foreach ($root in $Roots) {
    $abs = Join-Path $Repo ($root.Path -replace '/', '\')
    if (-not (Test-Path -LiteralPath $abs)) {
        if ($root.Optional) { Say "   (absent, optional) $($root.Path)"; continue }
        throw "Required path is missing: $($root.Path)"
    }
    if ($root.Kind -eq 'file') {
        $i = Get-Item -LiteralPath $abs
        $Files.Add([pscustomobject]@{ Rel = $root.Path; Abs = $i.FullName; Size = $i.Length })
    } else {
        Walk $abs $root.Path
    }
}
$Files = @($Files | Sort-Object Rel)
Say "   $($Files.Count) files selected, $([math]::Round((($Files | Measure-Object Size -Sum).Sum) / 1MB, 2)) MB"

# What the project root holds that is not shipped (checked against the real folder so the list cannot go stale unnoticed)
$rootNotShipped = New-Object System.Collections.Generic.List[object]
$shippedTop = @($Roots | ForEach-Object { ($_.Path -split '/')[0] } | Select-Object -Unique)
foreach ($e in (Get-ChildItem -LiteralPath $Repo -Force)) {
    if ($shippedTop -contains $e.Name) { continue }
    $reason = $RootReasons[$e.Name]
    if (-not $reason -and $e.Name -match '(?i)\.(bat|ps1)$') { $reason = $RootReasons['*.bat, *.ps1 (root)'] }
    if (-not $reason -and $e.Name -match '(?i)\.(md|txt)$') { $reason = $RootReasons['*.md, *.txt (root)'] }
    if (-not $reason -and $e.Name -match '(?i)^\.env') { $reason = 'secrets / environment template' }
    if (-not $reason) { $reason = 'NOT IN THE ALLOW-LIST (review: add it to $Roots if the application needs it)' }
    $rootNotShipped.Add([pscustomobject]@{ Path = $e.Name + $(if ($e.PSIsContainer) { '/' } else { '' }); Reason = $reason; Files = $(if ($e.PSIsContainer) { Count-Files $e.FullName } else { 1 }) })
}
foreach ($p in @('bootstrap/cache', 'database/*.sqlite*', 'public/storage', 'public/web.config')) {
    $rootNotShipped.Add([pscustomobject]@{ Path = $p; Reason = $RootReasons[$p]; Files = '-' })
}

# ---------------------------------------------------------------- stage
Step '2. Copy to the staging folder (byte for byte; line endings are not changed)'
foreach ($f in $Files) {
    $dest = Join-Path $stage ($f.Rel -replace '/', '\')
    $dir = Split-Path -Parent $dest
    if (-not (Test-Path -LiteralPath $dir)) { New-Item -ItemType Directory -Force -Path $dir | Out-Null }
    [IO.File]::Copy($f.Abs, $dest, $false)
}

$composerLockHash = (Get-FileHash -LiteralPath (Join-Path $Repo 'composer.lock') -Algorithm SHA256).Hash.ToLower()
$phpVer = if ($PhpExe) { ((& $PhpExe -r 'echo PHP_VERSION;') 2>$null) } else { '?' }
$releaseTxt = @(
    "release_id=$releaseId",
    "built_utc=$((Get-Date).ToUniversalTime().ToString('yyyy-MM-dd HH:mm:ss'))",
    "source_branch=$branch",
    "source_head=$sha",
    "uncommitted_paths_at_build=$dirty",
    "composer_lock_sha256=$composerLockHash",
    "built_with_php=$phpVer",
    "files=$($Files.Count)",
    'note=built from the working tree; storage/, .env, vendor/ and databases are NOT part of this package'
) -join "`n"
[IO.File]::WriteAllText((Join-Path $stage 'RELEASE.txt'), $releaseTxt + "`n", (New-Object Text.UTF8Encoding($false)))

$allStaged = @(Get-ChildItem -LiteralPath $stage -Recurse -File -Force)
$hashLines = foreach ($f in ($allStaged | Sort-Object { $_.FullName.Substring($stage.Length + 1) })) {
    $rel = $f.FullName.Substring($stage.Length + 1).Replace('\', '/')
    '{0}  {1}' -f (Get-FileHash -LiteralPath $f.FullName -Algorithm SHA256).Hash.ToLower(), $rel
}
[IO.File]::WriteAllText((Join-Path $stage 'RELEASE.sha256'), (($hashLines -join "`n") + "`n"), (New-Object Text.UTF8Encoding($false)))
Say "   staged $($allStaged.Count + 1) files (including RELEASE.txt and RELEASE.sha256)"

# ---------------------------------------------------------------- validate the staged tree
$results = New-Object System.Collections.Generic.List[object]
function Check([string]$name, [bool]$ok, [string]$detail = '') { $results.Add([pscustomobject]@{ Name = $name; Ok = $ok; Detail = $detail }); Say ('   {0,-5} {1}  {2}' -f $(if ($ok) { 'PASS' } else { 'FAIL' }), $name, $detail) }
function Warn([string]$name, [string]$detail) { $results.Add([pscustomobject]@{ Name = $name; Ok = $true; Detail = $detail; Warn = $true }); Write-Host "   WARN  $name  $detail" -ForegroundColor Yellow }

Step '3. Validate the staged tree'
$stagedRel = @(Get-ChildItem -LiteralPath $stage -Recurse -File -Force | ForEach-Object { $_.FullName.Substring($stage.Length + 1).Replace('\', '/') })

$required = @('artisan', 'composer.json', 'composer.lock', 'bootstrap/app.php', 'public/index.php', 'routes/web.php', 'routes/api.php', 'app/Http/Kernel.php', 'app/Providers/RouteServiceProvider.php', 'config/app.php', 'config/database.php', 'resources/views/layouts/app.blade.php', 'RELEASE.txt', 'RELEASE.sha256')
$missing = @($required | Where-Object { $stagedRel -notcontains $_ })
Check 'required application files present' ($missing.Count -eq 0) $(if ($missing.Count) { 'missing: ' + ($missing -join ', ') })
Check 'migrations present' (@($stagedRel | Where-Object { $_ -like 'database/migrations/*.php' }).Count -gt 0) ("$(@($stagedRel | Where-Object { $_ -like 'database/migrations/*.php' }).Count) files")

$forbidden = @($stagedRel | Where-Object { $_ -match '(?i)(^|/)\.env(\.|$)' -or $_ -match '(?i)\.(sqlite|sqlite3|db|sql|log|pem|key|p12|pfx|jks|keystore|ppk|apk|aab|bak)$' -or $_ -match '(?i)^(storage|vendor|node_modules|tests|docs|scripts|societyflowmob|\.git|bootstrap/cache)/' -or $_ -match '(?i)^public/storage(/|$)' -or $_ -match '(?i)^database/[^/]+\.(sqlite|db)' })
Check 'no secrets, databases, storage, vendor, tests or uploads in the package' ($forbidden.Count -eq 0) $(if ($forbidden.Count) { 'FOUND: ' + (($forbidden | Select-Object -First 10) -join ', ') })

$hashMismatch = 0
foreach ($f in $Files) { $a = (Get-FileHash -LiteralPath $f.Abs -Algorithm SHA256).Hash; $b = (Get-FileHash -LiteralPath (Join-Path $stage ($f.Rel -replace '/', '\')) -Algorithm SHA256).Hash; if ($a -ne $b) { $hashMismatch++ } }
Check 'every staged file is byte-identical to its source' ($hashMismatch -eq 0) "$($Files.Count) compared"
Check 'staged file count matches the manifest' ($stagedRel.Count -eq ($Files.Count + 2)) "$($stagedRel.Count) staged = $($Files.Count) selected + RELEASE.txt + RELEASE.sha256"

if (-not $KeepAllSeeders) {
    $literalSeeders = @($stagedRel | Where-Object { $_ -like 'database/seeders/*.php' } | Where-Object { Test-PasswordLiteral ([IO.File]::ReadAllText((Join-Path $stage ($_ -replace '/', '\')))) })
    Check 'no seeder with a hard-coded password is shipped' ($literalSeeders.Count -eq 0) $(if ($literalSeeders.Count) { ($literalSeeders -join ', ') })
}

# Seeders / classes that shipped code names but that were left out
$excludedSeederClasses = @($Excluded | Where-Object { $_.Path -like 'database/seeders/*.php' } | ForEach-Object { [IO.Path]::GetFileNameWithoutExtension($_.Path) })
if ($excludedSeederClasses.Count) {
    $refs = @()
    foreach ($rel in @($stagedRel | Where-Object { $_ -like 'database/*.php' -or $_ -like 'app/*.php' -or $_ -like 'routes/*.php' })) {
        $text = [IO.File]::ReadAllText((Join-Path $stage ($rel -replace '/', '\')))
        foreach ($c in $excludedSeederClasses) { if ($text -match "\b$([regex]::Escape($c))\b") { $refs += "$rel -> $c" } }
    }
    if ($refs.Count) { Warn 'shipped files mention a seeder that was left out (fine if only in a comment)' (($refs | Select-Object -First 6) -join '; ') }
    else { Check 'no shipped file refers to a seeder that was left out' $true }
}

# Secret scan of everything that ships (values are never printed - only file:line and the kind of match)
$secretPatterns = @(
    @{ Name = 'private key block';        Re = '-----BEGIN [A-Z ]*PRIVATE KEY-----'; Blocking = $true },
    # AKIAIOSFODNN7EXAMPLE is the placeholder AWS itself prints in its documentation (used as input placeholder text in the storage settings form)
    @{ Name = 'AWS access key id';        Re = '\bAKIA(?!IOSFODNN7EXAMPLE\b)[0-9A-Z]{16}\b'; Blocking = $true },
    @{ Name = 'APP_KEY value';            Re = 'APP_KEY\s*=\s*base64:[A-Za-z0-9+/=]{20,}'; Blocking = $true },
    @{ Name = 'Google API key';           Re = '\bAIza[0-9A-Za-z_\-]{35}\b'; Blocking = $true },
    @{ Name = 'GitHub / Slack token';     Re = '\b(ghp_[A-Za-z0-9]{36}|xox[baprs]-[A-Za-z0-9-]{10,})\b'; Blocking = $true },
    @{ Name = 'DB_PASSWORD assignment';   Re = '(?m)^\s*DB_PASSWORD\s*=\s*\S+'; Blocking = $true },
    @{ Name = 'long literal secret';      Re = '(?i)\b(api[_-]?key|secret|token|passwd|password)\b[''"]?\s*(=>|=|:)\s*[''"][A-Za-z0-9_\-/+=]{28,}[''"]'; Blocking = $false }
)
$textExt = '(?i)\.(php|json|md|txt|js|css|xml|yml|yaml|ini|html|htm|env|sh|conf|config|svg)$'
$blockingHits = @(); $softHits = @()
foreach ($rel in ($stagedRel | Where-Object { $_ -match $textExt })) {
    $abs = Join-Path $stage ($rel -replace '/', '\')
    if ((Get-Item -LiteralPath $abs).Length -gt 3MB) { continue }
    $lines = [IO.File]::ReadAllLines($abs)
    for ($n = 0; $n -lt $lines.Length; $n++) {
        foreach ($p in $secretPatterns) {
            if ($lines[$n] -match $p.Re) {
                $hit = "${rel}:$($n + 1) [$($p.Name)]"
                if ($p.Blocking) { $blockingHits += $hit } else { $softHits += $hit }
            }
        }
    }
}
Check 'no private keys, cloud keys, APP_KEY or DB passwords in shipped files' ($blockingHits.Count -eq 0) $(if ($blockingHits.Count) { 'FOUND: ' + (($blockingHits | Select-Object -First 8) -join '; ') })
if ($softHits.Count) { Warn 'long literal that looks like a credential (review - value not printed)' (($softHits | Select-Object -First 8) -join '; ') }

# PHP syntax of every shipped PHP file (Blade templates are skipped: they compile at runtime and are covered by `artisan view:cache`)
if ($SkipLint) { Warn 'PHP syntax check skipped (-SkipLint)' 'run it before uploading' }
elseif (-not $PhpExe) { Warn 'PHP syntax check skipped' 'no php.exe found' }
else {
    $lintList = Join-Path $env:TEMP "sf-lint-$releaseId.txt"
    $lintScript = Join-Path $env:TEMP "sf-lint-$releaseId.php"
    ($stagedRel | Where-Object { $_ -like '*.php' -and $_ -notlike '*.blade.php' } | ForEach-Object { Join-Path $stage ($_ -replace '/', '\') }) | Set-Content -LiteralPath $lintList -Encoding ascii
    [IO.File]::WriteAllText($lintScript, @'
<?php
$bad = 0; $n = 0;
foreach (file($argv[1], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $f) {
    $n++;
    try { token_get_all(file_get_contents($f), TOKEN_PARSE); }
    catch (ParseError $e) { $bad++; echo "PARSE ERROR $f line {$e->getLine()}: {$e->getMessage()}\n"; }
}
echo "linted $n files, $bad with errors\n";
exit($bad ? 1 : 0);
'@)
    $lintOut = & $PhpExe $lintScript $lintList 2>&1
    $lintOk = ($LASTEXITCODE -eq 0)
    Check "every shipped PHP file parses (PHP $phpVer)" $lintOk (($lintOut | Select-Object -Last 3) -join ' | ')
    Remove-Item -LiteralPath $lintList, $lintScript -Force
}

# composer.json valid and composer.lock in step with it
if ($PhpExe -and (Test-Path (Join-Path $Repo 'composer.phar'))) {
    $cv = (& $PhpExe (Join-Path $Repo 'composer.phar') validate --no-check-publish --working-dir=$Repo 2>&1) -join "`n"
    Check 'composer.json is valid and composer.lock matches it' ($cv -match 'is valid' -and $cv -notmatch 'not up to date') (($cv -split "`n" | Select-Object -First 2) -join ' ')
}

# ---------------------------------------------------------------- migration review
Step '4. Migration review (static - reads each up(), runs nothing)'
function Get-UpBody([string]$text) {
    $m = [regex]::Match($text, 'function\s+up\s*\([^)]*\)\s*(:\s*\w+)?\s*\{')
    if (-not $m.Success) { return '' }
    $depth = 1; $i = $m.Index + $m.Length; $start = $i
    while ($i -lt $text.Length -and $depth -gt 0) { $c = $text[$i]; if ($c -eq '{') { $depth++ } elseif ($c -eq '}') { $depth-- }; $i++ }
    return $text.Substring($start, [Math]::Max(0, $i - $start - 1))
}
$flagRules = @(
    @{ Flag = 'DROPS/RENAMES SCHEMA'; Re = '(?i)dropColumn|dropColumns|dropIfExists|Schema::drop|->drop\(|dropForeign|dropUnique|dropIndex|dropPrimary|dropTimestamps|dropSoftDeletes|renameColumn|Schema::rename|->rename\(|->change\(\)' },
    @{ Flag = 'DELETES DATA';         Re = '(?i)->truncate\(|->delete\(\)|DELETE\s+FROM|TRUNCATE\s' },
    @{ Flag = 'WRITES/UPDATES DATA';  Re = '(?i)->update\(|->insert\(|updateOrInsert|updateOrCreate|::create\(|UPDATE\s+\w+\s+SET|INSERT\s+INTO' },
    @{ Flag = 'RAW SQL';              Re = 'DB::statement|DB::unprepared|DB::raw|DB::select|->getPdo\(\)' },
    @{ Flag = 'MYSQL/SQLITE BRANCH';  Re = "getDriverName\(\)|DB::getDriverName|Schema::getConnection" }
)
$review = New-Object System.Collections.Generic.List[string]
$review.Add("Static review of database/migrations/*.php in release $releaseId (up() only). A flag is a prompt to read the file, not a verdict.")
$review.Add('Compare with the PENDING list on the server (`php artisan migrate:status --pending`) - production may already have run some of these.')
$review.Add('Never run `migrate:rollback` on production: see docs/DEPLOYMENT_CLOUDPANEL.md section 8.')
$review.Add('')
$review.Add(('{0,-72} {1}' -f 'MIGRATION', 'WHAT up() DOES'))
$migFiles = @($stagedRel | Where-Object { $_ -like 'database/migrations/*.php' } | Sort-Object)
$flaggedDestructive = 0
foreach ($rel in $migFiles) {
    $text = [IO.File]::ReadAllText((Join-Path $stage ($rel -replace '/', '\')))
    $up = Get-UpBody $text
    $flags = @(); foreach ($r in $flagRules) { if ($up -match $r.Re) { $flags += $r.Flag } }
    if ($flags -contains 'DROPS/RENAMES SCHEMA' -or $flags -contains 'DELETES DATA') { $flaggedDestructive++ }
    $kind = if (-not $up) { '(no up() found - check)' } elseif ($flags.Count -eq 0) { 'creates / adds only' } else { $flags -join ' + ' }
    $review.Add(('{0,-72} {1}' -f ([IO.Path]::GetFileNameWithoutExtension($rel)), $kind))
}
$review.Add('')
$review.Add("$($migFiles.Count) migrations; $flaggedDestructive flagged as dropping/renaming schema or deleting data in up().")
[IO.File]::WriteAllText((Join-Path $out 'MIGRATIONS-REVIEW.txt'), ($review -join "`r`n") + "`r`n")
Say "   $($migFiles.Count) migrations reviewed; $flaggedDestructive with schema-removing or data-deleting operations in up()  -> MIGRATIONS-REVIEW.txt"
if ($flaggedDestructive -gt 0) { Warn 'some migrations drop/rename schema or delete data in up()' "$flaggedDestructive - read MIGRATIONS-REVIEW.txt and check which of them production has not run yet" }

# ---------------------------------------------------------------- server tools
Step '5. Server tools (LF line endings)'
$toolsSrc = Join-Path $Repo 'scripts\deploy\server'
$toolsOut = Join-Path $out 'server-tools'
New-Item -ItemType Directory -Force -Path $toolsOut | Out-Null
$toolHashes = @()
foreach ($t in (Get-ChildItem -LiteralPath $toolsSrc -Filter '*.sh' -ErrorAction SilentlyContinue | Sort-Object Name)) {
    $bytes = [IO.File]::ReadAllBytes($t.FullName)
    $text = [Text.Encoding]::UTF8.GetString($bytes).Replace("`r`n", "`n")
    [IO.File]::WriteAllText((Join-Path $toolsOut $t.Name), $text, (New-Object Text.UTF8Encoding($false)))
    $toolHashes += ('{0}  {1}' -f (Get-FileHash -LiteralPath (Join-Path $toolsOut $t.Name) -Algorithm SHA256).Hash.ToLower(), $t.Name)
}
if ($toolHashes.Count) { [IO.File]::WriteAllText((Join-Path $toolsOut 'SHA256SUMS'), (($toolHashes -join "`n") + "`n"), (New-Object Text.UTF8Encoding($false))) }
Check 'server tools copied' ($toolHashes.Count -ge 4) "$($toolHashes.Count) scripts"
$bashCandidates = @((Get-Command bash -ErrorAction SilentlyContinue).Source, 'C:\Program Files\Git\bin\bash.exe')
if ($gitExe) { $bashCandidates += (Join-Path (Split-Path -Parent (Split-Path -Parent $gitExe)) 'bin\bash.exe') }
$bash = $bashCandidates | Where-Object { $_ -and (Test-Path $_) } | Select-Object -First 1
if ($bash) {
    $badSh = @(); foreach ($t in (Get-ChildItem -LiteralPath $toolsOut -Filter '*.sh')) { & $bash -n $t.FullName 2>&1 | Out-Null; if ($LASTEXITCODE -ne 0) { $badSh += $t.Name } }
    Check 'server scripts pass `bash -n` (syntax)' ($badSh.Count -eq 0) $(if ($badSh.Count) { $badSh -join ', ' })
} else { Warn 'bash not found - server script syntax not checked' 'run `bash -n` on each script' }

# ---------------------------------------------------------------- archive
Step '6. Create and verify the archive'
$archive = Join-Path $out "societyflow-$releaseId.tar.gz"
$listFile = Join-Path $env:TEMP "sf-tarlist-$releaseId.txt"
$stagedRel | Sort-Object | Set-Content -LiteralPath $listFile -Encoding ascii
& $tar -czf $archive -C $stage -T $listFile
if ($LASTEXITCODE -ne 0) { throw "tar failed with exit code $LASTEXITCODE" }
Remove-Item -LiteralPath $listFile -Force
$listing = @(& $tar -tzf $archive)
Check 'archive lists exactly the staged files' ($listing.Count -eq $stagedRel.Count -and (@(Compare-Object ($listing | Sort-Object) ($stagedRel | Sort-Object)).Count -eq 0)) "$($listing.Count) entries"
Check 'no absolute or parent-relative paths in the archive' (@($listing | Where-Object { $_ -match '^(/|[A-Za-z]:|\.\.)|/\.\./' }).Count -eq 0)
# extract into a scratch folder and re-check the hashes the server will check
$verify = Join-Path $env:TEMP "societyflow-verify-$releaseId"
New-Item -ItemType Directory -Force -Path $verify | Out-Null
& $tar -xzf $archive -C $verify
$bad = 0; $nHash = 0
foreach ($line in [IO.File]::ReadAllLines((Join-Path $verify 'RELEASE.sha256'))) {
    if (-not $line) { continue }
    $nHash++; $h, $p = $line -split '  ', 2
    $ap = Join-Path $verify ($p -replace '/', '\')
    if (-not (Test-Path -LiteralPath $ap) -or (Get-FileHash -LiteralPath $ap -Algorithm SHA256).Hash.ToLower() -ne $h) { $bad++ }
}
Check 'extracted archive matches RELEASE.sha256' ($bad -eq 0 -and $nHash -eq ($stagedRel.Count - 1)) "$nHash hashes checked"
$archiveHash = (Get-FileHash -LiteralPath $archive -Algorithm SHA256).Hash.ToLower()
[IO.File]::WriteAllText("$archive.sha256", "$archiveHash  $(Split-Path -Leaf $archive)`n", (New-Object Text.UTF8Encoding($false)))
Remove-Item -LiteralPath $verify -Recurse -Force

# ---------------------------------------------------------------- reports
$inc = @("SocietyFlow release $releaseId - FILES IN THE PACKAGE ($($stagedRel.Count))", '')
$inc += ($stagedRel | Sort-Object | ForEach-Object { $abs = Join-Path $stage ($_ -replace '/', '\'); '{0,9}  {1}' -f (Get-Item -LiteralPath $abs).Length, $_ })
[IO.File]::WriteAllText((Join-Path $out 'INCLUDED.txt'), ($inc -join "`r`n") + "`r`n")

$exc = @("SocietyFlow release $releaseId - NOT IN THE PACKAGE", '', '--- Project entries that are not shipped (and why)', '')
$exc += ($rootNotShipped | ForEach-Object { '{0,-28} {1,7} files  {2}' -f $_.Path, $_.Files, $_.Reason })
$exc += @('', '--- Files / folders skipped inside the shipped folders', '')
if ($Excluded.Count) { $exc += ($Excluded | Sort-Object Path | ForEach-Object { '{0,-70} {1}' -f $_.Path, $_.Reason }) } else { $exc += '(none)' }
[IO.File]::WriteAllText((Join-Path $out 'EXCLUDED.txt'), ($exc -join "`r`n") + "`r`n")

$failed = @($results | Where-Object { -not $_.Ok })
$val = @("Validation of release $releaseId", '')
$val += ($results | ForEach-Object { '{0,-5} {1}  {2}' -f $(if (-not $_.Ok) { 'FAIL' } elseif ($_.Warn) { 'WARN' } else { 'PASS' }), $_.Name, $_.Detail })
$val += @('', "archive: $(Split-Path -Leaf $archive)", "size: $((Get-Item -LiteralPath $archive).Length) bytes", "sha256: $archiveHash")
[IO.File]::WriteAllText((Join-Path $out 'VALIDATION.txt'), ($val -join "`r`n") + "`r`n")

Remove-Item -LiteralPath $stage -Recurse -Force

Step 'Result'
if ($failed.Count -eq 0) {
    [IO.File]::WriteAllText((Join-Path $out 'READY.txt'), "Release $releaseId passed every validation check on $((Get-Date).ToString('yyyy-MM-dd HH:mm'))`r`nNothing has been uploaded. Next: docs/DEPLOYMENT_CLOUDPANEL.md`r`n")
    Say "READY   $archive"
    Say "sha256  $archiveHash"
    Say "Read INCLUDED.txt, EXCLUDED.txt, MIGRATIONS-REVIEW.txt and the WARN lines in VALIDATION.txt before uploading."
    exit 0
}
[IO.File]::WriteAllText((Join-Path $out 'NOT-READY.txt'), "Release $releaseId FAILED: " + (($failed | ForEach-Object { $_.Name }) -join '; ') + "`r`nDo not upload this package.`r`n")
Write-Host "NOT READY - $($failed.Count) check(s) failed:" -ForegroundColor Red
$failed | ForEach-Object { Write-Host "  - $($_.Name)  $($_.Detail)" -ForegroundColor Red }
exit 1
