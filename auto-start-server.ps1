# Run this ONCE as Administrator to set up auto-start
# After this, Laravel server starts automatically every time Windows boots

$taskName = "SocietyFlow-Laravel-Server"
$scriptPath = "D:\xampp\htdocs\SocietyFlow\run-server.bat"

# Create the batch file that runs the server
$batContent = @"
@echo off
cd /d D:\xampp\htdocs\SocietyFlow
php artisan serve --host=0.0.0.0 --port=8000
"@
Set-Content -Path $scriptPath -Value $batContent

# Remove existing task if any
Unregister-ScheduledTask -TaskName $taskName -Confirm:$false -ErrorAction SilentlyContinue

# Create scheduled task to run at startup
$action = New-ScheduledTaskAction -Execute "cmd.exe" -Argument "/c `"$scriptPath`""
$trigger = New-ScheduledTaskTrigger -AtLogOn
$settings = New-ScheduledTaskSettingsSet -ExecutionTimeLimit 0 -RestartCount 3 -RestartInterval (New-TimeSpan -Minutes 1)
$principal = New-ScheduledTaskPrincipal -UserId $env:USERNAME -LogonType Interactive -RunLevel Highest

Register-ScheduledTask -TaskName $taskName -Action $action -Trigger $trigger -Settings $settings -Principal $principal -Force

Write-Host "SUCCESS! Laravel server will now auto-start every time you log into Windows." -ForegroundColor Green
Write-Host "Server will run at: http://0.0.0.0:8000" -ForegroundColor Cyan
Write-Host ""
Write-Host "To test: restart your PC and check if server is running." -ForegroundColor Yellow
