@echo off
SET ADB="C:\Users\DELL\AppData\Local\Android\Sdk\platform-tools\adb.exe"

echo ========================================
echo  SocietyFlow Dev Server
echo ========================================
echo.

echo [1/2] Setting up ADB port forwarding...
%ADB% reverse tcp:8000 tcp:8000
if %ERRORLEVEL% EQU 0 (
    echo [OK] Phone port 8000 -> PC port 8000 (USB)
) else (
    echo [WARN] No phone connected via USB. Use WiFi instead.
)
echo.

echo [2/2] Starting Laravel on all interfaces...
echo.
echo   USB connection:  http://127.0.0.1:8000
echo   WiFi connection: http://192.168.1.22:8000
echo.
php artisan serve --host=0.0.0.0 --port=8000
pause
