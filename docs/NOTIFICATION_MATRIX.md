# Notification matrix: action -> audience -> channel -> template -> conditions -> result (2026-10-21)

Built from the code (every `::dispatch` / `BusinessNotifications::` / sender call under `app/`), not from memory. "Web" = Blade controllers, "Mobile" = `/api/v1`.
Every external send passes the same two gates (kill switch, then the exact test allow-list) and then `RecipientEligibility` for e-mail: a valid address, an
**active** account of the same society, not a Super Admin (account e-mails excepted). Results are logged as Sent (accepted by the server/provider, never "delivered"),
Blocked, Simulated, Skipped (with the reason) or Failed.

| # | Action (where it fires) | Audience | E-mail (template) | SMS | WhatsApp | In-app | Conditions / notes |
|---|---|---|---|---|---|---|---|
| 1 | **Notice published** (web publish / edit-to-published) | Active owners, tenants, admins of the society (per the notice's target audience), one per address / number | `notice_published` | purpose `notice` (needs an active template with a Message ID; else the legacy single DLT slot) | `notice` (needs an active SocietyFlow template) | yes | No mobile path creates notices (read-only API). |
| 2 | **Event created** (web, status pending) / **changed, rescheduled, cancelled** | Active owners in the event's audience | `event_created`, `event_changed` | none (no approved template) | `event` | yes | No reminder exists in the code. Festival "announce" reuses this. |
| 3 | **Complaint created** (web, villa-owner request, **mobile - added this round**) | The office (admins/staff) | `ticket-created` (slug) | none | none | yes (admins) | Mobile used to notify nobody. |
| 4 | **Complaint status updated** (web, **mobile admin API**) | The resident who raised it (+ staff e-mail) | `ticket_updated` | purpose `complaint` (needs template + Message ID: none today) | `complaint` | yes | |
| 5 | **Complaint assigned** (web) | The assignee | none | none | none | yes | Not when assigning to oneself. |
| 6 | **Bill generated** (rent bill, Admin -> Rent only) | Residents of the billed unit (+ admins) | `bill_generated` | purpose `bill` (template + whole-rupee amount; none today) | `bill` | yes | No maintenance-bill generator exists. |
| 7 | **Bill due reminder** (scheduler `bills:send-reminders --days=3` and `--days=0`) | Residents of the unit | `bill-due-reminder` (slug) | none | none | none | Runs only if a scheduler is running (none is on this PC). |
| 8 | **Payment received** (web record / approve) | The payer, or the unit's responsible people (+ admins/accountants in-app) | `payment_success` | none | `payment` | yes | Maintenance refunds do not exist. |
| 9 | **Visitor at the gate** (guard, web **and mobile**, one service) | Owner/tenant of the unit, admins | `visitor_request` | none | `visitor_arrival` | yes | |
| 10 | **Visitor approved / rejected / override / checked in** (resident, admin, guard) | Resident, admins, requester | `visitor_approved`, `visitor_rejected`, `visitor_override`, `visitor_checked_in` | none | `visitor` (approved), override | yes | Approval is separate from check-in; repeats are idempotent. Unanswered requests: `visitors:escalate-unanswered` (in-app). |
| 11 | **Gate pass created** (web) | The pass owner | `access_pass_created` | none | none | yes | Mobile cannot create or revoke passes. |
| 12 | **Gate pass revoked** (web) | The pass owner | `access_pass_revoked` | purpose `gate_pass` | `access_pass` | yes | |
| 13 | **Society document published** (with "Announce") | Active residents (+ guards if visible) | `society_document_published` | purpose `document` | `document` | yes | Society 6's saved "document" SMS template holds the bill wording: now refused. |
| 14 | **Facility booking requested / edited / cancelled** (web, villa owner, **mobile**) | The office | none | none | none | yes | |
| 15 | **Facility booking approved / rejected / cancelled by the office** (web, mobile admin) | The requester | `facility_booking_status_updated` | none | none | yes | "Approved" is not "paid". |
| 16 | **Facility payment / refund recorded** | - | **none** | none | none | none | No approved template exists: a gap, not invented. |
| 17 | **Tenant / owner added** | The new person (+ owner of the unit, admin) | `tenant_owner_added` | none | none | none | |
| 18 | **Account created** (admin creates a user) | The new user | `user_registered` (temporary password never logged) | none | none | none | |
| 19 | **Password reset** | The user | Laravel's reset mail (+ `password_reset` on completion) | none | none | none | |
| 20 | **Login one-time code** (mobile `OtpAuthController`) | The person signing in | none | `login_otp` (own verified path; never exposed outside a dev server) | optional template | none | Unknown numbers get the same answer as known ones. |
| 21 | **Guard shift change** | Guard / office | none | none | none | yes | |

## Notice publication: exact semantics (verified 2026-10-21, `NoticeOwnerNotificationTest`)

- `NoticePublished` fires only on **create as published**, **draft/pending -> published** (update) and **approve**. **Editing an already-published notice sends nothing**
  (no duplicate broadcast, and also no re-notification - there is no "notify again" action).
- Audience (e-mail, SMS, in-app): active accounts of the notice's own society holding `Owner`, `Villa Owner`, `Apartment Owner`, `Tenant` (+ `Admin` for e-mail/SMS/in-app);
  one e-mail per address, one SMS per number, never across societies (the same mobile number exists in four societies in the dev DB).
- E-mail de-duplication key `notice_published:<notice id>:<address>`: only a `sent` row created on/after 2026-10-17 (`EmailLog::HONEST_STATUS_SINCE`) counts as delivered.
  Earlier "sent" rows only meant "written to the log" and no longer suppress a real send; blocked / simulated / failed / skipped rows never do. Logs are not rewritten.
- **The notice form's "send e-mail" / "send SMS" checkboxes are stored but not read by any listener** (default unticked): every published notice notifies regardless.
  Open product decision - honouring them would stop notices going out when the box is left unticked.
- The SMS/WhatsApp test seams (`SmsService`/`WhatsAppService::$transportOverride`) now sit behind the allow-list, so a simulation shows what the real path would send.
- WhatsApp for a notice needs an active, approved SocietyFlow template on purpose `notice`: none is active today (blocker, owner action).

Gaps that need an approved provider template first (none was invented): SMS for payment, visitor, facility, event; any facility payment/refund notice;
WhatsApp for facility; e-mail for facility payment/refund and festival payments.
