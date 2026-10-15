<?php

namespace App\Support;

use App\Events\VisitorWorkflowEvent;
use App\Jobs\DispatchEventNotification;
use App\Models\Society;
use App\Models\User;
use App\Models\Visitor;
use App\Models\WhatsAppTemplate;
use App\Services\DynamicEmailService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Tells the right people about what happened to a visitor at the gate, on every channel the society has set up, each
 * channel on its own: a failure, a missing template or a switched-off channel in one never stops the others, and none of
 * them can undo the business action (this runs after the change is stored).
 *
 * Channels and what they honestly report (`handle()` returns one status per channel and writes the same to the visitor's
 * history as a `notified` row - no message text, no phone numbers or addresses):
 *
 *   in_app     sent(n)                                   the header bell; always on
 *   email      queued(n) | skipped_disabled | skipped_no_template | skipped_no_recipient | failed
 *   whatsapp   queued(n) | skipped_disabled | skipped_no_template | skipped_template_not_approved | skipped_not_configured
 *              | skipped_no_recipient | skipped_duplicate | failed
 *   sms        skipped_no_template - SocietyFlow has no DLT-approved SMS template for visitor events, so none is sent
 *
 * "queued" means handed to the queue / provider layer; it is never "delivered". Delivery evidence is in the delivery log
 * (email_logs) and only the provider's own receipt could confirm it - which Fast2SMS WhatsApp does not give back to us.
 *
 * The resident-facing "visitor approved" email / WhatsApp / bell entry is not sent from here: it goes through the existing
 * VisitorApproved event (SendVisitorApprovedEmail + BusinessNotifications::visitorApproved), which the service dispatches
 * next to this one, so one approval is never announced twice.
 */
class VisitorNotifications
{
    /** Email trigger events seeded by 2026_10_15_000002 (and the existing visitor_approved). */
    private const EMAIL = [
        'requested'  => 'visitor_request',
        'rejected'   => 'visitor_rejected',
        'overridden' => 'visitor_override',
        'checked_in' => 'visitor_checked_in',
    ];

    /** @return array<string,string> channel => status */
    public static function handle(VisitorWorkflowEvent $event): array
    {
        $visitor = Visitor::query()->with(['flat.building', 'flat.villaArea'])->find($event->visitor->id);
        if (!$visitor) {
            return [];
        }
        $actor = $event->actorId ? User::find($event->actorId) : null;
        $people = VisitorRecipients::forVisitor($visitor);
        $kind = $event->kind;

        $plan = self::plan($kind, $visitor, $actor, $people, $event->meta);

        // An approval is announced to the residents by the VisitorApproved event (email, WhatsApp, bell), dispatched by the
        // service next to this one; this class only tells the guard, so those two channels are reported as that event's.
        $viaApproved = $kind === 'approved';

        $outcome = [
            'in_app'   => self::guarded(fn () => self::inApp($plan['in_app'], $visitor)),
            'email'    => $viaApproved ? 'left_to_visitor_approved_event' : self::guarded(fn () => self::email($kind, $visitor, $actor, $plan['email'], $event->meta)),
            'whatsapp' => $viaApproved ? 'left_to_visitor_approved_event' : self::guarded(fn () => self::whatsapp($kind, $visitor, $plan['residents'])),
            // there is no approved SMS template purpose for visitor events: said plainly rather than faked
            'sms'      => 'skipped_no_template',
        ];

        self::record($visitor, $kind, $actor, $outcome, $people['linked']);

        return $outcome;
    }

    /**
     * Who is told what on each channel, per kind of event.
     *
     * @return array{in_app: list<array{users: Collection, title: string, body: string, icon: string}>, email: Collection, residents: Collection}
     */
    private static function plan(string $kind, Visitor $v, ?User $actor, array $people, array $meta): array
    {
        /** @var Collection $residents */
        $residents = $people['residents'];
        $admins = $people['admins'];
        $requester = $people['requester'];
        $notActor = fn (Collection $c) => $actor ? $c->reject(fn ($u) => $u->id === $actor->id)->values() : $c;
        $notRequester = fn (Collection $c) => $requester ? $c->reject(fn ($u) => $u->id === $requester->id)->values() : $c;

        $unit = self::unit($v);
        $who = trim(($v->visitor_name ?: 'A visitor') . ($v->visitor_type && $v->visitor_type !== 'guest' ? ' (' . $v->visitor_type . ')' : ''));
        $by = $actor?->name ?? 'someone';
        $reason = trim((string) ($meta['reason'] ?? ''));

        $inApp = [];
        $email = collect();
        // who a WhatsApp message goes to when this kind sends one: the residents of the unit, never the person who acted
        $whatsapp = $notActor($residents);

        switch ($kind) {
            case 'requested':
                $body = "{$who} is at the gate for {$unit}." . ($v->purpose ? " Purpose: {$v->purpose}." : '') . ' Please allow or deny.';
                if ($people['linked']) {
                    $inApp[] = ['users' => $notActor($residents)->merge($notActor($admins))->unique('id'), 'title' => 'Visitor at the gate', 'body' => $body, 'icon' => 'fa-user-clock'];
                } else {
                    // nobody is linked to the unit: never invent an owner - the office decides, the guard is told why
                    $inApp[] = ['users' => $notActor($admins), 'title' => 'Visitor at the gate - no owner linked', 'body' => "{$who} is at the gate for {$unit}, but no owner or tenant account is linked to it, so nobody could be asked. Please decide.", 'icon' => 'fa-user-clock'];
                    if ($requester) {
                        $inApp[] = ['users' => collect([$requester]), 'title' => 'No owner linked', 'body' => "No owner or tenant is linked to {$unit}. The administrators have been asked to decide about {$who}.", 'icon' => 'fa-circle-info'];
                    }
                }
                $email = $notActor($residents)->merge($notActor($admins))->unique('id');
                break;

            case 'approved':
                // the resident-facing email / WhatsApp / bell entry is the VisitorApproved event's; the guard hears here
                if ($requester) {
                    $inApp[] = ['users' => collect([$requester]), 'title' => 'Visitor approved', 'body' => "{$who} for {$unit} was approved by {$by}. You can check them in.", 'icon' => 'fa-user-check'];
                }
                break;

            case 'rejected':
                $text = "{$who} for {$unit} was turned away by {$by}" . ($reason !== '' ? ": {$reason}" : '') . '. Do not let them in.';
                $audience = $notActor($residents)->merge($notActor($admins))->when($requester, fn ($c) => $c->push($requester))->unique('id');
                $inApp[] = ['users' => $audience, 'title' => 'Visitor rejected', 'body' => $text, 'icon' => 'fa-user-xmark'];
                $email = $notActor($notRequester($residents))->merge($notActor($admins))->unique('id');
                break;

            case 'overridden':
                $now = $v->approval_status === 'allowed' ? 'allowed' : 'denied';
                $text = "Administrator {$by} changed the decision on {$who} for {$unit}: now {$now}. Reason: {$reason}";
                $audience = $residents->merge($notActor($admins))->when($requester, fn ($c) => $c->push($requester))->unique('id');
                $inApp[] = ['users' => $audience, 'title' => 'Visitor decision changed', 'body' => $text, 'icon' => 'fa-scale-balanced'];
                $email = $residents->merge($notActor($admins))->unique('id');
                break;

            case 'checked_in':
                $inApp[] = ['users' => $residents, 'title' => 'Visitor entered', 'body' => "{$who} has entered to visit {$unit}.", 'icon' => 'fa-door-open'];
                $email = $residents;
                break;

            case 'checked_out':
                $inApp[] = ['users' => $residents, 'title' => 'Visitor left', 'body' => "{$who} has left {$unit}.", 'icon' => 'fa-door-closed'];
                break;

            case 'escalated':
                $minutes = (int) ($meta['minutes'] ?? 0);
                $text = "{$who} has been waiting at the gate for {$unit}" . ($minutes ? " for {$minutes} minutes" : '') . ' with no answer. They have not been admitted - please decide.';
                $inApp[] = ['users' => $admins->when($requester, fn ($c) => $c->push($requester))->unique('id'), 'title' => 'Visitor waiting - no answer', 'body' => $text, 'icon' => 'fa-hourglass-half'];
                break;
        }

        return ['in_app' => $inApp, 'email' => $email, 'residents' => $whatsapp];
    }

    private static function inApp(array $entries, Visitor $visitor): string
    {
        $url = self::url($visitor);
        $sent = 0;
        foreach ($entries as $e) {
            $sent += InAppNotifier::send($e['users'], 'visitor', $e['title'], $e['body'], $url, $e['icon']);
        }

        return $entries === [] ? 'skipped_no_recipient' : ($sent > 0 ? "sent({$sent})" : 'skipped_no_recipient');
    }

    private static function email(string $kind, Visitor $visitor, ?User $actor, Collection $users, array $meta): string
    {
        $trigger = self::EMAIL[$kind] ?? null;
        if ($trigger === null) {
            return 'skipped_no_template';
        }
        $societyId = (int) $visitor->society_id;
        if (!NotificationRules::isEnabled($trigger, 'email', $societyId)) {
            return 'skipped_disabled';
        }
        if (!DynamicEmailService::hasTemplateForEvent($trigger, $societyId)) {
            return 'skipped_no_template';
        }

        // Text a guard or a resident typed is put into an HTML e-mail: angle brackets and ampersands are escaped so a name can
        // never inject markup or a link. The visitor's number is masked - the resident knows who they expect, and the delivery
        // log keeps these variables.
        $text = fn (?string $v) => htmlspecialchars((string) $v, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $society = Society::find($societyId)?->name ?? '';
        $base = [
            'society_name' => $text($society),
            'visitor_name' => $text($visitor->visitor_name),
            'visitor_phone' => \App\Services\SmsService::maskPhone((string) $visitor->visitor_phone),
            'visitor_type' => $text(ucfirst((string) $visitor->visitor_type)),
            'unit' => $text(self::unit($visitor)),
            'purpose' => trim((string) $visitor->purpose) !== '' ? $text($visitor->purpose) : 'Not stated',
            'reason' => trim((string) ($meta['reason'] ?? '')) !== '' ? $text($meta['reason']) : 'No reason given',
            'actor_name' => $text($actor?->name ?? 'The society office'),
            'decision' => $visitor->approval_status === 'allowed' ? 'allowed' : 'denied',
            'visit_time' => $visitor->expected_entry_time ? $visitor->expected_entry_time->format('d M Y, h:i A') : '',
            'view_url' => self::url($visitor) ?? '',
        ];
        // identifies this occurrence: a retried delivery dedupes, a later override of the same visitor does not
        $stamp = $visitor->override_at?->timestamp ?? $visitor->decided_at?->timestamp ?? $visitor->actual_entry_time?->timestamp ?? $visitor->created_at?->timestamp ?? 0;

        $queued = 0;
        foreach ($users as $user) {
            if (!$user instanceof User || !filter_var((string) $user->email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            DispatchEventNotification::dispatch(
                $trigger,
                ['email' => $user->email, 'name' => $user->name, 'role' => $user->getRoleNames()->first() ?? 'user'],
                $base + ['user_name' => $text($user->name)],
                $societyId,
                "{$trigger}:{$visitor->id}:{$stamp}:{$user->email}",
            )->afterCommit();
            $queued++;
        }

        return $queued > 0 ? "queued({$queued})" : 'skipped_no_recipient';
    }

    private static function whatsapp(string $kind, Visitor $visitor, Collection $users): string
    {
        // Two events have an approved wording: a guard logged a visitor ('visitor_arrival' - "your visitor has arrived ...
        // contact the gate") and an administrator changed a decision to allowed ('visitor' - "your visitor has been approved").
        // Rejection, entry and exit have none, and nothing else is invented in their place.
        [$purpose, $rule] = match (true) {
            $kind === 'requested'                                              => ['visitor_arrival', 'visitor_request'],
            $kind === 'overridden' && $visitor->approval_status === 'allowed'  => ['visitor', 'visitor_override'],
            default                                                            => [null, null],
        };
        if ($purpose === null) {
            return 'skipped_no_template';
        }
        $societyId = (int) $visitor->society_id;

        if (!NotificationRules::isEnabled($rule, 'whatsapp', $societyId)) {
            return 'skipped_disabled';
        }
        $when = $visitor->expected_entry_time ? $visitor->expected_entry_time->format('d M, h:i A') : '';
        $stamp = $visitor->override_at?->timestamp ?? $visitor->created_at?->timestamp ?? 0;

        $result = BusinessNotifications::dispatchWhatsApp($purpose, $users, $societyId, fn (User $u) => [
            'resident_name' => $u->name, 'visitor_name' => (string) $visitor->visitor_name, 'visit_date' => $when, 'unit_name' => self::unit($visitor),
        ], "{$rule}:{$visitor->id}:{$stamp}");

        return $result['count'] > 0 ? "queued({$result['count']})" : $result['status'];
    }

    /** One history row per event: the channel outcomes only. Never throws. */
    private static function record(Visitor $visitor, string $kind, ?User $actor, array $outcome, bool $linked): void
    {
        try {
            $by = $actor ?? User::find($visitor->requested_by ?? $visitor->host_user_id);
            if (!$by) {
                return;
            }
            $parts = [];
            foreach ($outcome as $channel => $status) {
                $parts[] = "{$channel}={$status}";
            }
            $visitor->addLog($by, 'notified', "{$kind}: " . implode('; ', $parts) . ($linked ? '' : '; no resident linked to the unit'));
        } catch (\Throwable $e) {
            Log::warning('Visitor notification outcome not recorded', ['class' => get_class($e)]);
        }
    }

    private static function guarded(callable $fn): string
    {
        try {
            return $fn();
        } catch (\Throwable $e) {
            Log::error('Visitor notification channel failed', ['class' => get_class($e), 'message' => mb_substr($e->getMessage(), 0, 200)]);

            return 'failed';
        }
    }

    private static function unit(Visitor $v): string
    {
        return $v->flat ? VisitorPropertyScope::unitLabel($v->flat) : 'the property';
    }

    private static function url(Visitor $v): ?string
    {
        try {
            return route('visitors.show', $v->id);
        } catch (\Throwable) {
            return null;
        }
    }
}
