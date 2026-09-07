<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\PushNotificationService;
use Illuminate\Console\Command;

class TestPushNotification extends Command
{
    protected $signature = 'push:test {user-id} {--trigger=test_notification}';
    protected $description = 'Send a test push notification to a user';

    public function handle(PushNotificationService $service): int
    {
        $userId = $this->argument('user-id');
        $trigger = $this->option('trigger');

        $user = User::find($userId);
        if (!$user) {
            $this->error("User {$userId} not found");
            return Command::FAILURE;
        }

        $this->info("Sending {$trigger} notification to {$user->name} ({$user->email})");

        $result = $service->sendToUser($user, $trigger, [
            'user_name' => $user->name,
            'society_name' => $user->society->name ?? 'SocietyFlow',
        ]);

        if ($result) {
            $this->info('Notification sent successfully');
            return Command::SUCCESS;
        } else {
            $this->error('Failed to send notification');
            return Command::FAILURE;
        }
    }
}
