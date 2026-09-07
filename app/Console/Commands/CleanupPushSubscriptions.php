<?php

namespace App\Console\Commands;

use App\Models\PushSubscription;
use Illuminate\Console\Command;

class CleanupPushSubscriptions extends Command
{
    protected $signature = 'push:cleanup {--society-id= : Specific society ID}';
    protected $description = 'Cleanup invalid and inactive push subscriptions';

    public function handle(): int
    {
        $societyId = $this->option('society-id');

        $query = PushSubscription::where('is_active', false)
            ->where('updated_at', '<', now()->subDays(30));

        if ($societyId) {
            $query->where('society_id', $societyId);
        }

        $count = $query->delete();

        $this->info("Deleted {$count} inactive push subscriptions");

        return Command::SUCCESS;
    }
}
