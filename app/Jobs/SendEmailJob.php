<?php

namespace App\Jobs;

use App\Models\EmailLog;
use App\Models\EmailTemplate;
use App\Services\EmailConfigurationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Exception;

class SendEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected EmailLog $emailLog;
    protected EmailTemplate $template;

    public function __construct(EmailLog $emailLog, EmailTemplate $template)
    {
        $this->emailLog = $emailLog;
        $this->template = $template;
    }

    /**
     * Execute the job
     */
    public function handle(): void
    {
        // A password or link that was never stored cannot be rebuilt: sending "[hidden]" to a person would be worse than not sending.
        if ($this->emailLog->hasHiddenValues()) {
            $this->emailLog->markAsFailed('Not re-sent: this message carried a password or link that is not stored.');

            return;
        }

        try {
            // Load society-specific email configuration
            EmailConfigurationService::loadAndApplySettings($this->emailLog->society_id);

            // Replace variables in subject and body
            $subject = $this->replaceVariables($this->template->subject, $this->emailLog->variables ?? []);
            $body = $this->replaceVariables($this->template->body, $this->emailLog->variables ?? []);

            // Send the email
            Mail::send([], [], function ($message) use ($subject, $body) {
                $message->to($this->emailLog->recipient_email, $this->emailLog->recipient_name)
                        ->subject($subject)
                        ->html($body);
            });

            // A log-only / kill-switch run is not a send (see MailDelivery): say so instead of marking it sent
            $delivery = \App\Support\MailDelivery::outcome($this->emailLog->recipient_email);
            if (\App\Support\MailDelivery::isNotSent($delivery['status'])) {
                $this->emailLog->update(['status' => $delivery['status'], 'error_message' => $delivery['reason']]);
                return;
            }

            // Mark as sent
            $this->emailLog->markAsSent();

            Log::info('Email sent successfully', [
                'email_log_id' => $this->emailLog->id,
                'recipient' => $this->emailLog->recipient_email,
                'trigger' => $this->emailLog->trigger_action,
            ]);
        } catch (Exception $e) {
            $this->handleFailure($e);
        }
    }

    /**
     * Handle job failure
     */
    public function failed(Exception $exception): void
    {
        $this->handleFailure($exception);
    }

    /**
     * Handle email sending failure
     */
    private function handleFailure(Exception $exception): void
    {
        $errorMessage = $exception->getMessage();
        $this->emailLog->markAsFailed($errorMessage);

        Log::error('Email sending failed', [
            'email_log_id' => $this->emailLog->id,
            'recipient' => $this->emailLog->recipient_email,
            'trigger' => $this->emailLog->trigger_action,
            'error' => $errorMessage,
            'retry_count' => $this->emailLog->retry_count,
        ]);

        // Retry if needed
        if ($this->emailLog->shouldRetry()) {
            $this->release(delay: 300); // Retry after 5 minutes
        }
    }

    /**
     * Replace variables in text
     */
    private function replaceVariables(string $text, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $placeholder = '{{' . $key . '}}';
            $text = str_replace($placeholder, (string)$value, $text);
        }

        return $text;
    }
}
