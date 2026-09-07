<?php

namespace App\Console\Commands;

use App\Models\EmailLog;
use App\Jobs\SendEmailJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RetryFailedEmails extends Command
{
    protected $signature = 'email:retry-failed {--limit=50}';
    protected $description = 'Retry failed emails that have not exceeded retry limit';

    public function handle(): int
    {
        $limit = (int)$this->option('limit');
        
        $failedEmails = EmailLog::failed()
            ->where('retry_count', '<', 3)
            ->limit($limit)
            ->get();

        if ($failedEmails->isEmpty()) {
            $this->info('No failed emails to retry.');
            return 0;
        }

        $this->info("Retrying {$failedEmails->count()} failed emails...");

        foreach ($failedEmails as $emailLog) {
            try {
                $template = $emailLog->emailTemplate;
                if ($template) {
                    SendEmailJob::dispatch($emailLog, $template);
                    $this->line("Queued retry for email log ID: {$emailLog->id}");
                }
            } catch (\Exception $e) {
                $this->error("Failed to queue retry for email log ID {$emailLog->id}: {$e->getMessage()}");
                Log::error('Failed to queue email retry', [
                    'email_log_id' => $emailLog->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info('Email retry command completed.');
        return 0;
    }
}
