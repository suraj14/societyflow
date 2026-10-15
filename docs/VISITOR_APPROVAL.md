# Visitor approval at the gate

A guard logs a visitor for a unit; the unit's owner / tenant (or an administrator) allows or turns them away; the guard checks them in and out; an administrator can change a decision, with a reason. One service, `App\Services\VisitorApprovalService`, does all of it for the mobile API, the web pages and the guard dashboard - so the rules are the same everywhere.

## States
`approval_status` (pending / allowed / denied) and `entry_status` (pending / entered / exited) are unchanged. The apps show five states derived from the pair (`Visitor::workflowStatus()`): `pending_approval`, `approved`, `rejected`, `checked_in`, `checked_out`.

## Who may do what
| Action | Allowed | Refused |
|---|---|---|
| Log a visitor | Guard / Staff / Admin for any unit **in their society**; a resident for their own unit (pre-approved - nothing to decide) | a unit of another society (422) |
| Allow / deny | an owner or tenant **of that unit** (`VisitorPropertyScope`), an Admin of the society | Guard and Staff (403, also for their own request); a resident of another unit and any other society (404) |
| Check in / out | Guard, Staff, Admin; a resident for the guest they invited themselves | everyone else (403); a visitor not allowed (422); a denied visitor can never be checked in |
| Override | Admin only, written reason (5+ characters), until the visitor has entered | everyone else (403); same decision again (409); after entry (409) |

Every change is a compare-and-swap `UPDATE ... WHERE approval_status = ? AND entry_status = ?`: if an owner and an administrator tap together, exactly one wins and the other gets a 409 naming the winner. The same person tapping twice is the same decision (no second notification).

## Who is told, on which channel
Recipients are found through property links only (`VisitorRecipients`): the active tenant's account, the owner's account (`flats.owner_id`, else the owner record's own address), legacy household accounts - active, same society, resident role, and able to decide for that unit. A unit with nobody linked tells the administrators, and tells the guard so; nobody is invented.

| Event | Bell (in-app) | E-mail | WhatsApp | SMS |
|---|---|---|---|---|
| Guard logs a visitor | owners/tenants of the unit + admins | `visitor_request` | `visitor_arrival` (needs an active, approved template + key + Phone Number ID) | not sent: no approved visitor SMS template exists |
| Allowed | guard; the *other* people of the unit | `visitor_approved` (existing) to the other people of the unit | `visitor` (existing template) to them | not sent |
| Denied | guard, other residents, admins | `visitor_rejected` | none: no approved template | not sent |
| Override | residents, guard, other admins | `visitor_override` | `visitor` if now allowed | not sent |
| Checked in | residents | `visitor_checked_in` | none | not sent |
| Checked out | residents | none (would be one e-mail per exit) | none | not sent |
| No answer in time | admins + guard | none | none | not sent |

Each channel is independent: a failure or a missing template in one never blocks another or undoes the business action. Every event writes one `notified` row in the visitor's history with the outcome per channel (`sent(n)`, `queued(n)`, `skipped_disabled`, `skipped_no_template`, `skipped_template_not_approved`, `skipped_template_not_sendable`, `skipped_not_configured`, `skipped_no_recipient`, `failed`) - no message text, no addresses, no numbers. "queued" means handed to the queue / provider layer; it is **never** "delivered" (Fast2SMS WhatsApp sends no receipt we consume).

## No answer
Settings -> Security -> *Visitor no-response time* (default 10 minutes, 0 = off; `security / visitor_no_response_minutes`). `php artisan visitors:escalate-unanswered` (scheduled every minute) tells the administrators and the guard **once**. Time passing never admits anyone.

## Operator steps still needed for WhatsApp
1. Message Templates -> WhatsApp: make `society_visitor_approved` active for *Visitor approved* (this also switches off the BookMizo template currently on it) and set `society_visitor_arrived` to *Visitor at the gate* and make it active. Both are provider-approved; nothing is switched on by the code.
2. Settings -> WhatsApp -> Verify configuration must pass (three stages), then one approved real test send to a named staging number.
3. Society 1's older `whatsapp_*_template_name` settings still name BookMizo templates; the sender refuses them and the delivery status says so.

Tests: `tests/Feature/VisitorApprovalWorkflowTest.php`, `ComplaintAssignmentNotificationTest.php`, `WhatsAppProviderContractTest.php` (retry rules); Flutter `test/visitor_approval_test.dart`.
