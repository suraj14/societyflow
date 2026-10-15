<?php

namespace App\Support;

use App\Jobs\SendWhatsAppNotification;
use App\Models\Society;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * In-app (header bell) and WhatsApp for business events - the two channels
 * not already sent by an event's own email/SMS listeners. Each method
 * targets exactly the people the event's email goes to, so neither channel
 * widens who learns about a record.
 *
 * WhatsApp is queued only when the society has attached a template to that
 * notification type (Settings -> WhatsApp) and WhatsApp is on; otherwise it
 * is skipped without a delivery-log entry.
 */
class BusinessNotifications
{
    private const RESIDENT_ROLES = ['Owner', 'Villa Owner', 'Apartment Owner', 'Tenant'];

    public static function visitorApproved(object $visitor): void
    {
        $users = self::visitorApprovedRecipients($visitor);
        if ($users->isEmpty()) {
            return;
        }
        $when = $visitor->expected_entry_time ? Carbon::parse($visitor->expected_entry_time)->format('d M, h:i A') : '';

        InAppNotifier::send($users, 'visitor', 'Visitor approved', trim(($visitor->visitor_name ?? 'Your visitor') . ' can enter' . ($when ? " ({$when})" : '') . '.'), self::url('visitors.show', $visitor->id), 'fa-user-check');
        self::whatsapp('visitor', $users, $visitor->society_id, fn ($u) => [
            'resident_name' => $u->name, 'visitor_name' => $visitor->visitor_name ?? '', 'visit_date' => $when,
        ]);
    }

    /**
     * Who is told "your visitor was approved": the resident the visit was registered for (`created_by`, the earlier
     * shape of this event), else the people of the visited unit other than whoever approved - they already know. A
     * Visitor row has no created_by, so before this fallback an approval reached nobody at all.
     *
     * @return Collection<int,User>
     */
    public static function visitorApprovedRecipients(object $visitor): Collection
    {
        if (!empty($visitor->created_by)) {
            $user = User::find($visitor->created_by);

            return $user && $user->hasAnyRole(self::RESIDENT_ROLES) ? collect([$user]) : collect();
        }
        if (empty($visitor->flat_id) || !$visitor instanceof \App\Models\Visitor) {
            return collect();
        }

        $approver = (int) ($visitor->decided_by ?? $visitor->approved_by ?? 0);

        return VisitorRecipients::forVisitor($visitor)['residents']->reject(fn (User $u) => $u->id === $approver)->values();
    }

    /** Called directly from ComplaintController::store - never throws into it. */
    public static function complaintCreated(object $complaint): void
    {
        self::guard(fn () => self::notifyComplaintCreated($complaint));
    }

    private static function notifyComplaintCreated(object $complaint): void
    {
        InAppNotifier::send(
            self::roleUsers($complaint->society_id, ['Admin']),
            'complaint', 'New complaint',
            trim(($complaint->complaint_number ?? '') . ' ' . ($complaint->title ?? '')),
            self::url('complaints.show', $complaint->id), 'fa-ticket'
        );
    }

    public static function complaintUpdated(object $complaint): void
    {
        $creator = $complaint->created_by ? User::find($complaint->created_by) : null;
        if (!$creator) {
            return;
        }
        $status = ucwords(str_replace('_', ' ', (string) ($complaint->status ?? 'updated')));

        InAppNotifier::send([$creator], 'complaint', 'Complaint updated', ($complaint->complaint_number ?? 'Your complaint') . " is now {$status}.", self::url('complaints.show', $complaint->id), 'fa-ticket');
        self::whatsapp('complaint', [$creator], $complaint->society_id, fn ($u) => [
            'resident_name' => $u->name, 'complaint_ref' => $complaint->complaint_number ?? (string) $complaint->id,
            'complaint_title' => $complaint->title ?? '', 'complaint_status' => $status,
            // the office's own note when there is one, else what changed - both real data, never invented text
            'complaint_update' => trim((string) ($complaint->resolution_notes ?? '')) !== '' ? trim((string) $complaint->resolution_notes) : "Status changed to {$status}",
        ]);
    }

    /** A complaint was assigned to someone: tell them it is theirs (not when they assigned it to themselves). Never throws. */
    public static function complaintAssigned(object $complaint, User $assignee, ?User $by = null): void
    {
        self::guard(function () use ($complaint, $assignee, $by) {
            if ($by && $by->id === $assignee->id || (int) $assignee->society_id !== (int) $complaint->society_id) {
                return;
            }
            InAppNotifier::send(
                [$assignee], 'complaint', 'Complaint assigned to you',
                trim(($complaint->complaint_number ?? '') . ' ' . ($complaint->title ?? '')) . ($by ? " - assigned by {$by->name}." : '.'),
                self::url('complaints.show', $complaint->id), 'fa-ticket'
            );
        });
    }

    /** A resident asked for a service: the office needs to arrange it. Never throws. */
    public static function serviceRequestCreated(object $request): void
    {
        self::guard(fn () => InAppNotifier::send(
            self::roleUsers($request->society_id, ['Admin']),
            'service', 'New service request',
            ($request->service?->name ?? 'A service') . ' requested' . (($u = User::find($request->user_id)) ? " by {$u->name}" : '') . '.',
            self::url('service-requests.index'), 'fa-screwdriver-wrench'
        ));
    }

    /** Called directly from the service request controllers - never throws into them. */
    public static function serviceRequestUpdated(object $request, string $action): void
    {
        self::guard(fn () => self::notifyServiceRequestUpdated($request, $action));
    }

    private static function notifyServiceRequestUpdated(object $request, string $action): void
    {
        $user = $request->user_id ? User::find($request->user_id) : null;
        if (!$user) {
            return;
        }
        $service = $request->service?->name ?? 'Your service request';
        $status = ucwords(str_replace('_', ' ', $action));

        InAppNotifier::send([$user], 'service', 'Service request ' . strtolower($status), "{$service}: {$status}.", self::url('service-requests.index'), 'fa-screwdriver-wrench');
        self::whatsapp('service_request', [$user], $request->society_id, fn ($u) => [
            'resident_name' => $u->name, 'service_name' => $service, 'request_status' => $status,
        ]);
    }

    public static function paymentReceived(object $payment): void
    {
        $amount = self::money($payment->amount ?? 0);

        // Who is told "we received your payment": the person who paid; and when the office recorded it (no user on the
        // payment), the people responsible for that unit - the tenant for rent, the owner for anything else (everyone of
        // the unit when only the other kind exists). Never the admin who typed it in.
        $payers = collect();
        if ($payment->user_id) {
            $payers = collect([User::find($payment->user_id)])->filter();
        } elseif ($payment->flat_id) {
            $people = UserFlatResolver::peopleOfFlat((int) $payment->flat_id, (int) $payment->society_id);
            $isRent = strtolower((string) $payment->bill_type) === 'rent';
            $payers = ($isRent ? $people['tenants'] : $people['owners']);
            if ($payers->isEmpty()) {
                $payers = $people['tenants']->merge($people['owners'])->unique('id');
            }
        }

        foreach ($payers as $payer) {
            InAppNotifier::send([$payer], 'payment', 'Payment received', "We received your payment of {$amount}.", self::url('payments.show', $payment->id), 'fa-indian-rupee-sign');
        }
        self::whatsapp('payment', $payers->all(), $payment->society_id, fn ($u) => [
            'amount' => $amount, 'amount_number' => number_format((float) ($payment->amount ?? 0), 2), 'resident_name' => $u->name,
            'payment_date' => $payment->payment_date ? Carbon::parse($payment->payment_date)->format('d M Y') : now()->format('d M Y'),
            'receipt_ref' => $payment->receipt_number ?? (string) $payment->id,
        ]);

        $first = $payers->first();
        $staff = self::roleUsers($payment->society_id, ['Admin', 'Accountant'])->reject(fn ($u) => $payers->contains('id', $u->id));
        InAppNotifier::send($staff, 'payment', 'Payment received', "{$amount} received" . ($first ? " from {$first->name}" : '') . '.', self::url('payments.show', $payment->id), 'fa-indian-rupee-sign');
    }

    public static function billGenerated(object $bill): void
    {
        if (empty($bill->flat_id)) {
            return;
        }
        $residents = self::roleUsers($bill->society_id, self::RESIDENT_ROLES)
            ->filter(fn ($u) => UserFlatResolver::userHasFlat($u, (int) $bill->flat_id));
        $amount = self::money($bill->total_amount ?? $bill->amount ?? 0);
        $due = !empty($bill->due_date) ? Carbon::parse($bill->due_date)->format('d M Y') : '';

        InAppNotifier::send($residents, 'bill', 'New bill', "A bill of {$amount} has been generated" . ($due ? ", due {$due}" : '') . '.', self::url('payments.index'), 'fa-file-invoice-dollar');
        // "October 2026" from the bill's own month and year; left out (so a template that needs it is not sent) when the bill has none
        $month = $bill->month ?? null;
        $period = $month && ($bill->year ?? null)
            ? (is_numeric($month) ? Carbon::create((int) $bill->year, (int) $month, 1)->format('F Y') : trim("{$month} {$bill->year}"))
            : '';
        self::whatsapp('bill', $residents, $bill->society_id, fn ($u) => [
            'resident_name' => $u->name, 'amount' => $amount, 'due_date' => $due, 'bill_ref' => $bill->bill_number ?? (string) $bill->id,
            'amount_number' => number_format((float) ($bill->total_amount ?? $bill->amount ?? 0), 2),
        ] + ($period !== '' ? ['bill_period' => $period] : []));
    }

    public static function noticePublished(object $notice): void
    {
        $all = [...self::RESIDENT_ROLES, 'Admin'];
        $roles = is_array($notice->target_audience ?? null) && $notice->target_audience
            ? array_values(array_intersect($notice->target_audience, $all))
            : $all;
        if (!$roles) {
            return;
        }
        $users = self::roleUsers($notice->society_id, $roles);

        InAppNotifier::send($users, 'notice', 'New notice', (string) ($notice->title ?? 'A new notice was published'), self::url('notices.show', $notice->id), 'fa-bullhorn');
        self::whatsapp('notice', $users->filter(fn ($u) => $u->hasAnyRole(self::RESIDENT_ROLES)), $notice->society_id, fn ($u) => [
            'resident_name' => $u->name, 'notice_title' => (string) ($notice->title ?? ''),
        ]);
    }

    public static function eventCreated(object $event): void
    {
        // Same audience as SendEventCreatedEmail (one definition: EventAudience).
        $users = EventAudience::users($event);

        InAppNotifier::send($users, 'event', 'New society event', (string) ($event->event_name ?? 'A new event was announced'), self::url('events.show', $event->id), 'fa-calendar-days');
        self::eventWhatsApp($event, $users, 'New event', "created:{$event->id}");
    }

    /** WhatsApp for an event announcement/change, only where a template is attached to the `event` purpose. */
    private static function eventWhatsApp(object $event, iterable $users, string $what, string $dedupe): void
    {
        $when = $event->start_date ? Carbon::parse($event->start_date)->format('d M Y, h:i A') : '';
        // Identifies this save (see SendEventChangedEmail): a retried delivery dedupes, a later edit does not.
        $stamp = ($event->updated_at ? Carbon::parse($event->updated_at)->timestamp : 0) . '-' . ($event->start_date ? Carbon::parse($event->start_date)->format('YmdHi') : '0');

        $start = $event->start_date ? Carbon::parse($event->start_date) : null;
        $location = trim((string) ($event->location ?? ''));

        self::whatsapp('event', $users, $event->society_id, fn ($u) => [
            'resident_name' => $u->name, 'event_name' => (string) ($event->event_name ?? ''), 'event_date' => $when, 'event_status' => $what,
        ] + ($start ? ['event_day' => $start->format('d M Y'), 'event_time' => $start->format('h:i A')] : [])
          + ($location !== '' ? ['event_location' => $location] : []), "event:{$dedupe}:{$stamp}");
    }

    /** An announced event was cancelled / rescheduled / updated. Same audience as eventCreated. */
    public static function eventChanged(object $event, string $kind, string $summary): void
    {
        $title = match ($kind) {
            'cancelled'   => 'Event cancelled',
            'rescheduled' => 'Event rescheduled',
            default       => 'Event updated',
        };

        $users = EventAudience::users($event);

        InAppNotifier::send(
            $users,
            'event',
            $title,
            trim(($event->event_name ?? 'An event') . ' ' . $summary) . '.',
            self::url('events.show', $event->id),
            $kind === 'cancelled' ? 'fa-calendar-xmark' : 'fa-calendar-days'
        );
        self::eventWhatsApp($event, $users, match ($kind) { 'cancelled' => 'Cancelled', 'rescheduled' => 'Rescheduled', default => 'Updated' }, "{$kind}:{$event->id}");
    }

    public static function accessPassCreated(object $pass): void
    {
        $user = $pass->createdBy ?? null;
        self::whatsapp('access_pass', $user ? [$user] : [], $pass->society_id, fn ($u) => [
            'resident_name' => $u->name, 'person_name' => $pass->person_name ?? '', 'pass_status' => 'created',
        ]);
    }

    public static function accessPassRevoked(object $pass): void
    {
        InAppNotifier::send([$pass->createdBy ?? null], 'access_pass', 'Gate pass revoked', ($pass->person_name ?? 'A gate pass') . ' can no longer enter with this pass.', self::url('access-passes.show', $pass->id), 'fa-id-card');
    }

    /** A resident asked to book a facility: the office decides. Never throws. */
    public static function facilityBookingRequested(object $booking): void
    {
        self::guard(function () use ($booking) {
            $date = $booking->booking_date ? Carbon::parse($booking->booking_date)->format('d M Y') : '';
            $who  = User::find($booking->user_id)?->name;

            InAppNotifier::send(
                self::roleUsers($booking->society_id, ['Admin']),
                'facility',
                $booking->status === 'approved' ? 'New facility booking' : 'Facility booking request',
                ($booking->facility?->name ?? 'A facility') . ($date ? " on {$date}" : '') . ($who ? " by {$who}" : '') . '.',
                self::url('facility-bookings.show', $booking->id),
                'fa-calendar-check'
            );

            // And tell the person who asked that it was received (or, on a
            // facility that needs no approval, that it is confirmed). Skipped
            // when they are themselves one of the admins just told above.
            $requester = $booking->user_id ? User::find($booking->user_id) : null;
            if ($requester && !$requester->hasRole('Admin')) {
                $confirmed = $booking->status === 'approved';
                InAppNotifier::send(
                    [$requester],
                    'facility',
                    $confirmed ? 'Booking confirmed' : 'Booking request received',
                    ($booking->facility?->name ?? 'Your booking') . ($date ? " on {$date}" : '') . ($confirmed ? ' is confirmed.' : ' was received and is waiting for approval.'),
                    self::url('facility-bookings.show', $booking->id),
                    'fa-calendar-check'
                );
            }
        });
    }

    /**
     * A resident changed a request that is still pending. The office may
     * already have read the first version, so tell them it moved. Never throws.
     */
    public static function facilityBookingEdited(object $booking): void
    {
        self::guard(function () use ($booking) {
            $date = $booking->booking_date ? Carbon::parse($booking->booking_date)->format('d M Y') : '';
            $time = $booking->start_time && $booking->end_time
                ? ', ' . Carbon::parse($booking->start_time)->format('h:i A') . ' - ' . Carbon::parse($booking->end_time)->format('h:i A')
                : '';
            $who = User::find($booking->user_id)?->name;

            InAppNotifier::send(
                self::roleUsers($booking->society_id, ['Admin']),
                'facility',
                'Facility booking request changed',
                ($booking->facility?->name ?? 'A booking') . ($who ? " by {$who}" : '') . " is now for {$date}{$time}.",
                self::url('facility-bookings.show', $booking->id),
                'fa-calendar-check'
            );
        });
    }

    /** A resident cancelled their own booking. Never throws. */
    public static function facilityBookingCancelled(object $booking): void
    {
        self::guard(function () use ($booking) {
            $date = $booking->booking_date ? Carbon::parse($booking->booking_date)->format('d M Y') : '';
            $who  = User::find($booking->user_id)?->name;

            InAppNotifier::send(
                self::roleUsers($booking->society_id, ['Admin']),
                'facility',
                'Facility booking cancelled',
                ($booking->facility?->name ?? 'A booking') . ($date ? " on {$date}" : '') . ($who ? " was cancelled by {$who}" : ' was cancelled') . '.',
                // A request withdrawn from the web is deleted, so there is no page to open.
                ($booking->exists ?? true) ? self::url('facility-bookings.show', $booking->id) : self::url('facility-bookings.index'),
                'fa-calendar-xmark'
            );
        });
    }

    /**
     * A roster change that concerns one guard: they were given a shift (`assigned`), lost one (`removed`), were replaced
     * on one (`replaced`) or were marked absent for one (`absent`). Goes only to that guard, only when their account is
     * still active in the assignment's own society, and never to the admin who made the change. In-app only: there is
     * no approved email / SMS / WhatsApp template for rosters, so nothing external can be sent (and the kill switch is
     * irrelevant). Never throws into the roster action.
     */
    public static function guardShiftChanged(\App\Models\ShiftAssignment $assignment, string $kind, ?int $guardId = null, ?int $actorId = null): void
    {
        self::guard(function () use ($assignment, $kind, $guardId, $actorId) {
            $guard = User::query()->whereKey($guardId ?? $assignment->guard_user_id)
                ->where('society_id', $assignment->society_id)->where('status', 'active')->first();
            if (!$guard || ($actorId !== null && (int) $guard->id === (int) $actorId)) {
                return;
            }

            $assignment->loadMissing('shift', 'gate');
            $date = $assignment->date ? Carbon::parse($assignment->date)->format('d M Y') : '';
            $what = trim(($assignment->shift?->name ?? 'Shift') . ($assignment->gate?->name ? ' at ' . $assignment->gate->name : '') . ($date ? " on {$date}" : ''));

            [$title, $body, $icon] = match ($kind) {
                'assigned' => ['New shift assigned', "You are on {$what}.", 'fa-user-shield'],
                'removed'  => ['Shift cancelled', "You are no longer on {$what}.", 'fa-calendar-xmark'],
                'replaced' => ['Shift reassigned', "You have been replaced on {$what}.", 'fa-people-arrows'],
                'absent'   => ['Marked absent', "You were marked absent for {$what}.", 'fa-user-xmark'],
            };

            InAppNotifier::send([$guard], 'shift', $title, $body, self::url('shift.current'), $icon);
        });
    }

    public static function facilityBookingUpdated(object $booking): void
    {
        $status = ucfirst((string) $booking->status);
        $date = $booking->booking_date ? Carbon::parse($booking->booking_date)->format('d M Y') : '';
        InAppNotifier::send([$booking->user], 'facility', "Booking {$status}", ($booking->facility?->name ?? 'Your booking') . ($date ? " on {$date}" : '') . " is {$status}.", ($booking->exists ?? true) ? self::url('facility-bookings.show', $booking->id) : self::url('facility-bookings.index'), 'fa-calendar-check');
    }

    public static function societyDocumentPublished(object $document, Collection $recipients): void
    {
        InAppNotifier::send($recipients, 'document', 'New society document', "{$document->title} ({$document->version}) has been published.", self::url('society-rules.show', $document->id), 'fa-book-open');
    }

    // ── helpers ─────────────────────────────────────────────────────────────

    private static function guard(callable $fn): void
    {
        try {
            $fn();
        } catch (\Throwable $e) {
            Log::error('Business notification failed', ['class' => get_class($e), 'message' => mb_substr($e->getMessage(), 0, 200)]);
        }
    }

    private static function roleUsers(?int $societyId, array $roles): Collection
    {
        if (!$societyId) {
            return collect();
        }

        return User::where('society_id', $societyId)
            ->where('status', 'active')
            ->whereHas('roles', fn ($q) => $q->whereIn('name', $roles))
            ->get();
    }

    /** @param iterable<User|null> $users */
    private static function whatsapp(string $purpose, iterable $users, ?int $societyId, callable $values, ?string $dedupeKey = null): void
    {
        self::dispatchWhatsApp($purpose, $users, $societyId, $values, $dedupeKey);
    }

    /**
     * Queues one WhatsApp notification per person - and says what happened, so a caller can record an honest status
     * instead of assuming. Nothing is queued unless the society has switched WhatsApp on, holds a key and a Phone Number
     * ID, and has a sendable template (provider-approved, SocietyFlow's, switched on) for this notification type.
     * "queued" is not "delivered": the send itself, and its outcome, are WhatsAppService's and the delivery log's.
     *
     * @param  iterable<User|null> $users
     * @return array{status: string, count: int}  status: queued | skipped_not_configured | skipped_no_template
     *         | skipped_disabled | skipped_template_not_approved | skipped_template_not_sendable (another product's
     *         template) | skipped_no_recipient | skipped_duplicate
     */
    public static function dispatchWhatsApp(string $purpose, iterable $users, ?int $societyId, callable $values, ?string $dedupeKey = null): array
    {
        if (!$societyId) {
            return ['status' => 'skipped_not_configured', 'count' => 0];
        }

        // Ask the sender's own question - which template, and may it be sent - so what is reported is what will happen:
        // an older setting that names another product's template, or a template that is pending or switched off, is
        // "skipped", not "queued" for a job that would then refuse it.
        $resolved = \App\Services\WhatsAppService::resolveTemplate($purpose, $societyId, log: false);
        $template = $resolved['template'];
        $skip = [
            'no_template'  => 'skipped_no_template',
            'switched_off' => 'skipped_no_template',
            'not_approved' => 'skipped_template_not_approved',
            'not_sendable' => 'skipped_template_not_sendable',
        ][$resolved['check']] ?? null;
        if ($skip !== null) {
            return ['status' => $skip, 'count' => 0];
        }
        if (!(bool) SystemSetting::get('whatsapp', 'whatsapp_enabled', false, $societyId)) {
            return ['status' => 'skipped_disabled', 'count' => 0];
        }
        if (\App\Services\WhatsAppService::apiKey($societyId) === ''
            || (!$template?->phone_number_id && trim((string) SystemSetting::get('whatsapp', 'whatsapp_phone_number_id', '', $societyId)) === '')) {
            return ['status' => 'skipped_not_configured', 'count' => 0];
        }

        $societyRow = Society::find($societyId);
        $society = $societyRow?->name ?? '';
        // the society's own phone, offered to templates that print a contact number; absent when none is saved
        $societyPhone = trim((string) ($societyRow?->phone ?? ''));

        $queued = 0;
        $duplicates = 0;
        foreach ($users as $user) {
            if (!$user instanceof User || !$user->phone) {
                continue;
            }
            // The same message to the same person is queued once, however many times the
            // triggering event is delivered (a retried listener, a double submit).
            if ($dedupeKey !== null && !\Illuminate\Support\Facades\Cache::add("wa:{$dedupeKey}:{$user->id}", 1, now()->addDays(2))) {
                $duplicates++;
                continue;
            }
            try {
                SendWhatsAppNotification::dispatch($purpose, $user->phone, $values($user) + ['society_name' => $society] + ($societyPhone !== '' ? ['society_phone' => $societyPhone] : []), $societyId)->afterCommit();
                $queued++;
            } catch (\Throwable $e) {
                Log::warning('WhatsApp notification not queued', ['purpose' => $purpose, 'class' => get_class($e)]);
            }
        }

        return $queued > 0
            ? ['status' => 'queued', 'count' => $queued]
            : ['status' => $duplicates > 0 ? 'skipped_duplicate' : 'skipped_no_recipient', 'count' => 0];
    }

    private static function hasTemplate(string $purpose, int $societyId): bool
    {
        // A template switched on for this notification in Message Templates -> WhatsApp (the record is what counts now) ...
        if (\App\Models\WhatsAppTemplate::forPurpose($societyId, $purpose) !== null) {
            return (bool) SystemSetting::get('whatsapp', 'whatsapp_enabled', false, $societyId);
        }

        // ... or the older settings key some societies still carry.
        $assigned = SystemSetting::query()
            ->where('group', 'whatsapp')
            ->where('key', "whatsapp_{$purpose}_template_name")
            ->where('society_id', $societyId)
            ->whereNotNull('value')
            ->where('value', '!=', '')
            ->exists();

        return $assigned && (bool) SystemSetting::get('whatsapp', 'whatsapp_enabled', false, $societyId);
    }

    private static function money($amount): string
    {
        return "\u{20B9}" . number_format((float) $amount, 2);
    }

    private static function url(string $route, $param = null): ?string
    {
        try {
            return $param === null ? route($route) : route($route, $param);
        } catch (\Throwable) {
            return null;
        }
    }
}
