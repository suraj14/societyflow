<?php

namespace App\Services;

use App\Enums\EmailEvent;
use App\Models\EmailTemplate;
use App\Models\EmailLog;
use App\Models\Society;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * Dynamic Email Service
 * 
 * Handles email sending based on configured template-to-event mappings.
 * Admins can configure which templates are sent for specific events.
 */
class DynamicEmailService
{
    /**
     * Send email based on trigger event
     * 
     * @param string $triggerEvent - Event that triggered the email (from EmailEvent enum)
     * @param array $recipients - Array of recipient arrays with email, name, role
     * @param array $variables - Variables to replace in template
     * @param int|null $societyId - Society ID for multi-tenant support
     * @return bool - Success status
     */
    public static function sendByEvent(
        string $triggerEvent,
        array $recipients,
        array $variables = [],
        ?int $societyId = null
    ): bool {
        try {
            // Validate event
            if (!in_array($triggerEvent, EmailEvent::values())) {
                Log::warning('Invalid email event', ['event' => $triggerEvent]);
                return false;
            }

            // Get template configured for this event
            $template = EmailTemplate::getTemplateForEvent($triggerEvent, $societyId);

            if (!$template) {
                Log::warning('No email template configured for event', [
                    'event' => $triggerEvent,
                    'society_id' => $societyId,
                ]);
                return false;
            }

            // Check if template is active
            if ($template->status !== 'active') {
                Log::info('Email template is inactive', [
                    'template_id' => $template->id,
                    'event' => $triggerEvent,
                ]);
                return false;
            }

            // Prepare society variables
            $society = $societyId ? Society::find($societyId) : null;
            $variables = array_merge($variables, [
                'society_name' => $society?->name ?? 'SocietyFlow',
                'society_email' => $society?->email ?? 'support@societyflow.com',
                'society_phone' => $society?->phone ?? '',
                'society_address' => $society?->address ?? '',
            ]);

            // Send to each recipient
            foreach ($recipients as $recipient) {
                self::sendToRecipient($template, $recipient, $variables, $triggerEvent, $societyId);
            }

            return true;
        } catch (Exception $e) {
            Log::error('Dynamic email sending failed', [
                'event' => $triggerEvent,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Send email to a single recipient
     */
    private static function sendToRecipient(
        EmailTemplate $template,
        array $recipient,
        array $variables,
        string $triggerEvent,
        ?int $societyId
    ): void {
        try {
            // Load email configuration
            if ($societyId) {
                EmailConfigurationService::loadAndApplySettings($societyId);
            }

            // Replace variables
            $subject = self::replaceVariables($template->subject, $variables);
            $body = self::replaceVariables($template->body, $variables);

            // Send email
            Mail::send('emails.raw-html', ['body' => $body], function ($message) use ($recipient, $subject) {
                $message->to($recipient['email'], $recipient['name'] ?? 'Recipient')
                        ->subject($subject);
            });

            // Log successful send
            EmailLog::create([
                'email_template_id' => $template->id,
                'society_id' => $societyId,
                'recipient_email' => $recipient['email'],
                'recipient_name' => $recipient['name'] ?? null,
                'trigger_action' => $triggerEvent,
                'variables' => $variables,
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            Log::info('Email sent successfully', [
                'recipient' => $recipient['email'],
                'event' => $triggerEvent,
                'template_id' => $template->id,
            ]);
        } catch (Exception $e) {
            Log::error('Email send failed', [
                'recipient' => $recipient['email'],
                'event' => $triggerEvent,
                'error' => $e->getMessage(),
            ]);

            // Log failed send
            try {
                EmailLog::create([
                    'email_template_id' => $template->id,
                    'society_id' => $societyId,
                    'recipient_email' => $recipient['email'],
                    'recipient_name' => $recipient['name'] ?? null,
                    'trigger_action' => $triggerEvent,
                    'variables' => $variables,
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                ]);
            } catch (Exception $logError) {
                Log::error('Failed to log email error', ['error' => $logError->getMessage()]);
            }
        }
    }

    /**
     * Replace variables in text
     */
    private static function replaceVariables(string $text, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $placeholder = '{{' . $key . '}}';
            $text = str_replace($placeholder, (string)($value ?? ''), $text);
        }
        return $text;
    }

    /**
     * Get all available events for admin UI
     */
    public static function getAvailableEvents(): array
    {
        return EmailEvent::labels();
    }

    /**
     * Check if event has a configured template
     */
    public static function hasTemplateForEvent(string $triggerEvent, ?int $societyId = null): bool
    {
        return EmailTemplate::getTemplateForEvent($triggerEvent, $societyId) !== null;
    }

    /**
     * Get template for event
     */
    public static function getTemplate(string $triggerEvent, ?int $societyId = null): ?EmailTemplate
    {
        return EmailTemplate::getTemplateForEvent($triggerEvent, $societyId);
    }
}
