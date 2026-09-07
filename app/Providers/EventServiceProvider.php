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

// Application Listeners
use App\Listeners\SendUserWelcomeEmail;
use App\Listeners\SendBillGeneratedEmail;
use App\Listeners\SendPaymentConfirmationEmail;
use App\Listeners\SendNoticePublishedEmail;
use App\Listeners\SendEventCreatedEmail;
use App\Listeners\SendPaymentOverdueEmail;
use App\Listeners\SendTicketUpdatedEmail;
use App\Listeners\SendPasswordResetEmail;
use App\Listeners\SendTenantOwnerAddedEmail;
use App\Listeners\SendVisitorApprovedEmail;
use App\Listeners\SendReportGeneratedEmail;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
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