# Local messaging: safe mode, real-delivery testing, and the notification matrix

Nothing in this document has been run against a real provider. Delivery is **not** confirmed for any channel.

## 1. Safe by default

`EXTERNAL_MESSAGING_ENABLED` defaults to **false** (config/messaging.php, .env.example). While it is false:

- mail is forced to the `log` driver, and a message from a society's saved SMTP settings is cancelled at the last moment;
- SMS, Fast2SMS and WhatsApp HTTP calls are not made (a "suppressed" result is returned);
- the delivery log records an e-mail as **Simulated - not sent**, never "Sent" (`App\Support\MailDelivery`);
- every "Send test" button answers "Not sent: external messaging is disabled".

Statuses you will see: `simulated` (nothing left this server), `sent` (handed to the mail server / SMS or WhatsApp provider accepted it -
**not** proof the person received it), `failed`, and for SMS/WhatsApp `skipped_*` (not attempted, with the reason). "Delivered" is shown
only where a provider reports it; none is wired for e-mail or SMS today.

There is one switch, so there is no separate "local override" that could leak into staging or production. Whether a server may
deliver is decided by its own `.env`. **Production must set `EXTERNAL_MESSAGING_ENABLED=true` explicitly when it is approved to send**
(this default changed from on to off on 2026-10-16: a deployment whose .env lacks the line now sends nothing).

## 2. Enabling real delivery on this computer (deliberate, temporary)

1. Back up `database/database.sqlite` (see MESSAGING_STAGING_AND_DEPLOYMENT.md). The dev DB holds real residents' numbers and e-mail addresses:
   **do not run a notice, bill or bulk action against it while delivery is on** - use a copy with blanked recipients, or one approved test society/user.
2. In `.env` set `EXTERNAL_MESSAGING_ENABLED=true` (do not change anything else).
3. `php artisan config:clear`, then restart `php artisan serve` (and `php artisan queue:work` if you use the database queue).
4. Use ONLY approved test recipients (your own mailbox / phone). Never a bulk audience.
5. To return to safe mode: set it back to `false`, `php artisan config:clear`, restart. Confirm: "Send test email" must say "Not sent".

## 2a. The test-recipient allow-list (use this whenever delivery is on)

The dev database holds real residents. To turn real delivery on **without any chance of messaging them**, also set in `.env`:

```
EXTERNAL_MESSAGING_ENABLED=true
EXTERNAL_MESSAGING_ALLOWLIST="your-test-mailbox@example.com,98XXXXXXXX"
```

With the variable present, real e-mail, SMS, Fast2SMS and WhatsApp requests go only to the listed addresses / 10-digit numbers; every other recipient is
**simulated** (logged as "Simulated - not sent", and the Send-test buttons say "not on the test allow-list"). Present but empty = nobody. **Absent = no
restriction**, which is what an approved production uses - so never copy this line to production by mistake, and never delete it on a machine holding real data.
Then `php artisan config:clear` and restart the server.
## 2b. What each status means (e-mail delivery log; SMS / WhatsApp use the same words in their "Skipped:" reason)

| Status | Meaning | Looks like |
|---|---|---|
| **Blocked - messaging disabled** | `EXTERNAL_MESSAGING_ENABLED` is off. Nothing left this computer. Not a settings error. | amber "Blocked - not sent" |
| **Blocked - not on the allow-list** | Delivery is on for a test but this recipient is not listed (or the list has an invalid entry, which blocks everyone). | amber "Blocked - not sent" |
| **Simulated** | Delivery is on but the mailer is the local log file. Nothing was sent. | grey "Simulated - not sent" |
| **Queued** | Handed to the queue; not yet attempted (only with a real queue worker). | |
| **Sent / accepted** | The mail server or SMS/WhatsApp provider accepted it. NOT proof the person received it. | green "Sent" |
| **Failed** | The provider or mail server refused it, or the settings are wrong. | red "Failed" |
| **Delivered** | Shown only where a provider reports delivery. None does for e-mail or SMS today, so it is never shown. | |

The Send-test buttons show a blocked result in **amber** with "Blocked by the safety switch - this is not a settings error", and a real
provider/settings problem in **red**, so the two can no longer be confused.

## 2c. The allow-list rules (fail closed, exact match)

- Entries are comma separated e-mail addresses (matched case-insensitively and exactly) and Indian mobile numbers (10 digits starting 6-9,
  with an optional `+91`, `91` or `0` prefix; any other country code is not a match).
- No substring, wildcard, domain or partial-number matching.
- **One malformed entry (an empty item, a bad address, a short number) makes the whole list invalid: nobody is allowed** until it is fixed,
  and the messages say "invalid entry". Present but empty also means nobody. Absent means no restriction.
- It is checked at the point a request would leave the server, for every route: SMTP mail, Fast2SMS (DLT, quick, one-time code, WhatsApp),
  Meta-style WhatsApp, BulkSMSPlans and the custom HTTP SMS API - so queued jobs, retries and the scheduler are covered too.

## 2d. First, ask the app what it thinks: `php artisan messaging:status`

Read-only, prints no secret, contacts no provider. It shows:

- whether delivery is **ON or OFF** and **where that comes from**: `.env`, the safe default (nothing set), or **a variable exported in the shell
  that started the process (which beats `.env`)**;
- whether the configuration is **cached** (then `.env` edits are ignored until `php artisan config:clear`);
- whether the test allow-list is absent, empty, **invalid** (blocks everyone) or valid (how many people; never their addresses);
- per society: SMTP host/port/encryption/sender and whether the password is set; SMS sender/route/key and every SMS template with its Message ID and any
  **wrong wording for its type**; WhatsApp phone-number id and, for each notification, whether it would send or is **BLOCKED** (BookMizo / unapproved template).

Why a local server can stay blocked after you edit `.env`:
1. **The server was started with the variable exported** (`$env:EXTERNAL_MESSAGING_ENABLED='false'` in the same shell) - a real environment variable wins over `.env`.
   Start it from a clean shell: `php artisan serve --host=0.0.0.0 --port=8123` with nothing exported.
2. **The server is still the old process.** A running server keeps the value it started with. Stop it and start it again after any `.env` change.
3. **Config is cached** (`bootstrap/cache/config.php`): `php artisan config:clear`.

Run `php artisan messaging:status` again after each step; the line "Where that comes from" tells you which of these it was.

## 2e. Templates that do not match their notification

A template's wording must be the wording of the notification it is saved for. The app now **refuses to save** (and **refuses to send**) an SMS template
whose text is the registered wording of a *different* notification (for example the "your bill is Rs. ..." text saved as "Society document published"):
the person would otherwise text every resident a bill notice when a document was published. An older template that already has this problem is listed
by `messaging:status` as **WRONG WORDING** and is skipped at send time; fix it in Message Templates (change its type, or replace its wording).

## 3. Manual acceptance checklist (each step needs your approved test recipient)

1. Settings -> Email: confirm host/port/encryption/sender are filled. Port 465 with "TLS" is correct in this Laravel (it opens an implicit-TLS
   `smtps` connection). Whether `info@bookmizo.com` is an address ZeptoMail has verified as a sender for Royal Eden is **unknown to the app**:
   check the Sender Addresses/Domains page of the ZeptoMail account; the app cannot see it.
2. Enable local delivery (section 2). "Send test email" to your mailbox: expect "The mail server accepted the test email" - then check the inbox.
   A server-side authentication error appears as "Test email failed" with the sanitised provider text.
3. Publish a test notice for one test tenant/owner only. Delivery log: one row per eligible active person of that society (active, valid address,
   one per address); the publishing Admin is one recipient among them, not the only one.
4. SMS: only with an active template that has a Fast2SMS **Message ID**; send to your own number; the log shows `sent` (provider accepted), not delivered.
5. WhatsApp: only with a SocietyFlow template that is approved; BookMizo templates are refused by design.
6. Switch each channel off in its settings and repeat: nothing is attempted; the log shows `skipped`.
7. Restore `EXTERNAL_MESSAGING_ENABLED=false`, clear config, restart: every test button says "Not sent".
8. Review `storage/logs/laravel.log` and the Delivery Logs page: no passwords, tokens or OTPs appear (OTP, password and reset values are scrubbed).

## 4. Notification matrix (what exists in the code today)

E-mail uses `EmailTemplate` rows (trigger event), WhatsApp/SMS use the society's own templates. "Rule" = the society's Communication Center toggle.

| Event | Trigger | Recipients | E-mail | SMS | WhatsApp | In-app |
|---|---|---|---|---|---|---|
| Notice published | NoticePublished | Active owners, tenants, admins of the society (per the notice audience), valid address, once each | `notice_published` | purpose `notice` (DLT 1277179196313898498, enforced wording) | `notice` | yes |
| Society document published (announce) | SocietyDocumentPublished | owners of the society | `society_document_published` | `document` | `document` | yes |
| Gate pass revoked | AccessPassRevoked | the pass owner | `access_pass_revoked` | `gate_pass` | `access_pass` | yes |
| Complaint created | ComplaintController | admins (in-app only) | `ticket-created` (slug) | - | - | yes |
| Complaint status updated | TicketUpdated | the resident who raised it | `ticket_updated` | **new:** `complaint` (DLT 1277179196367432151), needs a Message ID, never Quick SMS | `complaint` | yes |
| Bill generated (rent) | BillGenerated | residents of the billed unit | `bill_generated` | **new:** `bill` (DLT 1277179196734446136), whole-rupee amounts only | `bill` | yes |
| Bill due reminder | `bills:send-reminders` (scheduled) | residents of the unit | `bill-due-reminder` | - | - | - |
| Payment received | PaymentReceived | the payer / unit's responsible people | `payment_success` | - (no approved template) | `payment` | yes |
| Visitor request / approved / rejected / checked-in / override | VisitorApprovalService | residents of the unit, admins | `visitor_*` | - (no approved visitor SMS template) | `visitor`, `visitor_arrival` | yes |
| Facility booking status (approved/rejected/cancelled) | FacilityBookingStatusUpdated | the requester | `facility_booking_status_updated` | - | - | yes |
| Facility payment / refund recorded | FacilityBookingController | - | **none (no approved template)** | - | - | - |
| Event created / changed / cancelled | EventCreated / EventChanged | event audience | `event_created`, `event_changed` | - | `event` | yes |
| Festival announcement | publish with "Announce" | through the Events module | as Event | - | as Event | as Event |
| Login OTP | OTP flow | the person signing in | - | `login_otp` (own verified path; DLT 1277179196034433976, reference only) | - | - |
| User created / password reset | UserCreated / PasswordReset | the user | `user_registered`, `password_reset` | - | - | - |

Gaps that need a new approved provider template (none was invented): SMS for payment, visitor, facility booking, event; any facility
payment/refund or festival payment notice; maintenance-bill generation other than rent (the app has no generator for it).

## 5. SMS audiences

`App\Support\SmsAudience` resolves each audience on the server: Resident (owners + tenants), Guard, Admin, Committee (a committee designation
in Society Info), Staff, All Residents, All Team Members, Everyone in Society. Eligible = active, this society, not a Super Admin, a valid
10-digit mobile number; one entry per person and per number. There is no per-person SMS opt-out in the product, so none is applied.
The template form shows the live count; a **manual bulk-send action does not exist yet** (not built) - bulk audiences are for templates and
previews only.

## 6. DLT templates (screenshot-derived, UNVERIFIED against the provider account)

| Purpose | Name | DLT Template ID | Slots, in order |
|---|---|---|---|
| complaint | societyflow_complaint_update | 1277179196367432151 | complaint ref (alphanumeric), status (alphanumeric) |
| bill | societyflow_bill_generated | 1277179196734446136 | bill label e.g. "Maintenance October 2026" (alphanumeric), amount (number) |
| notice | societyflow_notice | 1277179196313898498 | notice title (alphanumeric) |
| login_otp | societyflow_login_otp | 1277179196034433976 | OTP (number) - reference only, no sample OTP stored |

The long DLT Template ID is not the Fast2SMS Message ID (a short number saved per template). **No Message ID is known for these**: enter the one
Fast2SMS shows next to each approved template. A template with a Message ID must read exactly like the registered text.
