<#
    php-upgrade-preflight.ps1 - READ-ONLY check of an extracted PHP package before XAMPP is pointed at it.

    It changes nothing: no php.ini is written, no Apache file is touched, nothing is restarted. It builds a throw-away php.ini in
    the temp folder, loads the candidate php.exe with it, and compares the result with the PHP that runs today.

        .\scripts\php-upgrade-preflight.ps1 -NewPhpDir G:\xampp\php-8.2.35 -ExpectedVersion 8.2.35
        .\scripts\php-upgrade-preflight.ps1 -NewPhpDir G:\xampp\php-8.2.35 -ExpectedVersion 8.2.35 `
            -ZipPath C:\Users\me\Downloads\php-8.2.35-Win32-vs16-x64.zip -ExpectedSha256 <hash shown on windows.php.net>

    Exit code 0 = every check passed; 1 = at least one FAIL (the list says which). See docs/PHP_UPGRADE_RUNBOOK.md for the rest.
#>
[CmdletBinding()]
param(
    [string]$NewPhpDir = 'G:\xampp\php-8.2.35',
    [string]$CurrentPhpDir = 'G:\xampp\php',
    [Parameter(Mandatory)][string]$ExpectedVersion,
    [string]$ZipPath,
    [string]$ExpectedSha256
)

# 'Continue', not 'Stop': under Windows PowerShell 5.1 a native command that merely writes to stderr (Composer does) would otherwise abort the script.
$ErrorActionPreference = 'Continue'
$repo = Split-Path -Parent $PSScriptRoot
$fail = 0
function Pass($m)  { Write-Host "  PASS  $m" -ForegroundColor Green }
function Fail($m)  { Write-Host "  FAIL  $m" -ForegroundColor Red; $script:fail++ }
function Info($m)  { Write-Host "        $m" }

Write-Host "`n== 1. Package contents ($NewPhpDir)"
if (-not (Test-Path $NewPhpDir) -or @(Get-ChildItem $NewPhpDir -Force).Count -eq 0) {
    Fail "the folder is missing or empty - nothing has been extracted into it yet"
    Write-Host "`nDownload the Windows build from https://windows.php.net/download/ (PHP 8.2 section): 'VS16 x64 Thread Safe' zip, then extract it into $NewPhpDir."
    exit 1
}
foreach ($f in 'php.exe', 'php8ts.dll', 'php8apache2_4.dll', 'php.ini-production', 'php.ini-development', 'ext\php_openssl.dll', 'ext\php_curl.dll', 'libsqlite3.dll') {
    if (Test-Path (Join-Path $NewPhpDir $f)) { Pass $f } else { Fail "$f is missing" }
}

Write-Host "`n== 2. Download integrity (only if -ZipPath and -ExpectedSha256 are given)"
if ($ZipPath -and $ExpectedSha256) {
    $actual = (Get-FileHash $ZipPath -Algorithm SHA256).Hash
    if ($actual -ieq $ExpectedSha256) { Pass "SHA256 of the zip matches the published value" } else { Fail "SHA256 differs: zip is $actual, expected $ExpectedSha256" }
} else {
    Info "skipped - compare the zip's SHA256 with the value published next to the download on windows.php.net"
}

Write-Host "`n== 3. Version and build type"
$newPhp = Join-Path $NewPhpDir 'php.exe'
$curPhp = Join-Path $CurrentPhpDir 'php.exe'
if (Test-Path $newPhp) {
    $ver = (& $newPhp -r 'echo PHP_VERSION;') 2>&1
    if ("$ver" -eq $ExpectedVersion) { Pass "php.exe reports $ver" } else { Fail "php.exe reports '$ver', expected $ExpectedVersion" }
    $info = (& $newPhp -i) 2>$null
    $cur  = (& $curPhp -i) 2>$null
    foreach ($key in 'Thread Safety', 'Architecture', 'Compiler', 'PHP Extension Build') {
        $n = ($info | Select-String "^$key =>" | Select-Object -First 1).Line
        $c = ($cur  | Select-String "^$key =>" | Select-Object -First 1).Line
        if ($n -and $n -eq $c) { Pass "$n" } else { Fail "$key differs: new '$n' vs current '$c' (Apache's module needs the same build type)" }
    }
}

Write-Host "`n== 4. Extensions, loaded with a throw-away php.ini (nothing is written to the PHP folders)"
$required = @('curl', 'fileinfo', 'mbstring', 'openssl', 'pdo_mysql', 'pdo_sqlite', 'mysqli', 'exif', 'gd', 'zip', 'intl')
$tmpIni = Join-Path $env:TEMP "php-preflight-$([guid]::NewGuid().ToString('N')).ini"
$lines = @("extension_dir=`"$(Join-Path $NewPhpDir 'ext')`"") + ($required | ForEach-Object { "extension=$_" })
Set-Content -Path $tmpIni -Value $lines -Encoding ascii
try {
    $mods = (& $newPhp -n -c $tmpIni -m) 2>&1
    foreach ($e in $required) {
        if ($mods -contains $e) { Pass "ext-$e loads" } else { Fail "ext-$e did not load (new php: $(($mods | Select-String -Pattern $e -SimpleMatch | Select-Object -First 1)))" }
    }
    $warn = $mods | Where-Object { $_ -match 'Warning|Unable to load|PHP Startup' }
    if ($warn) { $warn | ForEach-Object { Fail $_ } }

    Write-Host "`n== 5. Composer platform requirements with the new PHP (vendor/ is read, nothing is installed)"
    Push-Location $repo
    try {
        $out = (& $newPhp -n -c $tmpIni composer.phar check-platform-reqs) 2>&1
        $missing = $out | Select-String ' missing'
        if ($LASTEXITCODE -eq 0 -and -not $missing) { Pass "composer check-platform-reqs exit 0" } else { Fail "composer check-platform-reqs exit $LASTEXITCODE"; $missing | ForEach-Object { Info $_.Line.Trim() } }
    } finally { Pop-Location }
}
finally { if (Test-Path $tmpIni) { [void](Remove-Item $tmpIni -Force) } }

Write-Host "`n== 6. Today's runtime, for comparison"
Info ("current: " + ((& $curPhp -v) | Select-Object -First 1))
$curNeeds = @((& $curPhp -m) | Where-Object { $_ -match '^[a-z_0-9]+$' })
Info ("extensions enabled today: " + ($curNeeds -join ', '))

Write-Host ""
if ($fail -eq 0) { Write-Host "RESULT: all checks passed - safe to follow docs/PHP_UPGRADE_RUNBOOK.md" -ForegroundColor Green; exit 0 }
Write-Host "RESULT: $fail check(s) FAILED - do not switch Apache" -ForegroundColor Red
exit 1
