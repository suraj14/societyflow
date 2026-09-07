<?php

namespace App\Services;

use App\Models\User;
use App\Models\Society;
use App\Models\EmailTemplate;
use App\Models\EmailLog;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Exception;

class EmailNotificationService
{
    /**
     * Send email via trigger action (immediate send)
     */
    public static function sendByTrigger(string $triggerAction, array $recipients, array $variables = [], ?int $societyId = null): bool
    {
        try {
            $template = EmailTemplate::where('slug', $triggerAction)
                ->where(function($q) use ($societyId) {
                    $q->where('society_id', $societyId)->orWhereNull('society_id');
                })
                ->where('status', 'active')
                ->first();

            if (!$template) {
                Log::warning('Email template not found', ['trigger' => $triggerAction, 'society_id' => $societyId]);
                return false;
            }

            $society = $societyId ? Society::find($societyId) : null;
            $variables = array_merge($variables, [
                'society_name' => $society?->name ?? 'SocietyFlow',
                'society_email' => $society?->email ?? 'support@societyflow.com',
                'society_phone' => $society?->phone ?? '',
                'society_address' => $society?->address ?? '',
            ]);

            foreach ($recipients as $recipient) {
                if (self::shouldReceiveEmail($recipient, $triggerAction)) {
                    try {
                        if ($societyId) {
                            EmailConfigurationService::loadAndApplySettings($societyId);
                        }

                        $subject = self::replaceVariables($template->subject, $variables);
                        $body = self::replaceVariables($template->body, $variables);

                        // Send email with HTML content using view-based approach
                        Mail::send('emails.raw-html', ['body' => $body], function ($message) use ($recipient, $subject) {
                            $message->to($recipient['email'], $recipient['name'] ?? 'Recipient')
                                    ->subject($subject);
                        });

                        EmailLog::create([
                            'email_template_id' => $template->id,
                            'society_id' => $societyId,
                            'recipient_email' => $recipient['email'],
                            'recipient_name' => $recipient['name'] ?? null,
                            'trigger_action' => $triggerAction,
                            'variables' => $variables,
                            'status' => 'sent',
                        ]);

                        Log::info('Email sent', ['recipient' => $recipient['email'], 'trigger' => $triggerAction]);
                    } catch (Exception $e) {
                        Log::error('Email send failed', ['recipient' => $recipient['email'], 'error' => $e->getMessage()]);
                        
                        // Log failed email attempt
                        EmailLog::create([
                            'email_template_id' => $template->id,
                            'society_id' => $societyId,
                            'recipient_email' => $recipient['email'],
                            'recipient_name' => $recipient['name'] ?? null,
                            'trigger_action' => $triggerAction,
                            'variables' => $variables,
                            'status' => 'failed',
                            'error_message' => $e->getMessage(),
                        ]);
                    }
                }
            }

            return true;
        } catch (Exception $e) {
            Log::error('Email trigger failed', ['trigger' => $triggerAction, 'error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Replace variables in text
     */
    private static function replaceVariables(string $text, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $placeholder = '{{' . $key . '}}';
            $text = str_replace($placeholder, (string)$value, $text);
        }
        return $text;
    }

    /**
     * Check if user should receive specific email type based on their role
     * 
     * Permission Matrix:
     * Action          | Owner | Tenant | Admin | Staff | Accountant
     * Welcome         |  ✅   |  ✅    |  ✅   |  ✅   |  ✅
     * Bill Generated  |  ✅   |  ✅    |  ✅   |  ❌   |  ✅
     * Payment Success |  ✅   |  ✅    |  ✅   |  ❌   |  ✅
     * Overdue         |  ✅   |  ✅    |  ✅   |  ❌   |  ❌
     * Ticket Update   |  ✅   |  ✅    |  ✅   |  ✅   |  ❌
     * Notice          |  ✅   |  ✅    |  ✅   |  ❌   |  ❌
     * Event           |  ✅   |  ✅    |  ❌   |  ❌   |  ❌
     * Visitor         |  ✅   |  ✅    |  ❌   |  ❌   |  ❌
     * Reports         |  ❌   |  ❌    |  ✅   |  ❌   |  ✅
     */
    private static function shouldReceiveEmail(array $recipient, string $template): bool
    {
        $role = $recipient['role'] ?? 'user';
        
        // Define email permissions by role and template slug
        $emailPermissions = [
            'Super Admin' => ['*'],
            'Admin' => ['*'],
            'Manager' => ['ticket-*', 'notice-*'],
            
            // Apartment Owner, Villa Owner, Owner
            'Apartment Owner' => [
                'welcome-user',
                'reset-password',
                'new-bill-generated',
                'payment-received',
                'bill-overdue',
                'ticket-created',
                'ticket-status-updated',
                'ticket-closed',
                'new-notice',
                'new-event',
                'visitor-entry-created',
                'visitor-status-updated',
            ],
            'Villa Owner' => [
                'welcome-user',
                'reset-password',
                'new-bill-generated',
                'payment-received',
                'bill-overdue',
                'ticket-created',
                'ticket-status-updated',
                'ticket-closed',
                'new-notice',
                'new-event',
                'visitor-entry-created',
                'visitor-status-updated',
            ],
            'Owner' => [
                'welcome-user',
                'reset-password',
                'new-bill-generated',
                'payment-received',
                'bill-overdue',
                'ticket-created',
                'ticket-status-updated',
                'ticket-closed',
                'new-notice',
                'new-event',
                'visitor-entry-created',
                'visitor-status-updated',
            ],
            
            // Tenant
            'Tenant' => [
                'welcome-user',
                'reset-password',
                'rent-bill-generated',
                'new-bill-generated',
                'payment-received',
                'bill-overdue',
                'ticket-created',
                'ticket-status-updated',
                'ticket-closed',
                'new-notice',
                'new-event',
                'visitor-entry-created',
                'visitor-status-updated',
            ],
            
            // Staff
            'Staff' => [
                'welcome-user',
                'reset-password',
                'ticket-created',
                'ticket-status-updated',
                'ticket-closed',
            ],
            
            // Accountant
            'Accountant' => [
                'welcome-user',
                'reset-password',
                'new-bill-generated',
                'payment-received',
                'rent-bill-generated',
            ],
            
            // Guard
            'Guard' => [
                'welcome-user',
                'reset-password',
            ],
        ];

        $allowedTemplates = $emailPermissions[$role] ?? [];
        
        // Check if role has wildcard permission
        if (in_array('*', $allowedTemplates)) {
            return true;
        }

        // Check if template is in allowed list
        if (in_array($template, $allowedTemplates)) {
            return true;
        }

        // Check for wildcard patterns
        foreach ($allowedTemplates as $pattern) {
            if (strpos($pattern, '*') !== false && fnmatch($pattern, $template)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get recipients for a specific notification type
     */
    public static function getRecipients(string $type, array $context = []): array
    {
        $recipients = [];
        $societyId = $context['society_id'] ?? null;

        switch ($type) {
            case 'user_created':
            case 'password_reset':
            case 'password_changed':
            case 'account_activated':
            case 'account_deactivated':
                if (isset($context['user'])) {
                    $user = $context['user'];
                    $recipients[] = [
                        'email' => $user->email,
                        'name' => $user->name,
                        'role' => $user->getRoleNames()->first() ?? 'user'
                    ];
                }
                break;

            case 'bill_generated':
            case 'rent_bill_generated':
            case 'payment_received':
            case 'payment_failed':
            case 'bill_due_reminder':
                if (isset($context['flat_id'])) {
                    $flatUsers = User::whereHas('ownedProperties', function($q) use ($context) {
                        $q->where('id', $context['flat_id']);
                    })->orWhereHas('tenant', function($q) use ($context) {
                        $q->whereHas('flat', function($subQ) use ($context) {
                            $subQ->where('id', $context['flat_id']);
                        });
                    })->where('society_id', $societyId)->get();

                    foreach ($flatUsers as $user) {
                        $recipients[] = [
                            'email' => $user->email,
                            'name' => $user->name,
                            'role' => $user->getRoleNames()->first() ?? 'user'
                        ];
                    }
                }

                if ($context['notify_admin'] ?? false) {
                    $admin = User::where('society_id', $societyId)->role('Admin')->first();
                    if ($admin) {
                        $recipients[] = [
                            'email' => $admin->email,
                            'name' => $admin->name,
                            'role' => 'Admin'
                        ];
                    }
                }
                break;

            case 'ticket_created':
            case 'ticket_assigned':
            case 'ticket_status_updated':
            case 'ticket_closed':
                if (isset($context['complaint'])) {
                    $complaint = $context['complaint'];
                    
                    if ($complaint->createdBy) {
                        $recipients[] = [
                            'email' => $complaint->createdBy->email,
                            'name' => $complaint->createdBy->name,
                            'role' => $complaint->createdBy->getRoleNames()->first() ?? 'user'
                        ];
                    }

                    if ($complaint->assignedTo) {
                        $recipients[] = [
                            'email' => $complaint->assignedTo->email,
                            'name' => $complaint->assignedTo->name,
                            'role' => $complaint->assignedTo->getRoleNames()->first() ?? 'user'
                        ];
                    }

                    $admin = User::where('society_id', $societyId)->role('Admin')->first();
                    if ($admin) {
                        $recipients[] = [
                            'email' => $admin->email,
                            'name' => $admin->name,
                            'role' => 'Admin'
                        ];
                    }
                }
                break;

            case 'notice_published':
            case 'notice_updated':
            case 'event_created':
            case 'event_updated':
            case 'event_reminder':
                $query = User::where('society_id', $societyId)
                           ->whereHas('roles', function($q) {
                               $q->whereIn('name', ['Apartment Owner', 'Villa Owner', 'Tenant']);
                           });

                if (isset($context['visible_roles']) && is_array($context['visible_roles'])) {
                    $query->whereHas('roles', function($q) use ($context) {
                        $q->whereIn('name', $context['visible_roles']);
                    });
                }

                $users = $query->get();
                foreach ($users as $user) {
                    $recipients[] = [
                        'email' => $user->email,
                        'name' => $user->name,
                        'role' => $user->getRoleNames()->first() ?? 'user'
                    ];
                }
                break;

            case 'visitor_entry_created':
            case 'visitor_status_updated':
            case 'visitor_allowed':
            case 'visitor_denied':
                if (isset($context['visitor'])) {
                    $visitor = $context['visitor'];
                    if ($visitor->hostUser) {
                        $recipients[] = [
                            'email' => $visitor->hostUser->email,
                            'name' => $visitor->hostUser->name,
                            'role' => $visitor->hostUser->getRoleNames()->first() ?? 'user'
                        ];
                    }
                }
                break;

            case 'society_registration':
            case 'plan_purchase':
            case 'plan_upgraded':
            case 'trial_ending':
            case 'plan_expired':
                if ($societyId) {
                    $admin = User::where('society_id', $societyId)->role('Admin')->first();
                    if ($admin) {
                        $recipients[] = [
                            'email' => $admin->email,
                            'name' => $admin->name,
                            'role' => 'Admin'
                        ];
                    }
                }
                break;
        }

        $uniqueRecipients = [];
        foreach ($recipients as $recipient) {
            $uniqueRecipients[$recipient['email']] = $recipient;
        }

        return array_values($uniqueRecipients);
    }

    /**
     * Send welcome email to new user
     */
    public static function sendWelcomeEmail(User $user, string $temporaryPassword = null): bool
    {
        $recipients = [[
            'email' => $user->email,
            'name' => $user->name,
            'role' => $user->getRoleNames()->first() ?? 'user'
        ]];

        $variables = [
            'user_name' => $user->name,
            'user_email' => $user->email,
            'temporary_password' => $temporaryPassword ?? 'N/A',
            'login_url' => route('login'),
        ];

        return self::sendByTrigger('welcome-user', $recipients, $variables, $user->society_id);
    }

    /**
     * Send password reset email
     */
    public static function sendPasswordResetEmail(User $user, string $resetToken): bool
    {
        $recipients = [[
            'email' => $user->email,
            'name' => $user->name,
            'role' => $user->getRoleNames()->first() ?? 'user'
        ]];

        $variables = [
            'user_name' => $user->name,
            'user_email' => $user->email,
            'reset_url' => route('password.reset', ['token' => $resetToken, 'email' => $user->email]),
        ];

        return self::sendByTrigger('reset-password', $recipients, $variables, $user->society_id);
    }

    /**
     * Send bill notification email
     */
    public static function sendBillNotification(object $bill, string $type = 'maintenance'): bool
    {
        $recipients = self::getRecipients('bill_generated', [
            'society_id' => $bill->society_id,
            'flat_id' => $bill->flat_id ?? null,
        ]);

        $variables = [
            'bill_number' => $bill->bill_number ?? 'N/A',
            'bill_type' => $type,
            'amount' => $bill->amount ?? $bill->total_amount ?? 0,
            'due_date' => $bill->due_date ?? 'N/A',
            'view_url' => route('payments.show', $bill->id),
        ];

        return self::sendByTrigger('new-bill-generated', $recipients, $variables, $bill->society_id);
    }

    /**
     * Send bill overdue notification email
     */
    public static function sendBillOverdueNotification(object $bill, string $type = 'maintenance'): bool
    {
        $recipients = self::getRecipients('bill_overdue', [
            'society_id' => $bill->society_id,
            'flat_id' => $bill->flat_id ?? null,
        ]);

        $variables = [
            'bill_number' => $bill->bill_number ?? 'N/A',
            'bill_type' => $type,
            'amount' => $bill->amount ?? $bill->total_amount ?? 0,
            'due_date' => $bill->due_date ?? 'N/A',
            'view_url' => route('payments.show', $bill->id),
        ];

        return self::sendByTrigger('bill-overdue', $recipients, $variables, $bill->society_id);
    }

    /**
     * Send payment confirmation email
     */
    public static function sendPaymentConfirmation(object $payment): bool
    {
        $recipients = self::getRecipients('payment_received', [
            'society_id' => $payment->society_id,
            'flat_id' => $payment->flat_id ?? null,
        ]);

        $variables = [
            'payment_id' => $payment->id,
            'amount' => $payment->amount ?? 0,
            'payment_date' => $payment->payment_date ?? now()->format('Y-m-d'),
            'receipt_url' => route('payments.receipt', $payment->id),
        ];

        return self::sendByTrigger('payment-received', $recipients, $variables, $payment->society_id);
    }

    /**
     * Send complaint/ticket notification
     */
    public static function sendComplaintNotification(object $complaint, string $action = 'created'): bool
    {
        $recipients = self::getRecipients("ticket_{$action}", [
            'society_id' => $complaint->society_id,
            'complaint' => $complaint,
        ]);

        $variables = [
            'complaint_number' => $complaint->complaint_number ?? 'N/A',
            'complaint_title' => $complaint->title ?? 'N/A',
            'action' => $action,
            'view_url' => route('complaints.show', $complaint->id),
        ];

        return self::sendByTrigger("ticket-{$action}", $recipients, $variables, $complaint->society_id);
    }

    /**
     * Send notice notification
     */
    public static function sendNoticeNotification(object $notice, string $action = 'published'): bool
    {
        $recipients = self::getRecipients("notice_{$action}", [
            'society_id' => $notice->society_id,
            'visible_roles' => $notice->visible_roles ?? null,
        ]);

        $variables = [
            'notice_title' => $notice->title ?? 'N/A',
            'notice_content' => substr($notice->content ?? '', 0, 200),
            'action' => $action,
            'view_url' => route('notices.show', $notice->id),
        ];

        return self::sendByTrigger("new-notice", $recipients, $variables, $notice->society_id);
    }

    /**
     * Send event notification
     */
    public static function sendEventNotification(object $event, string $action = 'created'): bool
    {
        $recipients = self::getRecipients("event_{$action}", [
            'society_id' => $event->society_id,
            'visible_roles' => $event->visible_roles ?? null,
        ]);

        $variables = [
            'event_name' => $event->event_name ?? 'N/A',
            'event_date' => $event->event_date ?? 'N/A',
            'event_time' => $event->event_time ?? 'N/A',
            'action' => $action,
            'view_url' => route('events.show', $event->id),
        ];

        return self::sendByTrigger("new-event", $recipients, $variables, $event->society_id);
    }

    /**
     * Send visitor notification
     */
    public static function sendVisitorNotification(object $visitor, string $action = 'allowed'): bool
    {
        $recipients = self::getRecipients("visitor_{$action}", [
            'society_id' => $visitor->society_id,
            'visitor' => $visitor,
        ]);

        $variables = [
            'visitor_name' => $visitor->visitor_name ?? 'N/A',
            'visitor_type' => $visitor->visitor_type ?? 'N/A',
            'action' => $action,
            'view_url' => route('visitors.show', $visitor->id),
        ];

        $templateSlug = $action === 'allowed' ? 'visitor-approved' : 'visitor-denied';
        return self::sendByTrigger($templateSlug, $recipients, $variables, $visitor->society_id);
    }
}
