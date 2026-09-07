<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Event;
use Illuminate\Support\Facades\Log;

class UpdateEventStatuses extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'events:update-statuses';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically update event statuses to completed after end date and time';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking for events that should be marked as completed...');

        $updatedCount = Event::updateExpiredEvents();

        if ($updatedCount > 0) {
            $this->info("Updated {$updatedCount} event(s) to completed status.");
            Log::info("Auto-updated {$updatedCount} event(s) to completed status", [
                'command' => 'events:update-statuses',
                'timestamp' => now()
            ]);
        } else {
            $this->info('No events needed status updates.');
        }

        return Command::SUCCESS;
    }
}