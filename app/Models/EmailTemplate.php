<?php

namespace App\Models;

use App\Enums\EmailEvent;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'society_id',
        'name',
        'slug',
        'subject',
        'body',
        'variables',
        'type',
        'status',
        'trigger_event'
    ];

    protected $casts = [
        'variables' => 'array',
        'trigger_event' => 'string'
    ];

    /**
     * Get the society that owns the email template
     */
    public function society()
    {
        return $this->belongsTo(Society::class);
    }

    /**
     * Scope for active templates
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope for society templates
     */
    public function scopeForSociety($query, $societyId)
    {
        return $query->where('society_id', $societyId);
    }

    /**
     * Scope for templates by trigger event
     */
    public function scopeByTriggerEvent($query, $triggerEvent)
    {
        return $query->where('trigger_event', $triggerEvent);
    }

    /**
     * Scope for templates by trigger event and society
     */
    public function scopeByEventAndSociety($query, $triggerEvent, $societyId)
    {
        return $query->where('trigger_event', $triggerEvent)
                     ->where(function($q) use ($societyId) {
                         $q->where('society_id', $societyId)
                           ->orWhereNull('society_id');
                     });
    }

    /**
     * Get template for a specific event
     */
    public static function getTemplateForEvent($triggerEvent, $societyId = null)
    {
        return self::active()
                   ->byEventAndSociety($triggerEvent, $societyId)
                   ->first();
    }

    /**
     * Get default email templates
     */
    public static function getDefaultTemplates()
    {
        return [
            [
                'name' => 'Welcome User',
                'slug' => 'welcome-user',
                'subject' => 'Welcome to {{society_name}}',
                'body' => 'Dear {{user_name}}, Welcome to {{society_name}}! Your account has been created successfully.',
                'variables' => ['user_name', 'society_name', 'login_url', 'temporary_password'],
                'type' => 'system',
                'trigger_event' => 'user_registered'
            ],
            [
                'name' => 'Password Reset',
                'slug' => 'reset-password',
                'subject' => 'Password Reset Request - {{society_name}}',
                'body' => 'Dear {{user_name}}, You have requested a password reset. Click the link to reset: {{reset_url}}',
                'variables' => ['user_name', 'society_name', 'reset_url'],
                'type' => 'system',
                'trigger_event' => 'password_reset'
            ],
            [
                'name' => 'New Bill Generated',
                'slug' => 'new-bill-generated',
                'subject' => 'New Bill Generated - {{society_name}}',
                'body' => 'Dear {{user_name}}, A new {{bill_type}} bill has been generated for amount {{amount}}. Due date: {{due_date}}',
                'variables' => ['user_name', 'society_name', 'bill_type', 'amount', 'due_date', 'view_url'],
                'type' => 'system',
                'trigger_event' => 'bill_generated'
            ],
            [
                'name' => 'Payment Received',
                'slug' => 'payment-received',
                'subject' => 'Payment Received - Thank You',
                'body' => 'Dear {{user_name}}, We have received your payment of {{amount}}. Thank you!',
                'variables' => ['user_name', 'society_name', 'amount', 'receipt_url'],
                'type' => 'system',
                'trigger_event' => 'payment_success'
            ],
            [
                'name' => 'Payment Overdue',
                'slug' => 'payment-overdue',
                'subject' => 'Payment Overdue - {{society_name}}',
                'body' => 'Dear {{user_name}}, Your payment for bill {{bill_number}} is now {{days_overdue}} days overdue. Amount due: {{amount}}. Please pay immediately.',
                'variables' => ['user_name', 'society_name', 'bill_number', 'amount', 'days_overdue', 'view_url'],
                'type' => 'system',
                'trigger_event' => 'payment_overdue'
            ],
            [
                'name' => 'New Notice',
                'slug' => 'new-notice',
                'subject' => 'New Notice - {{society_name}}',
                'body' => 'Dear {{user_name}}, A new notice has been published: {{notice_title}}',
                'variables' => ['user_name', 'society_name', 'notice_title', 'view_url'],
                'type' => 'system',
                'trigger_event' => 'notice_published'
            ],
            [
                'name' => 'New Event',
                'slug' => 'new-event',
                'subject' => 'New Event - {{event_name}}',
                'body' => 'Dear {{user_name}}, A new event has been created: {{event_name}} on {{event_date}}',
                'variables' => ['user_name', 'society_name', 'event_name', 'event_date', 'view_url'],
                'type' => 'system',
                'trigger_event' => 'event_created'
            ],
            [
                'name' => 'Ticket Updated',
                'slug' => 'ticket-updated',
                'subject' => 'Ticket {{ticket_number}} Updated - {{ticket_status}}',
                'body' => 'Dear {{user_name}}, Your complaint ticket {{ticket_number}} has been updated. Status: {{ticket_status}}. Category: {{ticket_category}}',
                'variables' => ['user_name', 'society_name', 'ticket_number', 'ticket_title', 'ticket_status', 'ticket_category', 'view_url'],
                'type' => 'system',
                'trigger_event' => 'ticket_updated'
            ],
            [
                'name' => 'Visitor Approved',
                'slug' => 'visitor-approved',
                'subject' => 'Visitor Approved - {{visitor_name}}',
                'body' => 'Dear {{user_name}}, Your visitor {{visitor_name}} has been approved. Visit Date: {{visit_date}} at {{visit_time}}',
                'variables' => ['user_name', 'society_name', 'visitor_name', 'visitor_phone', 'visit_date', 'visit_time', 'view_url'],
                'type' => 'system',
                'trigger_event' => 'visitor_approved'
            ],
            [
                'name' => 'Tenant/Owner Added',
                'slug' => 'tenant-owner-added',
                'subject' => 'New Tenant/Owner Added - {{tenant_name}}',
                'body' => 'Dear {{user_name}}, A new tenant/owner {{tenant_name}} has been added to property {{property_number}}. Lease period: {{lease_start_date}} to {{lease_end_date}}',
                'variables' => ['user_name', 'society_name', 'tenant_name', 'tenant_phone', 'property_number', 'lease_start_date', 'lease_end_date'],
                'type' => 'system',
                'trigger_event' => 'tenant_owner_added'
            ],
            [
                'name' => 'Report Generated',
                'slug' => 'report-generated',
                'subject' => 'Report Generated - {{report_type}}',
                'body' => 'Dear {{user_name}}, A {{report_type}} report has been generated on {{generated_at}}. Please review the attached report.',
                'variables' => ['user_name', 'society_name', 'report_type', 'generated_at', 'report_data'],
                'type' => 'system',
                'trigger_event' => 'report_generated'
            ]
        ];
    }
}