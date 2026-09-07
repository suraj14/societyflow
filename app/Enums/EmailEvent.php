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
