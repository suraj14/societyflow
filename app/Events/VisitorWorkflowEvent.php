<?php

namespace App\Events;

use App\Models\Visitor;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Something real happened to a visitor at the gate. `kind` is one of:
 *
 *   requested   a guard logged a visitor and the residents must decide
 *   approved    a resident or administrator allowed entry
 *   rejected    a resident or administrator turned the visitor away
 *   overridden  an administrator changed an earlier decision, with a reason
 *   checked_in  the guard let the visitor in (not merely approval)
 *   checked_out the visitor left
 *   escalated   nobody answered in the configured time; the office was told (the visitor is NOT admitted)
 *
 * Dispatched only by App\Services\VisitorApprovalService, after the change is stored, so a notification is never sent for
 * an action that did not happen. The one listener is BusinessNotificationSubscriber -> VisitorNotifications.
 *
 * @property-read array{reason?: ?string, role?: ?string, previous?: ?string, previous_by?: ?int} $meta
 */
class VisitorWorkflowEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Visitor $visitor,
        public string $kind,
        public ?int $actorId = null,
        public array $meta = [],
    ) {
    }
}
