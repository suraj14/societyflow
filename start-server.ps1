# SocietyFlow Dev Server - Auto IP Updater + Server Starter
# Run this ONCE every day when you start development
# It automatically detects your PC's IP and updates the Flutter app

$ADB = "C:\Users\DELL\AppData\Local\Android\Sdk\platform-tools\adb.exe"
$ApiConfigPath = "societyflowmob\lib\core\config\api_config.dart"

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  SocietyFlow Dev Server" -ForegroundColor Cyan  
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Step 1: Get current PC IP
$ip = (Get-NetIPAddress -AddressFamily IPv4 | Where-Object { 
    $_.IPAddress -notlike "127.*" -and 
    $_.IPAddress -notlike "169.*" -and
    $_.PrefixOrigin -ne "WellKnown"
} | Select-Object -First 1).IPAddress

if (-not $ip) {
    Write-Host "[ERROR] Could not detect IP. Check your network connection." -ForegroundColor Red
    pause
    exit
}

Write-Host "[1/4] Detected PC IP: $ip" -ForegroundColor Green

# Step 2: Update Flutter api_config.dart with new IP
$content = Get-Content $ApiConfigPath -Raw
$newContent = $content -replace "static const String devFallbackUrl = 'http://[^']+';", "static const String devFallbackUrl = 'http://${ip}:8000/api/v1';"
Set-Content $ApiConfigPath $newContent -NoNewline
Write-Host "[2/4] Updated Flutter config: http://${ip}:8000/api/v1" -ForegroundColor Green

# Step 3: ADB port forwarding (USB connection)
Write-Host "[3/4] Setting up ADB port forwarding..." -ForegroundColor Yellow
$adbResult = & $ADB reverse tcp:8000 tcp:8000 2>&1
if ($LASTEXITCODE -eq 0) {
    Write-Host "      [OK] USB forwarding active" -ForegroundColor Green
} else {
    Write-Host "      [WARN] No USB device. Use WiFi." -ForegroundColor Yellow
}

# Step 4: Build and install APK with updated IP
Write-Host "[4/4] Building Flutter APK with new IP..." -ForegroundColor Yellow
Set-Location societyflowmob
$buildResult = flutter build apk --debug 2>&1 | Select-Object -Last 3
Write-Host $buildResult
Set-Location ..

# Install APK if phone connected
$devices = & $ADB devices 2>&1 | Select-String "device$"
if ($devices) {
    Write-Host ""
    Write-Host "Installing APK to phone..." -ForegroundColor Yellow
    & $ADB install -r "societyflowmob\build\app\outputs\flutter-apk\app-debug.apk" 2>&1
    & $ADB reverse tcp:8000 tcp:8000 2>&1 | Out-Null
    Write-Host "[OK] APK installed!" -ForegroundColor Green
} else {
    Write-Host "[INFO] No phone connected. Install APK manually from:" -ForegroundColor Yellow
    Write-Host "       societyflowmob\build\app\outputs\flutter-apk\app-debug.apk"
}

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Starting Laravel server on $ip`:8000" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Connect phone to same WiFi as this PC" -ForegroundColor Yellow
Write-Host "Press Ctrl+C to stop server" -ForegroundColor Yellow
Write-Host ""

php artisan serve --host=0.0.0.0 --port=8000
