<?php

namespace App\Support;

/**
 * The canonical list of business events that actually send a notification
 * somewhere in this codebase today - one entry per real, verified dispatch
 * point, not an aspirational list of events that should exist.
 *
 * This exists to make the bug this project kept hitting structurally hard
 * to reintroduce: three different times (Notice, the bill reminder command,
 * the service request emails), a real, already-dispatched call site turned
 * out to send nothing because no `EmailTemplate` row matched what it looked
 * up, or because `EmailNotificationService::shouldReceiveEmail()`'s
 * role-permission map had never heard of the slug. Each was found by
 * tracing one bug report at a time. `NotificationConfigIntegrityTest`
 * iterates this registry instead, so a *new* event added here without its
 * template or without a permission entry fails a test immediately, rather
 * than silently sending nothing until someone notices residents never got
 * an email.
 *
 * Two lookup styles coexist in this codebase (see `EmailTemplate` model):
 *   - `trigger_event`: matched by `EmailTemplate::getTemplateForEvent()`,
 *     used by `DispatchEventNotification` / `DynamicEmailService`.
 *   - `slug`, plus a role-permission check in `shouldReceiveEmail()`: used
 *     by `EmailNotificationService::sendByTrigger()`.
 * `lookup` below says which one a given event actually goes through -
 * mapping the existing two systems, not replacing them with a third.
 *
 * Adding a *new* event: register it here, add a migration seeding its
 * template(s) the same way the ones already in `database/migrations`
 * do, and - if it goes through the slug system - add its slug (or a
 * `prefix-*` wildcard, which `shouldReceiveEmail()` already supports) to
 * every role in that method's permission map that should receive it.
 * `NotificationConfigIntegrityTest` will fail until all three are done.
 */
class NotificationEvents
{
    public const GROUP_LOGIN_ACCOUNT    = 'login_account';
    public const GROUP_VISITOR_GATE     = 'visitor_gate';
    public const GROUP_SERVICE_COMPLAINT = 'service_complaint';
    public const GROUP_BILL_PAYMENT     = 'bill_payment';
    public const GROUP_IMPORTANT_SECURITY = 'important_security';

    public const LOOKUP_TRIGGER_EVENT = 'trigger_event';
    public const LOOKUP_SLUG          = 'slug';

    /**
     * @return array<string, array{
     *   group: string,
     *   lookup: string,
     *   templates: string[],
     *   channels: string[],
     *   dispatched_from: string,
     *   critical: bool,
     * }>
     */
    public static function all(): array
    {
        return [
            // ── DynamicEmailService / DispatchEventNotification (trigger_event) ──
            'user.registered' => [
                'group' => self::GROUP_LOGIN_ACCOUNT,
                'lookup' => self::LOOKUP_TRIGGER_EVENT,
                'templates' => ['user_registered'],
                'channels' => ['email'],
                'dispatched_from' => 'UserCreated event -> SendUserWelcomeEmail',
                'critical' => true,
            ],
            // Fires on password-reset *completion* (AuthController::reset,
            // via Laravel's Password::reset()), not on the reset-request
            // email - that one is Laravel's own ResetPassword notification,
            // outside this project's EmailTemplate pipeline entirely.
            'user.password_reset' => [
                'group' => self::GROUP_LOGIN_ACCOUNT,
                'lookup' => self::LOOKUP_TRIGGER_EVENT,
                'templates' => ['password_reset'],
                'channels' => ['email'],
                'dispatched_from' => 'AuthController::reset -> PasswordReset event -> SendPasswordResetEmail',
                'critical' => true,
            ],
            'payment.received' => [
                'group' => self::GROUP_BILL_PAYMENT,
                'lookup' => self::LOOKUP_TRIGGER_EVENT,
                'templates' => ['payment_success'],
                'channels' => ['email'],
                'dispatched_from' => 'PaymentReceived event -> SendPaymentConfirmationEmail',
                'critical' => true,
            ],
            'notice.published' => [
                'group' => self::GROUP_IMPORTANT_SECURITY,
                'lookup' => self::LOOKUP_TRIGGER_EVENT,
                'templates' => ['notice_published'],
                'channels' => ['email', 'sms'],
                'dispatched_from' => 'NoticePublished event -> SendNoticePublishedEmail + SendNoticePublishedSms',
                'critical' => true,
            ],
            'event.created' => [
                'group' => self::GROUP_IMPORTANT_SECURITY,
                'lookup' => self::LOOKUP_TRIGGER_EVENT,
                'templates' => ['event_created'],
                'channels' => ['email'],
                'dispatched_from' => 'EventCreated event -> SendEventCreatedEmail',
                'critical' => false,
            ],
            'event.changed' => [
                'group' => self::GROUP_IMPORTANT_SECURITY,
                'lookup' => self::LOOKUP_TRIGGER_EVENT,
                'templates' => ['event_changed'],
                'channels' => ['email'],
                'dispatched_from' => 'EventChanged event -> SendEventChangedEmail',
                'critical' => false,
            ],
            'complaint.status_updated' => [
                'group' => self::GROUP_SERVICE_COMPLAINT,
                'lookup' => self::LOOKUP_TRIGGER_EVENT,
                'templates' => ['ticket_updated'],
                'channels' => ['email'],
                'dispatched_from' => 'TicketUpdated event -> SendTicketUpdatedEmail',
                'critical' => true,
            ],
            'tenant_owner.added' => [
                'group' => self::GROUP_LOGIN_ACCOUNT,
                'lookup' => self::LOOKUP_TRIGGER_EVENT,
                'templates' => ['tenant_owner_added'],
                'channels' => ['email'],
                'dispatched_from' => 'TenantOwnerAdded event -> SendTenantOwnerAddedEmail',
                'critical' => false,
            ],
            'visitor.approved' => [
                'group' => self::GROUP_VISITOR_GATE,
                'lookup' => self::LOOKUP_TRIGGER_EVENT,
                'templates' => ['visitor_approved'],
                'channels' => ['email'],
                'dispatched_from' => 'VisitorApprovalService::decide -> VisitorApproved event -> SendVisitorApprovedEmail',
                'critical' => true,
            ],

            // ── Gate approval workflow (VisitorApprovalService -> VisitorWorkflowEvent -> VisitorNotifications) ──
            // The resident-facing "approved" message is `visitor.approved` above (VisitorApproved event), so an approval is
            // announced once. Checkout is in-app only: an e-mail per exit would flood residents and no template is approved.
            'visitor.request' => [
                'group' => self::GROUP_VISITOR_GATE,
                'lookup' => self::LOOKUP_TRIGGER_EVENT,
                'templates' => ['visitor_request'],
                'channels' => ['email', 'whatsapp'],
                'dispatched_from' => 'VisitorApprovalService::register -> VisitorWorkflowEvent(requested) -> VisitorNotifications',
                'critical' => true,
            ],
            'visitor.rejected' => [
                'group' => self::GROUP_VISITOR_GATE,
                'lookup' => self::LOOKUP_TRIGGER_EVENT,
                'templates' => ['visitor_rejected'],
                'channels' => ['email'],
                'dispatched_from' => 'VisitorApprovalService::decide -> VisitorWorkflowEvent(rejected) -> VisitorNotifications',
                'critical' => true,
            ],
            'visitor.override' => [
                'group' => self::GROUP_VISITOR_GATE,
                'lookup' => self::LOOKUP_TRIGGER_EVENT,
                'templates' => ['visitor_override'],
                'channels' => ['email', 'whatsapp'],
                'dispatched_from' => 'VisitorApprovalService::override -> VisitorWorkflowEvent(overridden) -> VisitorNotifications',
                'critical' => true,
            ],
            'visitor.checked_in' => [
                'group' => self::GROUP_VISITOR_GATE,
                'lookup' => self::LOOKUP_TRIGGER_EVENT,
                'templates' => ['visitor_checked_in'],
                'channels' => ['email'],
                'dispatched_from' => 'VisitorApprovalService::checkIn -> VisitorWorkflowEvent(checked_in) -> VisitorNotifications',
                'critical' => false,
            ],

            // ── EmailNotificationService::sendByTrigger (slug + role permission) ──
            'bill.due_reminder' => [
                'group' => self::GROUP_BILL_PAYMENT,
                'lookup' => self::LOOKUP_SLUG,
                'templates' => ['bill-due-reminder'],
                'channels' => ['email'],
                'dispatched_from' => 'bills:send-reminders (scheduled) -> EmailNotificationService::sendByTrigger',
                'critical' => true,
            ],
            'complaint.created' => [
                'group' => self::GROUP_SERVICE_COMPLAINT,
                'lookup' => self::LOOKUP_SLUG,
                'templates' => ['ticket-created'],
                'channels' => ['email'],
                'dispatched_from' => 'ComplaintController::store -> sendComplaintNotification',
                'critical' => false,
            ],
            // 'event.updated' used to be registered here: EventController::update called sendEventNotification(), which re-sent the
            // 'new-event' template (body: 'A new event has been created'). Updates now dispatch EventChanged ('event.changed' above),
            // so there is no separate 'event.updated' send any more and no rule to switch.
            // 'visitor.denied' (slug 'visitor-denied') used to be registered here: VisitorController::deny called
            // sendVisitorNotification(), which looks the recipient up through `$visitor->hostUser` - a relation Visitor does not
            // have (it is host()) - so the e-mail went to nobody. A rejection now dispatches `visitor.rejected` above, to the
            // residents and administrators who need it.
            'service_request.assigned' => [
                'group' => self::GROUP_SERVICE_COMPLAINT,
                'lookup' => self::LOOKUP_SLUG,
                'templates' => ['service-request-assigned'],
                'channels' => ['email'],
                'dispatched_from' => 'ServiceController::assignRequest -> sendServiceRequestNotification',
                'critical' => false,
            ],
            'service_request.in_progress' => [
                'group' => self::GROUP_SERVICE_COMPLAINT,
                'lookup' => self::LOOKUP_SLUG,
                'templates' => ['service-request-in_progress'],
                'channels' => ['email'],
                'dispatched_from' => 'ServiceController::updateRequestStatus -> sendServiceRequestNotification',
                'critical' => false,
            ],
            'service_request.completed' => [
                'group' => self::GROUP_SERVICE_COMPLAINT,
                'lookup' => self::LOOKUP_SLUG,
                'templates' => ['service-request-completed'],
                'channels' => ['email'],
                'dispatched_from' => 'ServiceController::updateRequestStatus -> sendServiceRequestNotification',
                'critical' => false,
            ],
            'service_request.cancelled' => [
                'group' => self::GROUP_SERVICE_COMPLAINT,
                'lookup' => self::LOOKUP_SLUG,
                'templates' => ['service-request-cancelled'],
                'channels' => ['email'],
                'dispatched_from' => 'ServiceController/Api\\ServiceApiController -> sendServiceRequestNotification',
                'critical' => false,
            ],

            // ── P1-61 additions: Access Pass, Bill, Facility Booking ──
            //
            // Payment and Society Event are deliberately NOT duplicated
            // here - `payment.received` and `event.created`/`event.updated`
            // above already cover them from a real dispatch site each; a
            // second registry entry for the same business domain would be
            // exactly the "invented event with no real operation behind it"
            // this registry exists to prevent.
            'access_pass.created' => [
                'group' => self::GROUP_VISITOR_GATE,
                'lookup' => self::LOOKUP_TRIGGER_EVENT,
                'templates' => ['access_pass_created'],
                'channels' => ['email'],
                'dispatched_from' => 'AccessPassController::store -> AccessPassCreated event -> SendAccessPassCreatedEmail',
                'critical' => false,
            ],
            // Security-relevant: Admin/Super Admin may revoke a pass a
            // resident created (authorizeOwnPass() allows it), so the
            // resident whose household staff lost gate access may not be
            // the one who just revoked it. Three channels - the first event
            // in this registry to prove email+SMS+WhatsApp all firing off
            // one real dispatch, not just documented as "possible".
            'access_pass.revoked' => [
                'group' => self::GROUP_IMPORTANT_SECURITY,
                'lookup' => self::LOOKUP_TRIGGER_EVENT,
                'templates' => ['access_pass_revoked'],
                'channels' => ['email', 'sms', 'whatsapp'],
                'dispatched_from' => 'AccessPassController::revoke -> AccessPassRevoked event -> SendAccessPassRevoked{Email,Sms,WhatsApp}',
                'critical' => true,
            ],
            // Reactivates the BillGenerated event/listener/template trio
            // that existed but was never dispatched (see
            // knownDeadEventClasses()'s history) - Admin\RentController::store()
            // is the one real bill-creation call site in the codebase
            // (confirmed by grep for MaintenanceBill::create across every
            // controller), and now dispatches it.
            'bill.generated' => [
                'group' => self::GROUP_BILL_PAYMENT,
                'lookup' => self::LOOKUP_TRIGGER_EVENT,
                'templates' => ['bill_generated'],
                'channels' => ['email'],
                'dispatched_from' => 'Admin\\RentController::store -> BillGenerated event -> SendBillGeneratedEmail',
                'critical' => true,
            ],
            'facility_booking.status_updated' => [
                'group' => self::GROUP_SERVICE_COMPLAINT,
                'lookup' => self::LOOKUP_TRIGGER_EVENT,
                'templates' => ['facility_booking_status_updated'],
                'channels' => ['email'],
                'dispatched_from' => 'FacilityBookingController::updateStatus -> FacilityBookingStatusUpdated event -> SendFacilityBookingStatusUpdatedEmail',
                'critical' => false,
            ],
            // Sent only when the admin ticks "Announce" (and picks channels)
            // on publish, and at most once per document version
            // (`announced_at`) - publishing is not itself an announcement,
            // so routine uploads never spam residents.
            'society_document.published' => [
                'group' => self::GROUP_IMPORTANT_SECURITY,
                'lookup' => self::LOOKUP_TRIGGER_EVENT,
                'templates' => ['society_document_published'],
                'channels' => ['email', 'sms', 'whatsapp'],
                'dispatched_from' => 'SocietyDocumentService::publish -> SocietyDocumentPublished event -> SendSocietyDocumentPublished{Email,Sms,WhatsApp}',
                'critical' => false,
            ],
        ];
    }

    /**
     * EmailTemplate rows that exist (society_id=null, status=active) but
     * that no entry in all() points at, because nothing in the codebase
     * actually dispatches a lookup for them. Each was confirmed by grepping
     * for every real call site of the method/column that would have to
     * reference it:
     *
     *   - password_reset / report_generated (trigger_event): §11 of
     *     CLAUDE.md records these as confirmed-dead events, kept for when
     *     "forgot password" / report generation get wired to a real
     *     dispatch, not regressions to fix now.
     *   - welcome-user, reset-password, new-bill-generated, bill-overdue,
     *     payment-received, new-notice (slug): each has a
     *     EmailNotificationService::send*() method, but every one of those
     *     methods is dead code - zero real call sites outside its own
     *     definition (new-notice's sendNoticeNotification() is reachable
     *     only from the dev-only `TestEmailSending` console command). The
     *     real pipelines for welcome/payment/notice run through the
     *     trigger_event system instead (user.registered, payment.received,
     *     notice.published above) - these slug templates are the leftover
     *     first draft, not a second active path.
     *   - event-updated (slug): never queried by anything.
     *   - new-event (slug): the 'A new event has been created' template that
     *     EventController::update used to re-send on EVERY save via
     *     sendEventNotification() (so a cancellation read as an announcement).
     *     Updates now dispatch EventChanged -> 'event.changed' with its own
     *     template, and creation uses 'event_created'. Nothing queries this slug
     *     any more; it is kept, not deleted, because a society may have edited it.
     *
     * `bill_generated` (P1-61) is no longer here - it moved to `all()` as
     * `bill.generated` once `Admin\RentController::store()` started
     * dispatching it for real; this list only holds rows nothing reaches.
     *
     * NotificationConfigIntegrityTest treats any OTHER society_id=null
     * active template with no registry entry as a new, unexplained orphan
     * and fails - this list is the one-time, reasoned exception, not a
     * blanket allowlist to keep extending.
     */
    public static function knownOrphanTemplateKeys(): array
    {
        return [
            'payment_overdue',
            'report_generated',
            'welcome-user',
            'reset-password',
            'new-bill-generated',
            'bill-overdue',
            'payment-received',
            'new-notice',
            'event-updated',
            'new-event',
            // superseded by `visitor_rejected` (see the note where 'visitor.denied' used to be registered); kept, not deleted,
            // because a society may have edited its copy
            'visitor-denied',
        ];
    }

    /**
     * Event classes with a listener registered in
     * `App\Providers\EventServiceProvider::$listen` that nothing in
     * `app/Http`, `app/Models`, `app/Console` or `app/Jobs` ever dispatches
     * (`::dispatch(...)` or `event(new ...)`) - confirmed by grep, not
     * inferred. Each still has an active template sitting behind it
     * (see knownOrphanTemplateKeys), which is exactly the "dead listener
     * pretending to be active" shape the architecture review asked to be
     * made structurally detectable: the listener, the event class and the
     * template all exist and look wired, but no business action anywhere
     * fires the event, so the pipeline never runs.
     *
     *   - PaymentOverdue: superseded by the real bill reminder path
     *     (`bills:send-reminders` -> `bill.due_reminder` above,
     *     slug-based). Nothing constructs this event class.
     *   - ReportGenerated: no report-generation feature dispatches it;
     *     there is no report-building code path in this app yet.
     *
     * `BillGenerated` (P1-61) is no longer here - `Admin\RentController::store()`
     * dispatches it for real now (see `all()`'s `bill.generated`).
     *
     * NotificationConfigIntegrityTest asserts this is the *complete* set of
     * registered-but-undispatched event classes - if a new one appears
     * (someone registers a listener for an event nobody fires), the test
     * fails until it is either wired to a real dispatch site or added here
     * with the same reasoning.
     */
    public static function knownDeadEventClasses(): array
    {
        return [
            \App\Events\PaymentOverdue::class,
            \App\Events\ReportGenerated::class,
        ];
    }

    /** Every template slug/trigger_event any registered event names, flattened. */
    public static function allTemplateKeys(): array
    {
        return array_values(array_unique(array_merge(...array_values(array_map(
            fn (array $e) => $e['templates'],
            self::all()
        )))));
    }
}
