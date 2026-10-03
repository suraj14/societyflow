<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Update event statuses every 15 minutes
        $schedule->command('events:update-statuses')
                 ->everyFifteenMinutes()
                 ->withoutOverlapping()
                 ->runInBackground();

        // Clock-time jobs fire on the societies' calendar, not the storage
        // timezone: with APP_TIMEZONE=UTC "09:00" was 14:30 IST.
        $tz = config('app.society_timezone', 'Asia/Kolkata');

        // Send bill reminders daily at 9 AM
        $schedule->command('bills:send-reminders --days=3')
                 ->dailyAt('09:00')
                 ->timezone($tz)
                 ->withoutOverlapping()
                 ->runInBackground();

        // Send overdue bill reminders daily at 6 PM
        $schedule->command('bills:send-reminders --days=0')
                 ->dailyAt('18:00')
                 ->timezone($tz)
                 ->withoutOverlapping()
                 ->runInBackground();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}