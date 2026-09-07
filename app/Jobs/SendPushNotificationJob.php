<?php

namespace App\Jobs;

use App\Models\PushNotificationLog;
use App\Models\User;
use App\Services\PushNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendPushNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [300, 600, 900]; // 5, 10, 15 minutes

    public function __construct(
        private int $userId,
        private string $triggerAction,
        private array $variables = [],
        private array $customData = []
    ) {}

    public function handle(PushNotificationService $service): void
    {
        try {
            $user = User::find($this->userId);
            if (!$user) {
                Log::warning('User not found for push notification', ['user_id' => $this->userId]);
                return;
            }

            $service->sendToUser($user, $this->triggerAction, $this->variables, $this->customData);
        } catch (\Exception $e) {
            Log::error('Push notification job failed', [
                'user_id' => $this->userId,
                'trigger' => $this->triggerAction,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);

            if ($this->attempts() >= $this->tries) {
                PushNotificationLog::create([
                    'user_id' => $this->userId,
                    'society_id' => $user->society_id ?? null,
                    'title' => 'Failed Notification',
                    'body' => 'Push notification failed after retries',
                    'trigger_action' => $this->triggerAction,
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                    'retry_count' => $this->attempts(),
                ]);
            }

            throw $e;
        }
    }
}
