@echo off
echo Setting static IP 192.168.1.100 on Ethernet...
netsh interface ip set address name="Ethernet" static 192.168.1.100 255.255.255.0 192.168.1.1
netsh interface ip set dns name="Ethernet" static 192.168.1.1
netsh interface ip add dns name="Ethernet" 8.8.8.8 index=2
echo.
echo Done! Your PC IP is now permanently: 192.168.1.100
echo Laravel server: php artisan serve --host=192.168.1.100 --port=8000
echo.
pause
