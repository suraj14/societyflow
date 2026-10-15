<?php

namespace App\Enums;

enum EmailEvent: string
{
    case USER_REGISTERED = 'user_registered';
    case TENANT_OWNER_ADDED = 'tenant_owner_added';
    case NOTICE_PUBLISHED = 'notice_published';
    case BILL_GENERATED = 'bill_generated';
    case PAYMENT_SUCCESS = 'payment_success';
    case PAYMENT_OVERDUE = 'payment_overdue';
    case TICKET_UPDATED = 'ticket_updated';
    case VISITOR_APPROVED = 'visitor_approved';
    case EVENT_CREATED = 'event_created';
    case PASSWORD_RESET = 'password_reset';
    case REPORT_GENERATED = 'report_generated';

    // DynamicEmailService::sendByEvent() rejects anything not listed here,
    // so every trigger_event in NotificationEvents must have a case
    // (asserted by EmailEventWhitelistTest). These four were registered
    // and dispatched but missing, so their emails were silently dropped.
    case ACCESS_PASS_CREATED = 'access_pass_created';
    case ACCESS_PASS_REVOKED = 'access_pass_revoked';
    case FACILITY_BOOKING_STATUS_UPDATED = 'facility_booking_status_updated';
    case SOCIETY_DOCUMENT_PUBLISHED = 'society_document_published';
    case EVENT_CHANGED = 'event_changed';

    // Gate approval workflow (VisitorNotifications)
    case VISITOR_REQUEST = 'visitor_request';
    case VISITOR_REJECTED = 'visitor_rejected';
    case VISITOR_OVERRIDE = 'visitor_override';
    case VISITOR_CHECKED_IN = 'visitor_checked_in';

    /**
     * Get all event values as array
     */
    public static function values(): array
    {
        return array_map(fn($case) => $case->value, self::cases());
    }

    /**
     * Get all events with labels for UI
     */
    public static function labels(): array
    {
        return [
            self::USER_REGISTERED->value => 'User Registered',
            self::TENANT_OWNER_ADDED->value => 'Tenant/Owner Added',
            self::NOTICE_PUBLISHED->value => 'Notice Published',
            self::BILL_GENERATED->value => 'Bill Generated',
            self::PAYMENT_SUCCESS->value => 'Payment Success',
            self::PAYMENT_OVERDUE->value => 'Payment Overdue',
            self::TICKET_UPDATED->value => 'Ticket Updated',
            self::VISITOR_APPROVED->value => 'Visitor Approved',
            self::EVENT_CREATED->value => 'Event Created',
            self::PASSWORD_RESET->value => 'Password Reset',
            self::REPORT_GENERATED->value => 'Report Generated',
            self::ACCESS_PASS_CREATED->value => 'Access Pass Created',
            self::ACCESS_PASS_REVOKED->value => 'Access Pass Revoked',
            self::FACILITY_BOOKING_STATUS_UPDATED->value => 'Facility Booking Status Updated',
            self::SOCIETY_DOCUMENT_PUBLISHED->value => 'Society Document Published',
            self::EVENT_CHANGED->value => 'Event Cancelled / Rescheduled / Updated',
            self::VISITOR_REQUEST->value => 'Visitor At The Gate (waiting for approval)',
            self::VISITOR_REJECTED->value => 'Visitor Rejected',
            self::VISITOR_OVERRIDE->value => 'Visitor Decision Changed By Administrator',
            self::VISITOR_CHECKED_IN->value => 'Visitor Entered',
        ];
    }

    /**
     * Get label for a specific event
     */
    public function label(): string
    {
        return self::labels()[$this->value] ?? $this->value;
    }
}
