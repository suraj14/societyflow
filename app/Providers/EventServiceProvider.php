<?php

namespace App\Providers;

use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

// Application Events
use App\Events\UserCreated;
use App\Events\BillGenerated;
use App\Events\PaymentReceived;
use App\Events\NoticePublished;
use App\Events\EventCreated;
use App\Events\PaymentOverdue;
use App\Events\TicketUpdated;
use App\Events\PasswordReset;
use App\Events\TenantOwnerAdded;
use App\Events\VisitorApproved;
use App\Events\ReportGenerated;
use App\Events\AccessPassCreated;
use App\Events\AccessPassRevoked;
use App\Events\FacilityBookingStatusUpdated;
use App\Events\SocietyDocumentPublished;

// Application Listeners
use App\Listeners\SendUserWelcomeEmail;
use App\Listeners\SendBillGeneratedEmail;
use App\Listeners\SendPaymentConfirmationEmail;
use App\Listeners\SendNoticePublishedEmail;
use App\Listeners\SendNoticePublishedSms;
use App\Listeners\SendEventCreatedEmail;
use App\Listeners\SendPaymentOverdueEmail;
use App\Listeners\SendTicketUpdatedEmail;
use App\Listeners\SendPasswordResetEmail;
use App\Listeners\SendTenantOwnerAddedEmail;
use App\Listeners\SendVisitorApprovedEmail;
use App\Listeners\SendReportGeneratedEmail;
use App\Listeners\SendAccessPassCreatedEmail;
use App\Listeners\SendAccessPassRevokedEmail;
use App\Listeners\SendAccessPassRevokedSms;
use App\Listeners\SendAccessPassRevokedWhatsApp;
use App\Listeners\SendFacilityBookingStatusUpdatedEmail;
use App\Listeners\SendSocietyDocumentPublishedEmail;
use App\Listeners\SendSocietyDocumentPublishedSms;
use App\Listeners\SendSocietyDocumentPublishedWhatsApp;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    /**
     * Header-bell entries and WhatsApp for the business events above, kept
     * apart from the email/SMS listeners so neither can stop the other.
     */
    protected $subscribe = [
        \App\Listeners\BusinessNotificationSubscriber::class,
    ];

    protected $listen = [
        // Laravel Auth Events
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],

        // SocietyFlow Email Trigger Events
        UserCreated::class => [
            SendUserWelcomeEmail::class,
        ],
        BillGenerated::class => [
            SendBillGeneratedEmail::class,
        ],
        PaymentReceived::class => [
            SendPaymentConfirmationEmail::class,
        ],
        NoticePublished::class => [
            SendNoticePublishedEmail::class,
            SendNoticePublishedSms::class,
        ],
        EventCreated::class => [
            SendEventCreatedEmail::class,
        ],
        PaymentOverdue::class => [
            SendPaymentOverdueEmail::class,
        ],
        TicketUpdated::class => [
            SendTicketUpdatedEmail::class,
        ],
        PasswordReset::class => [
            SendPasswordResetEmail::class,
        ],
        TenantOwnerAdded::class => [
            SendTenantOwnerAddedEmail::class,
        ],
        VisitorApproved::class => [
            SendVisitorApprovedEmail::class,
        ],
        ReportGenerated::class => [
            SendReportGeneratedEmail::class,
        ],

        // P1-61: Access Pass, Bill (reactivated), Facility Booking
        AccessPassCreated::class => [
            SendAccessPassCreatedEmail::class,
        ],
        AccessPassRevoked::class => [
            SendAccessPassRevokedEmail::class,
            SendAccessPassRevokedSms::class,
            SendAccessPassRevokedWhatsApp::class,
        ],
        FacilityBookingStatusUpdated::class => [
            SendFacilityBookingStatusUpdatedEmail::class,
        ],
        \App\Events\EventChanged::class => [
            \App\Listeners\SendEventChangedEmail::class,
        ],
        SocietyDocumentPublished::class => [
            SendSocietyDocumentPublishedEmail::class,
            SendSocietyDocumentPublishedSms::class,
            SendSocietyDocumentPublishedWhatsApp::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}