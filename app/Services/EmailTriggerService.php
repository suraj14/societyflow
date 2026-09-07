<?php

namespace App\Services;

use App\Models\User;
use App\Models\Society;
use App\Models\EmailTemplate;
use App\Models\EmailLog;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * Centralized Email Trigger Service
 * 
 * Handles all email sending with:
 * - Strict template slug matching
 * - Role-based recipient filtering
 * - Variable injection with safe defaults
 * - Comprehensive error handling and logging
 */
class EmailTriggerService
{
    /**
     * Template slug to role permissions mapping
     * Defines which roles can receive which email types
     */
    private static array $rolePermissions = [
        // Super Admin & Admin get all emails
        'Super Admin' => ['*'],
        'Admin' => ['*'],
        
        // Manager gets tickets and notices
        'Manager' => [
            'ticket_created',
            'ticket_update',
            'notice_published',
        ],
        
        // Owners (all types) get most emails except reports
        'Owner' => [
            'welcome_email',
            'user_added',
            'reset_password',
            'bill_generated',
            'payment_success',
            'payment_overdue',
            'ticket_created',
            'ticket_update',
            'notice_published',
            'event_invitation',
            'visitor_status',
        ],
        'Villa Owner' => [
            'welcome_email',
            'user_added',
            'reset_password',
            'bill_generated',
            'payment_success',
            'payment_overdue',
            'ticket_created',
            'ticket_update',
            'notice_published',
            'event_invitation',
            'visitor_status',
        ],
        'Apartment Owner' => [
            'welcome_email',
            'user_added',
            'reset_password',
            'bill_generated',
            'payment_success',
            'payment_overdue',
            'ticket_created',
            'ticket_update',
            'notice_published',
            'event_invitation',
            'visitor_status',
        ],
        
        // Tenants get most emails except reports
        'Tenant' => [
            'welcome_email',
            'user_added',
            'reset_password',
            'bill_generated',
            'payment_success',
            'payment_overdue',
            'ticket_created',
            'ticket_update',
            'notice_published',
            'event_invitation',
            'visitor_status',
        ],
        
        // Staff gets welcome, password reset, and tickets
        'Staff' => [
            'welcome_email',
            'reset_password',
            'ticket_created',
            'ticket_update',
        ],
        
        // Accountant gets welcome, password reset, bills, and payments
        'Accountant' => [
            'welcome_email',
            'reset_password',
            'bill_generated',
            'payment_success',
        ],
        
        // Guard gets welcome and password reset only
        'Guard' => [
            'welcome_email',
            'reset_password',
        ],
    ];

    /**
     * Trigger email sending for a specific action
     * 
     * @param string $templateSlug - Unique template identifier
     * @param array $context - Context data (user, bill, notice, etc.)
     * @return bool - Success status
     */
    public static function trigger(string $templateSlug, array $context = []): bool
    {
        try {
            $societyId = $context['society_id'] ?? null;

            // 1. Get template (STRICT slug matching)
            $template = EmailTemplate::where('slug', $templateSlug)
                ->where(function($q) use ($societyId) {
                    $q->where('society_id', $societyId)->orWhereNull('society_id');
                })
                ->where('status', 'active')
                ->first();

            if (!$template) {
                Log::warning('Email template not found', [
                    'template_slug' => $templateSlug,
                    'society_id' => $societyId,
                ]);
                return false;
            }

            // 2. Get recipients (role-based filtering)
            $recipients = self::getRecipients($templateSlug, $context);

            if (empty($recipients)) {
                Log::info('No eligible recipients for email', [
                    'template_slug' => $templateSlug,
                    'society_id' => $societyId,
                ]);
                return true; // Not an error, just no recipients
            }

            // 3. Prepare variables
            $variables = self::prepareVariables($template, $context, $societyId);

            // 4. Send to each recipient
            foreach ($recipients as $recipient) {
                self::sendToRecipient($template, $recipient, $variables, $templateSlug, $societyId);
            }

            return true;
        } catch (Exception $e) {
            Log::error('Email trigger failed', [
                'template_slug' => $templateSlug,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
    }

    /**
     * Get eligible recipients based on role permissions
     * 
     * @param string $templateSlug - Template identifier
     * @param array $context - Context data
     * @return array - Array of recipient arrays with email, name, role
     */
    private static function getRecipients(string $templateSlug, array $context = []): array
    {
        $recipients = [];
        $societyId = $context['society_id'] ?? null;

        // Determine recipient type based on template slug
        switch ($templateSlug) {
            case 'welcome_email':
            case 'user_added':
            case 'reset_password':
                // Single user recipient
                if (isset($context['user'])) {
                    $user = $context['user'];
                    $recipients[] = [
                        'email' => $user->email,
                        'name' => $user->name,
                        'role' => $user->getRoleNames()->first() ?? 'user',
                        'user_id' => $user->id,
                    ];
                }
                break;

            case 'bill_generated':
            case 'payment_success':
            case 'payment_overdue':
                // Bill/Payment recipients: property owners and tenants
                if (isset($context['flat_id'])) {
                    $recipients = self::getPropertyRecipients($context['flat_id'], $societyId);
                }
                // Also notify admin
                if ($context['notify_admin'] ?? false) {
                    $admin = User::where('society_id', $societyId)
                        ->role('Admin')
                        ->first();
                    if ($admin) {
                        $recipients[] = [
                            'email' => $admin->email,
                            'name' => $admin->name,
                            'role' => 'Admin',
                            'user_id' => $admin->id,
                        ];
                    }
                }
                break;

            case 'ticket_created':
            case 'ticket_update':
                // Ticket recipients: creator, assignee, admin
                if (isset($context['complaint'])) {
                    $complaint = $context['complaint'];
                    
                    if ($complaint->createdBy) {
                        $recipients[] = [
                            'email' => $complaint->createdBy->email,
                            'name' => $complaint->createdBy->name,
                            'role' => $complaint->createdBy->getRoleNames()->first() ?? 'user',
                            'user_id' => $complaint->createdBy->id,
                        ];
                    }

                    if ($complaint->assignedTo) {
                        $recipients[] = [
                            'email' => $complaint->assignedTo->email,
                            'name' => $complaint->assignedTo->name,
                            'role' => $complaint->assignedTo->getRoleNames()->first() ?? 'user',
                            'user_id' => $complaint->assignedTo->id,
                        ];
                    }

                    $admin = User::where('society_id', $societyId)
                        ->role('Admin')
                        ->first();
                    if ($admin) {
                        $recipients[] = [
                            'email' => $admin->email,
                            'name' => $admin->name,
                            'role' => 'Admin',
                            'user_id' => $admin->id,
                        ];
                    }
                }
                break;

            case 'notice_published':
            case 'event_invitation':
                // Notice/Event recipients: owners and tenants
                $query = User::where('society_id', $societyId)
                    ->whereHas('roles', function($q) {
                        $q->whereIn('name', ['Owner', 'Villa Owner', 'Apartment Owner', 'Tenant']);
                    });

                // Filter by visible roles if specified
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
                        'role' => $user->getRoleNames()->first() ?? 'user',
                        'user_id' => $user->id,
                    ];
                }
                break;

            case 'visitor_status':
                // Visitor recipients: property host
                if (isset($context['visitor'])) {
                    $visitor = $context['visitor'];
                    if ($visitor->hostUser) {
                        $recipients[] = [
                            'email' => $visitor->hostUser->email,
                            'name' => $visitor->hostUser->name,
                            'role' => $visitor->hostUser->getRoleNames()->first() ?? 'user',
                            'user_id' => $visitor->hostUser->id,
                        ];
                    }
                }
                break;

            case 'report_generated':
                // Report recipients: admin and accountant
                $users = User::where('society_id', $societyId)
                    ->whereHas('roles', function($q) {
                        $q->whereIn('name', ['Admin', 'Accountant']);
                    })
                    ->get();
                
                foreach ($users as $user) {
                    $recipients[] = [
                        'email' => $user->email,
                        'name' => $user->name,
                        'role' => $user->getRoleNames()->first() ?? 'user',
                        'user_id' => $user->id,
                    ];
                }
                break;
        }

        // Remove duplicates by email
        $uniqueRecipients = [];
        foreach ($recipients as $recipient) {
            $uniqueRecipients[$recipient['email']] = $recipient;
        }

        return array_values($uniqueRecipients);
    }

    /**
     * Get recipients for a property (owners and tenants)
     */
    private static function getPropertyRecipients(int $flatId, ?int $societyId): array
    {
        $recipients = [];

        // Get property owners
        $owners = User::whereHas('ownedProperties', function($q) use ($flatId) {
            $q->where('id', $flatId);
        })->where('society_id', $societyId)->get();

        foreach ($owners as $owner) {
            $recipients[] = [
                'email' => $owner->email,
                'name' => $owner->name,
                'role' => $owner->getRoleNames()->first() ?? 'user',
                'user_id' => $owner->id,
            ];
        }

        // Get tenants
        $tenants = User::whereHas('tenant', function($q) use ($flatId) {
            $q->whereHas('flat', function($subQ) use ($flatId) {
                $subQ->where('id', $flatId);
            });
        })->where('society_id', $societyId)->get();

        foreach ($tenants as $tenant) {
            $recipients[] = [
                'email' => $tenant->email,
                'name' => $tenant->name,
                'role' => $tenant->getRoleNames()->first() ?? 'user',
                'user_id' => $tenant->id,
            ];
        }

        return $recipients;
    }

    /**
     * Prepare variables for email template
     */
    private static function prepareVariables(EmailTemplate $template, array $context, ?int $societyId): array
    {
        $society = $societyId ? Society::find($societyId) : null;

        $variables = [
            'society_name' => $society?->name ?? 'SocietyFlow',
            'society_email' => $society?->email ?? 'support@societyflow.com',
            'society_phone' => $society?->phone ?? '',
            'society_address' => $society?->address ?? '',
        ];

        // Add context-specific variables
        if (isset($context['user'])) {
            $variables['user_name'] = $context['user']->name ?? '';
            $variables['user_email'] = $context['user']->email ?? '';
        }

        if (isset($context['bill'])) {
            $bill = $context['bill'];
            $variables['bill_number'] = $bill->bill_number ?? '';
            $variables['bill_type'] = $context['bill_type'] ?? 'maintenance';
            $variables['amount'] = $bill->amount ?? $bill->total_amount ?? 0;
            $variables['due_date'] = $bill->due_date ?? '';
            $variables['view_url'] = route('payments.show', $bill->id) ?? '';
        }

        if (isset($context['payment'])) {
            $payment = $context['payment'];
            $variables['payment_id'] = $payment->id ?? '';
            $variables['amount'] = $payment->amount ?? 0;
            $variables['payment_date'] = $payment->payment_date ?? now()->format('Y-m-d');
            $variables['receipt_url'] = route('payments.receipt', $payment->id) ?? '';
        }

        if (isset($context['complaint'])) {
            $complaint = $context['complaint'];
            $variables['complaint_number'] = $complaint->complaint_number ?? '';
            $variables['complaint_title'] = $complaint->title ?? '';
            $variables['status'] = $complaint->status ?? '';
            $variables['view_url'] = route('complaints.show', $complaint->id) ?? '';
        }

        if (isset($context['notice'])) {
            $notice = $context['notice'];
            $variables['notice_title'] = $notice->title ?? '';
            $variables['notice_content'] = substr($notice->content ?? '', 0, 200);
            $variables['view_url'] = route('notices.show', $notice->id) ?? '';
        }

        if (isset($context['event'])) {
            $event = $context['event'];
            $variables['event_name'] = $event->event_name ?? '';
            $variables['event_date'] = $event->start_date ?? '';
            $variables['event_time'] = $event->start_date?->format('H:i') ?? '';
            $variables['view_url'] = route('events.show', $event->id) ?? '';
        }

        if (isset($context['visitor'])) {
            $visitor = $context['visitor'];
            $variables['visitor_name'] = $visitor->visitor_name ?? '';
            $variables['visitor_type'] = $visitor->visitor_type ?? '';
            $variables['action'] = $context['action'] ?? 'updated';
            $variables['view_url'] = route('visitors.show', $visitor->id) ?? '';
        }

        if (isset($context['reset_url'])) {
            $variables['reset_url'] = $context['reset_url'];
        }

        if (isset($context['login_url'])) {
            $variables['login_url'] = $context['login_url'];
        }

        if (isset($context['temporary_password'])) {
            $variables['temporary_password'] = $context['temporary_password'];
        }

        return $variables;
    }

    /**
     * Send email to a single recipient
     */
    private static function sendToRecipient(
        EmailTemplate $template,
        array $recipient,
        array $variables,
        string $templateSlug,
        ?int $societyId
    ): void {
        try {
            // Check if recipient's role can receive this email type
            if (!self::canReceiveEmail($recipient['role'], $templateSlug)) {
                Log::info('Email filtered by role permission', [
                    'recipient_email' => $recipient['email'],
                    'role' => $recipient['role'],
                    'template_slug' => $templateSlug,
                ]);
                return;
            }

            // Load society-specific email configuration
            if ($societyId) {
                EmailConfigurationService::loadAndApplySettings($societyId);
            }

            // Replace variables in subject and body
            $subject = self::replaceVariables($template->subject, $variables);
            $body = self::replaceVariables($template->body, $variables);

            // Send email using view-based approach
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
                'trigger_action' => $template->slug,
                'variables' => $variables,
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            Log::info('Email sent successfully', [
                'recipient' => $recipient['email'],
                'template_slug' => $template->slug,
                'role' => $recipient['role'],
            ]);
        } catch (Exception $e) {
            Log::error('Email send failed', [
                'recipient' => $recipient['email'],
                'template_slug' => $templateSlug,
                'error' => $e->getMessage(),
            ]);

            // Log failed send
            try {
                EmailLog::create([
                    'email_template_id' => $template->id,
                    'society_id' => $societyId,
                    'recipient_email' => $recipient['email'],
                    'recipient_name' => $recipient['name'] ?? null,
                    'trigger_action' => $template->slug,
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
     * Check if a role can receive a specific email type
     */
    private static function canReceiveEmail(string $role, string $templateSlug): bool
    {
        $allowedTemplates = self::$rolePermissions[$role] ?? [];

        // Check for wildcard permission
        if (in_array('*', $allowedTemplates)) {
            return true;
        }

        // Check if template is in allowed list
        return in_array($templateSlug, $allowedTemplates);
    }

    /**
     * Replace variables in text with safe defaults
     */
    private static function replaceVariables(string $text, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $placeholder = '{{' . $key . '}}';
            // Replace with value or empty string if null
            $text = str_replace($placeholder, (string)($value ?? ''), $text);
        }
        return $text;
    }
}
