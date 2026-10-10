<#
    run_android_usb.ps1 - local Android debug run over the USB cable.

    USB cable -> .\scripts\run_android_usb.ps1 -> debug app -> local API.

    How the phone reaches the PC
    ----------------------------
    `adb reverse tcp:8000 tcp:8000` makes the phone's own 127.0.0.1:8000 a
    tunnel to port 8000 on this PC, over the USB cable. The app is compiled to
    talk to http://127.0.0.1:8000/api/v1 (ApiConfig.localDevUrl), so it never
    needs the PC's LAN address, which changes with Wi-Fi. Nothing here edits
    an IP into the app.

    Production is untouched. Release builds use ApiConfig.prodUrl
    (https://societyflow.joboapps.com/api/v1) and never this tunnel.

    Laravel listens on 0.0.0.0:8000. That is what was asked for, but it also
    makes the dev server reachable from the LAN while it runs. Stop it when
    you are not developing.
#>

[CmdletBinding()]
param(
    [int]$Port = 8000,

    # Skip starting Laravel (something is already serving on $Port).
    [switch]$NoServe,

    # Build and install a debug APK instead of running an attached session.
    [switch]$BuildOnly
)

$ErrorActionPreference = 'Stop'

$RepoRoot   = Split-Path -Parent $PSScriptRoot
$FlutterDir = Join-Path $RepoRoot 'societyflowmob'
$HealthUrl  = "http://127.0.0.1:$Port/api/v1/health"

$UsbRequired = 'USB debugging connection required for local API. Connect the Android device by USB.'

function Write-Step { param($m) Write-Host "`n==> $m" -ForegroundColor Cyan }
function Write-Ok   { param($m) Write-Host "    [OK] $m" -ForegroundColor Green }
function Write-Bad  { param($m) Write-Host "    [!!] $m" -ForegroundColor Red }

# Resolve a tool from PATH, falling back to known XAMPP / SDK locations.
function Resolve-Tool {
    param([string]$Name, [string[]]$Fallbacks)

    $cmd = Get-Command $Name -ErrorAction SilentlyContinue
    if ($null -ne $cmd) { return $cmd.Source }
    foreach ($f in $Fallbacks) {
        if (Test-Path $f) { return $f }
    }
    return $null
}

# ── 1. adb ────────────────────────────────────────────────────────────────────
Write-Step 'Checking adb'

$adb = Resolve-Tool -Name 'adb' -Fallbacks @(
    (Join-Path $env:LOCALAPPDATA 'Android\Sdk\platform-tools\adb.exe'),
    (Join-Path $env:USERPROFILE  'AppData\Local\Android\Sdk\platform-tools\adb.exe'),
    'G:\tools\android-sdk\platform-tools\adb.exe',
    'C:\Android\Sdk\platform-tools\adb.exe'
)
if ($null -eq $adb) {
    Write-Bad 'adb not found. Install Android platform-tools, or add them to PATH.'
    exit 1
}
Write-Ok "adb: $adb"

# ── 2. Device over USB ────────────────────────────────────────────────────────
Write-Step 'Checking for an Android device connected by USB'

# Lines look like "<serial><TAB>device". Anything else (unauthorized, offline)
# is not usable. @(...) keeps a single match an array, not a bare string.
$allLines    = @(& $adb devices | Select-Object -Skip 1 | Where-Object { $_ -match '^\S+\s+\S+' })
$deviceLines = @($allLines | Where-Object { $_ -match '^\S+\s+device$' })

if ($deviceLines.Count -eq 0) {
    if ($allLines -match '\sunauthorized$') {
        Write-Bad 'Phone found but not authorised for USB debugging.'
        Write-Bad 'Unlock the phone and tap "Allow" on the "Allow USB debugging?" prompt'
        Write-Bad '(tick "Always allow from this computer"), then run this script again.'
    } elseif ($allLines -match '\soffline$') {
        Write-Bad 'Phone is connected but adb reports it offline.'
        Write-Bad 'Unplug and re-plug the cable, or run:  adb kill-server; adb start-server'
    } else {
        Write-Bad $UsbRequired
        Write-Bad 'No device seen. Check: cable supports data (not charge-only), USB debugging is on'
        Write-Bad '(Settings > Developer options), and the USB mode is "File transfer".'
    }
    & $adb devices
    exit 1
}
if ($deviceLines.Count -gt 1) {
    Write-Bad 'More than one device attached. Connect only the target phone:'
    & $adb devices
    exit 1
}

$serial = ($deviceLines[0] -split '\s+')[0]
Write-Ok "device: $serial"

# ── 3. adb reverse ────────────────────────────────────────────────────────────
Write-Step "Setting up adb reverse tcp:$Port tcp:$Port"

# Clear first, so a tunnel left over from an earlier session cannot linger.
& $adb -s $serial reverse --remove-all 2>$null | Out-Null
$reverseOut = & $adb -s $serial reverse "tcp:$Port" "tcp:$Port" 2>&1
$reverseExit = $LASTEXITCODE

$reverseList = @(& $adb -s $serial reverse --list)
if ($reverseExit -ne 0 -or -not ($reverseList -match "tcp:$Port\s+tcp:$Port")) {
    Write-Bad "adb reverse failed. Command: $adb -s $serial reverse tcp:$Port tcp:$Port"
    Write-Bad "adb said: $($reverseOut -join ' ')"
    Write-Bad "Current reverse list: $($reverseList -join ', ')"
    exit 1
}
Write-Ok "adb reverse --list: $($reverseList -join ', ')"

# ── 4. Laravel API on 0.0.0.0 ─────────────────────────────────────────────────
Write-Step "Checking the API on port $Port"

function Test-Health {
    try {
        $r = Invoke-RestMethod -Uri $HealthUrl -TimeoutSec 5 -Headers @{ Accept = 'application/json' }
        return ($r.application -eq 'societyflow')
    } catch {
        return $false
    }
}

# Who, if anyone, is listening on the port. Used to tell "nothing running"
# apart from "something else (Apache, another app) holds the port".
function Get-PortOwner {
    $conn = Get-NetTCPConnection -LocalPort $Port -State Listen -ErrorAction SilentlyContinue | Select-Object -First 1
    if ($null -eq $conn) { return $null }
    $proc = Get-Process -Id $conn.OwningProcess -ErrorAction SilentlyContinue
    return "$($proc.ProcessName) (PID $($conn.OwningProcess))"
}

if (Test-Health) {
    Write-Ok 'API already serving and identifying as societyflow'
} elseif ($owner = Get-PortOwner) {
    Write-Bad "Port $Port is in use by $owner, but it is not answering as SocietyFlow on /api/v1/health."
    Write-Bad "Stop that process (or the old server), or rerun with a free port:  .\scripts\run_android_usb.ps1 -Port 8001"
    exit 1
} elseif ($NoServe) {
    Write-Bad "Nothing healthy on port $Port and -NoServe was given."
    exit 1
} else {
    $php = Resolve-Tool -Name 'php' -Fallbacks @(
        'G:\xampp\php\php.exe', 'C:\xampp\php\php.exe'
    )
    if ($null -eq $php) {
        Write-Bad 'php not found. Add PHP to PATH or start the API yourself.'
        exit 1
    }

    Write-Host "    starting: php artisan serve --host=0.0.0.0 --port=$Port"
    Start-Process -FilePath $php `
        -ArgumentList 'artisan', 'serve', '--host=0.0.0.0', "--port=$Port" `
        -WorkingDirectory $RepoRoot -WindowStyle Minimized | Out-Null

    $up = $false
    foreach ($i in 1..30) {
        Start-Sleep -Milliseconds 500
        if (Test-Health) { $up = $true; break }
    }
    if (-not $up) {
        Write-Bad "API did not come up on port $Port. Check for a port conflict."
        exit 1
    }
    Write-Ok 'API started'
}

# ── 5. The phone reaches the API through the tunnel ───────────────────────────
Write-Step 'Verifying the phone reaches /api/v1/health through the tunnel'

$deviceProbe = & $adb -s $serial shell "curl -s -m 8 http://127.0.0.1:$Port/api/v1/health" 2>$null
if ($deviceProbe -match 'societyflow') {
    Write-Ok 'phone reached /api/v1/health through adb reverse'
} else {
    # Not fatal: some ROMs ship no curl. The app's own health check decides.
    Write-Host "    [--] on-device probe inconclusive" -ForegroundColor Yellow
    Write-Host "         response: $deviceProbe"
}

# ── 6. Flutter debug ──────────────────────────────────────────────────────────
# Port 8000 is what ApiConfig.localDevUrl is compiled with; any other port is
# passed in explicitly so nobody ever edits an address into the app.
$flutterDefines = @()
if ($Port -ne 8000) { $flutterDefines += "--dart-define=API_BASE_URL=http://127.0.0.1:$Port/api/v1" }

Write-Step "Launching the debug app (API: http://127.0.0.1:$Port/api/v1 via adb reverse)"

Push-Location $FlutterDir
try {
    if ($BuildOnly) {
        & flutter build apk --debug @flutterDefines
        if ($LASTEXITCODE -ne 0) { Write-Bad 'build failed'; exit $LASTEXITCODE }

        $apk = 'build\app\outputs\flutter-apk\app-debug.apk'
        & $adb -s $serial install -r $apk
        Write-Ok "installed $apk"
    } else {
        & flutter run -d $serial @flutterDefines
    }
} finally {
    Pop-Location
}
