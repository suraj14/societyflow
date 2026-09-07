@echo off
SET ADB="C:\Users\DELL\AppData\Local\Android\Sdk\platform-tools\adb.exe"
SET FLUTTER_DIR=societyflowmob
SET API_CONFIG=%FLUTTER_DIR%\lib\core\config\api_config.dart
SET APK=%FLUTTER_DIR%\build\app\outputs\flutter-apk\app-debug.apk

echo ========================================
echo  SocietyFlow - Auto Dev Starter
echo ========================================
echo.

REM Step 1: Get current IP (192.168.x.x or 10.x.x.x)
for /f "tokens=2 delims=:" %%a in ('ipconfig ^| findstr /i "IPv4" ^| findstr "192.168"') do (
    set RAW_IP=%%a
)
set IP=%RAW_IP: =%
if "%IP%"=="" (
    for /f "tokens=2 delims=:" %%a in ('ipconfig ^| findstr /i "IPv4" ^| findstr "10\."') do (
        set RAW_IP=%%a
    )
    set IP=%RAW_IP: =%
)

echo [1/5] PC IP: %IP%

REM Step 2: Update Flutter config with current IP
powershell -Command "(Get-Content '%API_CONFIG%') -replace 'static const String devFallbackUrl = .*', 'static const String devFallbackUrl = ''http://%IP%:8000/api/v1'';' | Set-Content '%API_CONFIG%'"
echo [2/5] Flutter config updated: http://%IP%:8000/api/v1

REM Step 3: Check for connected phone and install APK
%ADB% devices 2>nul | findstr /i "device" | findstr /v "List" >nul
if %ERRORLEVEL%==0 (
    echo [3/5] Phone detected - installing APK...
    %ADB% install -r "%APK%" 2>&1 && echo       APK installed successfully! || echo       APK install failed - try manually
    REM Set up ADB port forwarding for USB connection
    %ADB% reverse tcp:8000 tcp:8000 >nul 2>&1
    echo       ADB port forwarding set
) else (
    echo [3/5] No phone detected via USB - using WiFi mode
    echo       Connect phone to same WiFi: http://%IP%:8000
)

REM Step 4: Kill old PHP server
taskkill /F /IM php.exe /T >nul 2>&1
echo [4/5] Killed old PHP server

REM Step 5: Start Laravel
echo [5/5] Starting Laravel on 0.0.0.0:8000...
echo.
echo ========================================
echo  Server: http://localhost:8000
echo  Phone:  http://%IP%:8000
echo  Guard login: guard@societyflow.com / guard123
echo  Press Ctrl+C to stop
echo ========================================
echo.

php artisan serve --host=0.0.0.0 --port=8000
