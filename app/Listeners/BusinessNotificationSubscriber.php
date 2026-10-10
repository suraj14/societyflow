<?php

namespace App\Listeners;

use App\Events\AccessPassCreated;
use App\Events\AccessPassRevoked;
use App\Events\BillGenerated;
use App\Events\EventCreated;
use App\Events\FacilityBookingStatusUpdated;
use App\Events\NoticePublished;
use App\Events\PaymentReceived;
use App\Events\SocietyDocumentPublished;
use App\Events\TicketUpdated;
use App\Events\VisitorApproved;
use App\Support\BusinessNotifications;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Log;

/**
 * Header-bell entries (and WhatsApp where an event has no WhatsApp listener
 * of its own) for the business events the email listeners already handle.
 * A separate subscriber, wrapped per event, so a failure here can never stop
 * an email/SMS listener and vice versa.
 */
class BusinessNotificationSubscriber
{
    public function subscribe(Dispatcher $events): array
    {
        return [
            VisitorApproved::class              => 'onVisitorApproved',
            TicketUpdated::class                => 'onTicketUpdated',
            PaymentReceived::class              => 'onPaymentReceived',
            BillGenerated::class                => 'onBillGenerated',
            NoticePublished::class              => 'onNoticePublished',
            EventCreated::class                 => 'onEventCreated',
            \App\Events\EventChanged::class     => 'onEventChanged',
            AccessPassCreated::class           => 'onAccessPassCreated',
            AccessPassRevoked::class            => 'onAccessPassRevoked',
            FacilityBookingStatusUpdated::class => 'onFacilityBookingStatusUpdated',
            SocietyDocumentPublished::class     => 'onSocietyDocumentPublished',
        ];
    }

    public function onVisitorApproved(VisitorApproved $e): void { $this->safely(fn () => BusinessNotifications::visitorApproved($e->visitor)); }
    public function onTicketUpdated(TicketUpdated $e): void { $this->safely(fn () => BusinessNotifications::complaintUpdated($e->complaint)); }
    public function onPaymentReceived(PaymentReceived $e): void { $this->safely(fn () => BusinessNotifications::paymentReceived($e->payment)); }
    public function onBillGenerated(BillGenerated $e): void { $this->safely(fn () => BusinessNotifications::billGenerated($e->bill)); }
    public function onNoticePublished(NoticePublished $e): void { $this->safely(fn () => BusinessNotifications::noticePublished($e->notice)); }
    public function onEventCreated(EventCreated $e): void { $this->safely(fn () => BusinessNotifications::eventCreated($e->event)); }
    public function onEventChanged(\App\Events\EventChanged $e): void { $this->safely(fn () => BusinessNotifications::eventChanged($e->event, $e->kind, $e->summary())); }
    public function onAccessPassCreated(AccessPassCreated $e): void { $this->safely(fn () => BusinessNotifications::accessPassCreated($e->accessPass)); }
    public function onAccessPassRevoked(AccessPassRevoked $e): void { $this->safely(fn () => BusinessNotifications::accessPassRevoked($e->accessPass)); }
    public function onFacilityBookingStatusUpdated(FacilityBookingStatusUpdated $e): void { $this->safely(fn () => BusinessNotifications::facilityBookingUpdated($e->booking)); }
    public function onSocietyDocumentPublished(SocietyDocumentPublished $e): void { $this->safely(fn () => BusinessNotifications::societyDocumentPublished($e->document, $e->recipients())); }

    private function safely(callable $fn): void
    {
        try {
            $fn();
        } catch (\Throwable $ex) {
            Log::error('Business notification (in-app/WhatsApp) failed', ['class' => get_class($ex), 'message' => mb_substr($ex->getMessage(), 0, 200)]);
        }
    }
}
