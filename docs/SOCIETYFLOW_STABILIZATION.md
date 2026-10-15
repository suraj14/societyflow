# SocietyFlow — stabilization, bug fix and web/mobile parity (2026-10-12)

Branch `production-readiness`. Release status: **READY FOR STAGING, NOT READY FOR PRODUCTION.**
Every row below has a regression test in `tests/Feature/` (Flutter ones in `societyflowmob/test/`). "Mocked" means the
provider HTTP layer is faked; **no live SMS / WhatsApp / email / payment was sent in any test.**

## Defects fixed

| # | Feature | Repro | Root cause | Fix (files) | Regression test | Status |
|---|---|---|---|---|---|---|
| 1 | Guard Shift Assignments HTTP 422 | Admin → Shift Assignments → Assign with a guard already on that shift/date, a past date, or an inactive guard | Controller called a bare `abort(422)`; the user got an empty error page, not a field message. Validation was not society-scoped | `ShiftAssignmentException`, `GuardShiftService` (ensure / isAvailable / ensureNoConflict), `Admin/ShiftAssignmentController`, `admin/shift-assignments/{create,index}` | `ShiftAssignmentFormTest`, `GuardShiftApiTest`, `GuardShiftAssignmentTest` | fixed |
| 2 | Tenant Quick Actions (Pay Rent, Raise Request, View Notices, My Unit) | Tenant dashboard → click each | Tiles were not wired to real routes; My Unit used the wrong tenancy; the form wrapper swallowed `HttpException` | `tenant/dashboard.blade.php`, `DashboardController`, `HandlesFormSubmissions`, `tenant/rent/index` | `TenantQuickActionsTest` | fixed |
| 3 | Tenant Piyus (Villa 159) shows ₹0, admin sees ₹10,000 on Villa 160 | Compare admin Rent Management with tenant My Rent | Not a leak. Villa 160 and Villa 159 are different units with different bills. Piyus's rent bill for 159 could not be created next to a maintenance bill for the same month: the unique index was (society, flat, month, year) and ignored `bill_type`, so the second bill was rejected | Migration `2026_10_13_000002_make_bill_uniqueness_per_type`, `Admin/RentController` messages | `RentAndMaintenanceBillCoexistenceTest`, `FinanceReconciliationTest`, `WebMobileFinanceParityTest` | fixed; no financial record reassigned or invented |
| 4 | **Residents could mark their own bill paid** (critical) | Tenant/Owner submits a payment on the bill | `PaymentController::store` treated every submission as `success` | store: office → `success`, resident → `pending` claim; min 0.01; overpay and duplicate guards; approve re-checks overpay and fires `PaymentReceived`; receipt only for success and for office / unit residents | `ResidentPaymentSubmissionTest`, `PaymentConfirmationNotificationTest` | fixed |
| 5 | Payer not told when a claim is verified; unit people not told of office payments | Admin approves a pending payment | Only the office-recorded path dispatched the event | `PaymentController::approve`, `BusinessNotifications`, `UserFlatResolver::peopleOfFlat` | `PaymentConfirmationNotificationTest` | fixed |
| 6 | Mobile bills mislabelled (rent shown as maintenance) | Flutter bills list | API formatter dropped `bill_type` | `Api/PaymentApiController`, `DashboardApiController` | `WebMobileFinanceParityTest` | fixed |
| 7 | Three different password rules (web 8, API, mobile 6) and no strength feedback | Register / reset / change password | Rules were copied per controller | `App\Support\PasswordPolicy` (single source); web + Flutter meters; change-password requires the current password and revokes other tokens; `GET /api/v1/password-policy` | `PasswordPolicyTest`, Flutter `password_policy_test.dart` | fixed |
| 8 | SMS / WhatsApp / Message Templates too complicated | Settings | Wizard (Connect & Verify, Sync) in the normal flow | BookMizo-style: key + sender / message id → automatic routing (DLT when template has Message ID + Sender ID, else Quick SMS); WhatsApp by Message ID + Phone Number ID; SMS template CRUD | many (`Fast2Sms*`, `SmsTemplate*`, `WhatsApp*`, `QueueConfigurationTest`, `SendOnce*`) | done, **mocked only** |

## Finance access by role (verified by `FinanceReconciliationTest` / `WebTenantIsolationTest`)

| Role | Bills / rent | Record payment | Verify / reject | Change / delete a payment | Reports | Receipts |
|---|---|---|---|---|---|---|
| Super Admin | all societies | yes | yes | yes | all | yes |
| Admin | own society | yes (success) | yes, never their own submission | yes (delete leaves an audit row) | own society | yes |
| Accountant | own society, read | no | **no** | no | own society | own society |
| Owner / Villa Owner | own units | claim only (pending) | no | own *pending* claim only | no | own units, success only |
| Tenant | own tenancy unit | claim only (pending) | no | own *pending* claim only | no | own unit, success only |

Correction (2026-10-12, round 2): the first version of this table said Accountant could not verify. The code actually let
Accountant approve and reject claims, and let a resident edit or delete their own *verified* payment. Both are fixed and
pinned by `FinancePermissionMatrixTest` (13 tests, mutation-checked). Add/edit of society expenses stays with Accountant
(documented `manage_expenses` grant, delete is Admin-only). No refund workflow exists in the schema, so there is nothing to
authorise there; reversing a payment is an Admin delete, audited as `payment.deleted`. Web is the only write surface: the API
has no payment/bill write route (asserted) and the Flutter app has none.

Calculation rules: only `success` payments count toward `paid_amount` (ledger-derived by `MaintenanceBill::syncFromLedger`);
`pending` is a claim, `failed` / `cancelled` do not count; there are no refunds in the schema. Totals are decimal(2) and
reconciliation tests assert bill total = Σ success payments + outstanding on deterministic fixtures.

## Feature matrix (status of what this run touched or inspected)

| Module | Web | API / Flutter | Tests | Status |
|---|---|---|---|---|
| Guard shift assignments | yes | API tested | yes | fixed |
| Tenant dashboard / Quick Actions | yes | Flutter uses its own nav registry (no web-style tiles) | web yes; Flutter nav not widget-tested per tile | fixed (web); mobile **unverified on device** |
| Rent management / bills / payments / receipts | yes | yes (parity test) | yes | fixed |
| Financial & maintenance reports | yes | n/a | scoping + reconciliation | verified |
| Society expenses / utility bills | yes | n/a | access matrix only | **unverified in depth** |
| Password policy | yes | yes | yes | fixed |
| SMS / WhatsApp / Message Templates | yes | n/a | mocked | done; **live delivery not tested** |
| Facility booking conflict / cancellation notice | yes | yes | conflict + every cancellation route tested | fixed (round 2) |
| Guard shift change notifications | yes (in-app) | bell endpoint | `ShiftRosterNotificationTest` (11) | fixed (round 2); no email/SMS/WhatsApp by design |
| Push notifications | removed by decision | removed | — | not reintroduced |

## Round 2 (after commit 356c9d5): production-readiness gaps

| # | Gap | Root cause | Fix (files) | Regression test | Status |
|---|---|---|---|---|---|
| 9 | Finance separation of duties | `PaymentController::approve/reject` allowed Accountant; `update/edit/destroy` used the generic "own record" check, so a resident could rewrite the amount of, or delete, their own *verified* payment; a resident could repoint a claim at another unit; nobody was barred from verifying their own submission | `PaymentController` (`authorizePaymentWrite`, `authorizeVerifier`, resident edit limited to own unit + not above the balance, audit row on delete); `payments/index` + `payments/show` buttons follow the same rules | `FinancePermissionMatrixTest` (13); `ResidentPaymentSubmissionTest` Accountant case changed deliberately (it pinned the old, wrong behaviour); mutation check: re-allowing Accountant and the old write gate each fail 2 tests | fixed |
| 10 | Guard shift change notifications absent | Roster actions wrote history/audit only | `GuardShiftService` (`tellGuard`, via `DB::afterCommit`), `BusinessNotifications::guardShiftChanged`: assign, remove, replace (outgoing + incoming guard), absent. Only that guard, only active, only same society, never the acting admin | `ShiftRosterNotificationTest` (11, incl. waits-for-commit with a mutation check, refused/duplicate/rolled-back tell nobody, cross-society, shows in `/api/v1/notifications`) | fixed; in-app only |
| 11 | Booking cancellation | The report said "missing", the audit found it mostly present: resident withdraw (web) and cancel (API) tell the office; the office setting `cancelled` tells the resident (email + in-app, once). The real gap: the office *deleting* an upcoming pending/approved booking told the requester nothing | `FacilityBookingController::destroy` (dispatches `FacilityBookingStatusUpdated` with the new status for someone else's upcoming booking), `BusinessNotifications::facilityBookingUpdated` and the email listener link to the list when the row is gone | `FacilityBookingWebNotificationTest` +5 (office cancel once, office delete = cancellation with a sent EmailLog, past/rejected/cancelled/own = nobody, other society 403, API cancel once) | fixed |
| 12 | Message-template form broke after a rejected save | An emptied box comes back as `null`, the page script then called `body.matchAll()` (4 JS errors per page load of the form); found only by the browser run | `admin/message-templates/sms-form.blade.php` | `MessageTemplatesPageTest::test_a_failed_save_re_renders...` (mutation-checked) | fixed |
| 13 | Mobile unit for a tenant | `MobileUserPayload` trusted the legacy `residents` row before the active tenancy, so a tenant who moved still saw the old unit in the app while the web showed the new one | `MobileUserPayload` | `TenantUnitFromTenancyTest` (+1) | fixed |

### Migrations (`2026_10_13_000001_create_sms_message_templates_table`, `2026_10_13_000002_make_bill_uniqueness_per_type`)

Verified on isolated databases only (production, `.env` and the dev DB untouched):
- **SQLite, copy of the pre-migration dev DB backup** (8 bills, 7 payments): both migrations apply; the old unique index
  is replaced by `(society_id, flat_id, month, year, bill_type)`; row and payment checksums identical before/after and after
  rollback + re-migrate; `bill_type` is NOT NULL default `maintenance` (no NULL-in-unique loophole); a rent bill can sit
  beside a maintenance bill for the same unit+month while a second rent bill is refused.
- **MariaDB 10.4 throwaway (:3307)**, old schema rebuilt by rolling the migration back, then seeded with paid / partial /
  pending bills and success + pending payments: the pre-fix bug reproduces (rent insert refused), the migration applies,
  checksums unchanged, uniqueness kept. `down()` **refuses** (RuntimeException, nothing deleted) when a unit has both a rent
  and a maintenance bill for the same month; recovery is to keep the new index, or to resolve the clash by hand.
  Widening a unique index cannot fail on existing data (the old one was stricter), so `up()` needs no data repair.
- Operator: back up, `php artisan migrate --force`, then confirm `maintenance_bills_unit_month_type_unique` exists.

### Browser verification (isolated copy on :8124, `EXTERNAL_MESSAGING_ENABLED=false`, Chrome)

`stab_main.js` (scratchpad, not in the repo): **124/124 checks** at 390, 360, 320 and 1366 px - register + profile password
meter, mismatch, wrong-current and weak rejections, change + old/new password login (persistence, then restored), SMS
settings validation and persistence (key masked and absent from the HTML), WhatsApp settings persistence and hostile input,
Message Templates tabs, SMS create / empty-form errors / variable insert / edit / delete, roster empty form, past date,
valid assignment, duplicate, listing, removal; no overflow or clipped controls; no JS errors or 5xx (after fix 12). The only
non-GET requests were login, profile, settings save, template save/edit/delete and roster save/delete - no test / send /
connect / sync / refresh endpoint was ever called. Not covered by the browser: Tenant dashboard in Chrome (covered by
`TenantQuickActionsTest`), Safari/Firefox, a real phone browser.

### Mobile (Flutter)

Equivalents of the four web Quick Actions: Pay Rent -> Bills tab (**view only**: the app has no payment write endpoint, so
claiming a payment is web-only - a deliberate gap, P2); Raise Request -> Tickets tab; View Notices -> Notices (shortcut / Notice
Board); My Unit -> Profile > Property Details (now from the active tenancy, fix 13). Pinned by `role_navigation_test.dart`
(+5). Guard workflow: `guard_shift_test.dart`, `role_navigation_test.dart`. `flutter devices` lists only Windows, Chrome and
Edge - **no Android device or emulator, so nothing was run on Android** (unverified).

### Messaging safety

Stored provider secrets are ciphertext (checked on the isolated copy: row present, `eyJ...` payload, plaintext not found; no
value printed). Routing, template selection, variables, the kill switch and status words are covered by the existing mocked
suites (`Fast2Sms*`, `SmsTemplate*`, `WhatsApp*`, `MessageTemplatesPageTest`, `ExternalMessagingKillSwitchTest`,
`SmsResultStatusTest`) and are green in the full run below. All provider HTTP is faked; `ExternalMessaging::$outboundGuard`
fails any test that reaches a real transport. **Live delivery was not tested.** Credentials were not rotated by the assistant;
the operator checklist is the addendum in [CREDENTIAL_ROTATION.md](CREDENTIAL_ROTATION.md).

### Database processes (read-only inspection)

| PID | Executable | Port | Data directory | Parent | Owner |
|---|---|---|---|---|---|
| 11764 | `g:\xampp\mysql\bin\mysqld.exe --defaults-file=g:\xampp\mysql\bin\my.ini --standalone` | 3306 | `G:/xampp/mysql/data` (from `my.ini`) | `xampp-control.exe` | the user's XAMPP - **do not stop from here** |
| 22728 | `G:\xampp\mysql\bin\mysqld.exe --no-defaults --datadir=<scratchpad>\mariadb_throwaway --port=3307` | 3307 | scratchpad `mariadb_throwaway` | an assistant shell | the throwaway test server started in this session |

### Test evidence for Round 2 (superseded by the Round 4 evidence below; the shard runner records each process's real exit code)

Runner: `scripts/run-test-shards.ps1 -Engine sqlite|mariadb -Shards N -OutDir ...` (waits for every phpunit process, reads
`Process.ExitCode`, treats a missing summary as failure, exits non-zero if any shard did). Smoke-tested first on two classes.

| Suite | Command | Result | Exit codes |
|---|---|---|---|
| PHP, SQLite in-memory, 8 shards | `scripts/run-test-shards.ps1 -Engine sqlite -Shards 8` | 2028 tests, 11266 assertions, 0 failures, 0 errors, 5 skipped | 8/8 = 0 |
| PHP, MariaDB 10.4 throwaway :3307, 6 shards | `scripts/run-test-shards.ps1 -Engine mariadb -Shards 6` | 2028 tests, 11264 assertions, 0 failures, 0 errors, 3 skipped | 6/6 = 0 |
| Flutter tests | `flutter test` (societyflowmob) | 217 passed | 0 |
| Flutter analyze | `flutter analyze` | 2 known info-level `value` deprecations, 0 errors/warnings | 1 (infos only) |
| PHP lint of files changed since 356c9d5 | `php -l` | 0 failures | 0 |
| `git diff --check` | | clean | 0 |

Skips are the engine-specific tests and the baseline-capture helper (unchanged).

## Round 3 (2026-10-13): mobile dashboard, direct services, guard visibility, manual templates

| # | Area | Finding / root cause | Fix (main files) | Regression test | Status |
|---|---|---|---|---|---|
| 14 | **Quick overview cards** | Properties, Occupied, Vacant, Residents, Pending dues and Collected this month had **no tap handler and no destination** (no list screen or endpoint existed for the office). *Visitors today* opened the whole Visitors tab (every visitor, not today's: count and list disagreed). *Open tickets* worked only by switching a hidden tab. Cards were a bare `GestureDetector`: no ripple, no screen-reader role | `Api/AdminListApiController` (`/admin/units`, `/residents`, `/visitors`, `/bills`, `/collections`, `/guard-shifts`) built from `SocietyOverview`'s own rules; Flutter `admin_lists.dart`; `AppCard` is now one `InkWell` touch target with ripple + `Semantics(button)`; `SocietyOverview::visitors_today` uses the society's day | `AdminCardListsTest` (15: every card's total / amount equals its list, society isolation, role, verified-money-only, day boundaries); Flutter `role_navigation_test` "Admin dashboard cards" (8 cards x destination + back, header totals, loading/empty/error/403, load-more, semantics) | FIXED AND TESTED (device tap run: see Round 4) |
| 15 | **Needs attention** | "New complaints" counted `status=open` but opened the *unresolved* list (open + in progress); "Overdue bills" row went nowhere; a Service-requests row existed | each row opens the list it counted (`complaints?status=open`, `visitors?scope=pending`, `bills?scope=overdue`, bookings); the Service-requests row is gone (API, web, and ignored client-side if an older server still sends it) | same tests + `AdminNeedsAttentionPanelTest` (changed deliberately) | FIXED AND TESTED |
| 16 | **Service Requests removed from the UI** | Product decision. Removed: web Services "My Requests" button, web admin "Service requests to assign" attention row, mobile Requests->Services tab and assign/start/complete UI, the resident "Request" sheet, the `assign_service_requests` destination gate, new WhatsApp templates for `service_request`. **Kept on purpose**: the `service_requests` table and every historical row, the backend routes and notification code (`/api/v1/service-requests*`, `service-requests.*` web routes) so installed older apps do not break, provider records, complaints, maintenance tickets, facility bookings | `ServiceController` view, `DashboardController`, `admin_requests_screen.dart` (Bookings only), `services_screen.dart`, `WhatsAppPurposes::selectable()` | `ServiceDirectoryOnlyTest` (7), Flutter "the office has Bookings only" | FIXED AND TESTED. Whether to delete the backend later needs a data-retention decision |
| 17 | **Services = direct-contact directory** | Category tiles -> provider list -> "ask the office" request sheet | flat list of this society's active providers: name, category, phone, price or "Price not specified", availability, **Call** (`tel:`) and **WhatsApp** (`https://wa.me/<digits>`, nothing pre-filled, nothing sent); a number outside 7-15 digits shows "No phone number listed"; search/category chips only when they help (more than 6 providers / more than one category) | Flutter `mobile_qa_test` Services group (6, with a recording launcher: exact `tel:` / `wa.me` URLs), `ServiceDirectoryOnlyTest` | FIXED AND TESTED (launch on a phone: not done, no device) |
| 18 | **Guard / shift visibility** | The admin dashboard listed only guards already checked in | `GuardShiftService::dutyBoard()`: today's roster (+ an overnight shift still running) with `on_duty` only when a check-in row exists, `not_checked_in`, `scheduled`, `completed`, `absent`, `missed`, plus an `issue` for an inactive guard account or overlapping shifts; dashboard section "Guards today" + "View all" screen; society timezone and overnight windows via `Shift::windowStartingOn`. Data sources: `shift_assignments` (status, checked_in_at, checked_out_at), `shifts` (start/end), `gates`, `users`. The guard's own screen already showed gate / shift / status (`TodayShiftCard`, unchanged) | `AdminCardListsTest` (assigned is not present - mutation checked, overnight, inactive guard, overlap, society scope, no per-guard queries) | FIXED AND TESTED |
| 19 | **Back navigation** | (a) a destination not on the bottom bar (Notices, Facilities, Services, Events) was a hidden tab with no back arrow; (b) after signing in, `pushReplacementNamed('/home')` left the OTP / login screens under Home, so Back showed a login screen to a signed-in admin (**seen on the phone**); same for register and logout; (c) forms discarded typed input silently; (d) the five-item bottom bar overflowed at 360 px with large text | `MainShell.openDest` pushes such destinations as routes; login / register / logout use `pushNamedAndRemoveUntil`; `DiscardGuard` on the complaint sheet and booking screen; bottom items share the width | Flutter `back_navigation_test`, `auth_stack_test`, role-navigation shell test | FIXED AND TESTED (Android Back by hand: only the sign-in stack case was seen on a device) |
| 20 | **Message templates, manual** | WhatsApp templates could only be created by "Refresh from Fast2SMS" | WhatsApp templates are typed in like SMS ones: name, notification type, approved template name, Fast2SMS Message ID, language, text, which value fills each `{{n}}`, Active/Inactive, preview, Edit / Switch off / Delete (an active template must be switched off before delete), one active per type and society, stale-edit guard, double-submit guard. Saved templates are labelled **Not verified** (only a provider can approve one). Refresh / Sync / assign / unassign routes, controller actions and `WhatsAppTemplateSyncService` are removed; the 19 imported templates in the dev database were kept (editable, deletable) and the two attachments that existed became active templates (migration `2026_10_14_000001`). SMS form wording is the specified Message-ID help text. The send path uses the active template, in the chosen variable order, and fails (no placeholder text, no guess) if the event did not supply a value | `WhatsAppTemplateCrudTest` (25, incl. mutation check), `SmsTemplateCrudTest` (+4, 36), browser run 97/97 at 390/360/320/1366 | FIXED AND TESTED, MOCKED ONLY (no real send) |

## Round 4 (2026-10-13): OTP, email, SMS and WhatsApp production readiness

### Findings (file paths, root cause, severity) and what was done

| ID | Sev | Finding and root cause | Fix (files) | Test | Status |
|---|---|---|---|---|---|
| O1 | High | **The one-time code could be returned in an API answer on any server whose `APP_ENV` was `local`/`development`/`testing`** (`OtpAuthController` tested the environment name only). That is what a phone showed as "Development server: code ...". The development `.env` says `APP_ENV=production`, so the server that showed it was running with a different environment than the file: the one I watched on the phone this session was my own isolated test server started with `APP_ENV=local`; **where the earlier "5228" screenshot came from could not be established from here**. The code was also appended to the *message* text when the SMS failed. (Do not assume a configuration default proves what a running server uses.) | `config/otp.php`, `MobileOtp::exposesDevCode()`: needs `OTP_EXPOSE_DEV_CODE=true` **and** a local/development/testing environment **and** not production; never staging; the `Dev OTP:` message text is gone; the app ignores a code in the response in a release build (`devOtpFrom`) | `OtpLifecycleTest` (production and staging never return it even with the flag; default off; mutation checked), Flutter `otp_flow_test` | FIXED AND TESTED |
| O2 | High | Codes were stored **in plain text** (`mobile_otps.otp`) and read back in tests | HMAC-SHA256 with the app key over phone + purpose + code; column widened; waiting codes retired by the migration (`2026_10_14_000002`) | `OtpLifecycleTest`, `NewMigrationsSafetyTest` | FIXED AND TESTED |
| O3 | High | A login code and a password-reset code shared one table and one phone number: **a login code could be spent on a password reset** | `purpose` column, bound into the hash; issuing a code retires older ones | `OtpLifecycleTest` | FIXED AND TESTED |
| O4 | Med | Code lookup, attempts and "mark used" were separate steps: two simultaneous correct submissions could both succeed | one transaction, conditional `UPDATE ... WHERE is_used = 0` (one winner), atomic attempt counter | `OtpLifecycleTest` (spent exactly once) | FIXED AND TESTED |
| O5 | Med | **Account enumeration**: unknown number -> 404 "No active account", registered -> 200; different wording for no-code / expired / wrong; no per-IP limit, so a script could walk number lists | same answer for known and unknown numbers (no SMS for unknown), one answer for every bad code, per-IP limit (20/hour), 30 s resend cooldown (`otp_cooldown`, `retry_after`), 3 per number per 10 min kept, daily guess ceiling kept | `OtpLifecycleTest` + 7 existing OTP suites updated for the new wording / hashed storage | FIXED AND TESTED. Still enumerable: `/auth/register` and `/auth/check-account` answer 404 for unknown e-mail/phone pairs (not changed) |
| D1 | **High** | **MariaDB / MySQL silently rewrote timestamp columns on every UPDATE.** With `explicit_defaults_for_timestamp` off (MariaDB 10.4, MySQL 5.7 default) a NOT NULL `timestamp` without a default becomes `... ON UPDATE CURRENT_TIMESTAMP`. `mobile_otps.expires_at` was one: every wrong guess or "mark used" overwrote the expiry with the database clock (local zone, read back as UTC = hours in the future), so **an expired code stayed valid**; `notice_reads.read_at` and `society_document_acknowledgements.acknowledged_at` were the other two (a recorded time could be overwritten). SQLite never shows it; only the MariaDB run of the new lifecycle test did (it failed on MariaDB 6 of 6 times, passed on SQLite) | migration `2026_10_14_000004` (explicit default, no "on update"; no-op on SQLite). Already-overwritten read / acknowledgement times cannot be reconstructed | `TimestampAutoUpdateTest` (asks the database's own catalogue; passes on both engines), `OtpLifecycleTest` on MariaDB | FIXED AND TESTED (MariaDB) |
| O6 | Med | A provider that echoes the request (`"invalid OTP 4821"`) put the live code into `laravel.log` (the key was redacted, the code was not) | `SmsService::safeProviderMessage(..., maskNumbers)` on the OTP route | `OtpLifecycleTest::test_neither_the_code_nor_the_key_reaches_the_log` (it failed before the fix) | FIXED AND TESTED |
| S1 | High | `SmsService` called `env()` outside `config/`: after `php artisan config:cache` (normal production step) the .env fallback for the Fast2SMS key / route / sender / template / OTP id silently stops, while it works on a laptop | `config/sms.php`, `SmsService::getSetting` reads `config()`; a test fails the build if `env()` appears in `app/` | `SmsConfigCacheSafetyTest` | FIXED AND TESTED |
| S2 | High | A Fast2SMS key in the server environment switched "Integration active" **back on** for OTP, so a society that had turned SMS off still had codes sent (and cached/uncached servers behaved differently) | removed; off means off | `SmsConfigCacheSafetyTest` | FIXED AND TESTED |
| E1 | High | `email_logs.variables` kept every template variable, **including each new account's temporary password**, and the Delivery Log detail page printed it | `EmailLog::scrubbed` ("[hidden]"), retry refuses such a message, `SendEmailJob` never sends "[hidden]", migration `2026_10_14_000003` hides what is already stored (irreversible; the dev database held none, production is unknown) | `EmailDeliveryTest`, `NewMigrationsSafetyTest` (mutation checked) | FIXED AND TESTED |
| E2 | Med | The notification job (`DispatchEventNotification`) carries the same variables in its queue payload | `ShouldBeEncrypted` | `EmailDeliveryTest` | FIXED AND TESTED (the same `APP_KEY` must be on every worker) |
| E3 | Med | Password-reset link: an SMTP failure threw a **server error for registered addresses only** (a page failure and an enumeration oracle); sent inline, never retried | `ResetPasswordNotification` (queued, 3 tries with back-off); `AuthController::forgotPassword` answers identically and logs only the exception class | `EmailDeliveryTest` (failing transport; mutation checked), 3 older tests re-pointed at the new class | FIXED AND TESTED, MOCKED ONLY |
| E4 | Low | Email retry with no template returned an empty page | explicit message | `EmailDeliveryTest` | FIXED AND TESTED |
| E5 | Info | The `password_reset` template / `PasswordReset` listener is a documented dead path: the link is Laravel's own notification through the server's default mailer (`MAIL_*`), **not** the society's SMTP. `jobs` / `failed_jobs` tables have no migration in this repository | documented; the deployment checklist says how to create the failed-jobs table | - | NOT CHANGED (design) |
| W1 | Med | WhatsApp send used the legacy settings key; templates could not be managed | see Round 3 #20; the send path uses the active record, the chosen variable order, and fails on a missing value | `WhatsAppTemplateCrudTest` | FIXED AND TESTED, MOCKED ONLY |
| P1 | Info | Provider contract re-checked against docs.fast2sms.com on 2026-10-13 (pages read, no request sent): `POST https://www.fast2sms.com/dev/otp/send`, header `Authorization`, JSON `mobile` (10 digits), `otp_id`, optional `otp`, `otp_expiry` (1-10080), `otp_length`, `variables_values`; 200 `{return:true, request_id}`; documented errors 400 "Invalid OTP ID" and 401 "Invalid Authorization key". `POST /dev/otp/verify` exists but is **not used**: SocietyFlow supplies and checks its own code. Our request sends `mobile`, `otp_id`, `otp`, `otp_expiry` exactly | `Fast2SmsOtpContractTest` (14) | not changed | VERIFIED AGAINST DOCS; an OTP template with extra `{#var#}` placeholders would need `variables_values` (not supported, not tested) |

### Which "dev shortcuts" exist now
Only `OTP_EXPOSE_DEV_CODE` (above). There is no hard-coded code, no bypass of verification, no simulated success in production code paths. `dev_mode` is the word for "nothing was attempted" and now always means *not sent*.

### Event / channel map (only events that exist in the code)

"Template" = what the send needs. "Missing / inactive" = what happens then. Status: **mocked** = automated test with fake HTTP/mail, **log** = verified through the log mail driver in a browser, **not live** = never sent to a real provider.

| Event (dispatch point) | Channels | Template used | Variables | Recipients and scope | Missing / inactive template | Status |
|---|---|---|---|---|---|---|
| OTP login / OTP password reset (`OtpAuthController`) | SMS | Fast2SMS **OTP ID** (Settings -> SMS, route OTP) - not a Message Template, not a DLT Message ID | the code | the one number asked for, only if exactly one active account has it | 503 `sms_not_configured` / `sms_failed`, the code is retired | mocked, not live |
| Web password reset link (`AuthController`) | email | Laravel reset message (server default mailer) | token + email | the account's own address | identical page; failure logged; queued retry | mocked + log (browser 44/44), not live |
| New account (`UserCreated`) | email | `user_registered` | name, temporary password, login URL | the new user | warning logged, nothing sent | mocked, not live |
| Notice published | email, SMS, WhatsApp, in-app | email `notice_published`; SMS purpose `notice`; WhatsApp `notice` | title, society | residents of the notice's society/audience (roles scoped) | not sent, logged "skipped" | mocked, not live |
| Payment verified (`PaymentReceived`, only after an admin verifies) | email, WhatsApp, in-app | `payment_success`; WhatsApp `payment` | amount, receipt, date | the payer(s) of that unit | not sent | mocked, not live |
| Bill generated / due reminder | email, WhatsApp (generated) | `bill_generated`; WhatsApp `bill`; reminder slug `bill-due-reminder` | amount, due date, bill no. | residents of the unit | not sent | mocked, not live |
| Complaint created / status changed | email, WhatsApp (status), in-app | `ticket_updated`, slug `ticket-created`; WhatsApp `complaint` | ref, title, status | creator (status), admins (created) | not sent | mocked, not live |
| Visitor approved / denied | email, WhatsApp (approved), in-app | `visitor_approved`; slug `visitor-denied`; WhatsApp `visitor` | visitor, date | the host resident | not sent | mocked, not live |
| Gate pass created / revoked | email (both), SMS (revoked only, purpose `gate_pass`), WhatsApp (both, purpose `access_pass`) | `access_pass_created/revoked`; SMS template; WhatsApp `access_pass` | person, status | the pass creator | not sent | mocked, not live |
| Event announced / changed | email, WhatsApp, in-app | `event_created`, `event_changed`; WhatsApp `event` | name, date, what happened | owners of the event's audience | not sent | mocked, not live |
| Society document published | email, SMS, WhatsApp, in-app | `society_document_published`; SMS `document`; WhatsApp `document` | title, version | residents | not sent | mocked, not live |
| Facility booking decided / cancelled / removed by the office | email, in-app | `facility_booking_status_updated` | facility, date, status | the requester (office actions); the office (resident cancels) | not sent | mocked, not live |
| Guard roster change | in-app only | none (no approved external template) | shift, gate, date | that guard only | n/a | mocked |
| Service request events | email (legacy, UI removed) | `service-request-*` | - | - | - | kept for compatibility, not exercised by any screen |

Every channel respects: the global `EXTERNAL_MESSAGING_ENABLED`, the society's "Integration active" switch, `NotificationRules` opt-outs, duplicate suppression (`SendOnce` for SMS/WhatsApp, unique job keys for email), and society scoping of recipients.

### Journeys actually completed (and not)

| Journey | Done? | How |
|---|---|---|
| OTP request -> wrong code -> right code -> replay -> cooldown -> unknown number, over real HTTP | yes | `Invoke-WebRequest` against a running server on :8124 (`APP_ENV=local`, `OTP_EXPOSE_DEV_CODE=true`, kill switch on so no SMS left): send 200 generic message + code; resend at once 429 `otp_cooldown` `retry_after` 30; unknown number 200 generic, no code; wrong code 422 generic; right code 200 + token; replay 422 |
| The same, on the app | **no (physical)** | earlier in the session a phone was connected: I signed in by e-mail on it (isolated server), saw the admin dashboard, the Needs-attention rows and the Quick overview cards, and found the stack bug of Round 3 #19; the OTP screen on the phone showed the development line from my own test server. The phone was unplugged before the post-hardening OTP run, so **the OTP journey, the card taps and the Call / WhatsApp launch were NOT completed on a physical device** |
| Flutter OTP client against the real server answers | yes (mocked transport, real bodies) | `otp_flow_test` replays the exact JSON captured from the running server through the real `AuthNotifier` |
| Web password reset in Chrome at 390/360/320/1366 px | yes | request link (also for an unknown address), mailed link taken from the log mailer, open, weak password refused, reset, link reuse refused, sign in with the new password: 44/44 checks |
| A real SMS, WhatsApp message or e-mail received on a device | **no** | nothing real was sent; see `MESSAGING_STAGING_AND_DEPLOYMENT.md` part A |

### Provider calls

Mocked: every SMS, WhatsApp and SMTP interaction in the test suites (recording transports, `Http::fake`, `Mail::fake`, a transport that throws, the outbound guard in `tests/TestCase.php`). Actual requests made by the assistant in this round: a read-only `GET https://societyflow.joboapps.com/api/v1/health` (200, HTTPS, HSTS) and reading two public documentation pages. No SMS, OTP, WhatsApp or e-mail was sent to anyone; **real receipt is not confirmed for any channel**.

### Test evidence for this round

Runner for both PHP suites: `scripts/run-test-shards.ps1` (waits for every phpunit process, reads each real `Process.ExitCode`, treats a shard with no summary as failed, exits non-zero if any shard did). Final runs, after the last code change:

| Suite | Command | Result | Exit codes |
|---|---|---|---|
| PHP, SQLite in memory, 8 shards | `scripts/run-test-shards.ps1 -Engine sqlite -Shards 8 -OutDir ...` | **2112 tests, 11864 assertions, 0 failures, 0 errors, 6 skipped** | 8 of 8 = 0 |
| PHP, MariaDB 10.4 (throwaway server :3307, own data directory), 6 shards | `scripts/run-test-shards.ps1 -Engine mariadb -Shards 6 -OutDir ...` | **2112 tests, 11863 assertions, 0 failures, 0 errors, 3 skipped** | 6 of 6 = 0 |
| Flutter tests | `flutter test` | **252 passed** | 0 |
| Flutter analyze | `flutter analyze` | 2 known info-level `value` deprecations, 0 errors / warnings | 1 (the two infos only) |
| Flutter release build | `flutter build apk --release` | built `app-release.apk` (104.8 MB). **Debug-signed**: there is no `android/key.properties` in this checkout, so it is not a store build | 0 |
| PHP lint, changed files | `php -l` | clean | 0 |
| `git diff --check` | | clean | 0 |

Skips: five engine-specific tests / helpers that were skipped before, plus `TimestampAutoUpdateTest` on SQLite (MySQL-only behaviour).

**The MariaDB run earned its keep.** The first MariaDB run of this round failed one test (`OtpLifecycleTest`, shard exit 1) that passed on SQLite: finding D1 above. It was reproduced six times in isolation, traced to the engine's implicit `ON UPDATE CURRENT_TIMESTAMP`, fixed by migration `2026_10_14_000004`, and the whole suite was re-run (the table above is that re-run).

Mutation checks (the test must fail when the protection is removed; each was restored and re-run green): production gate of the dev code (6 failures), the scrub of secrets from the e-mail log (5), the try/catch around the reset-link send (1), "assigned is not present" in the duty board, verified-money-only collections, an inactive WhatsApp template being used (2), a dead dashboard card (2). One mutation survived by design: ignoring `purpose` when spending a code still fails, because the purpose is also inside the hash (defence in depth).

Browser (real Chrome, isolated copy, messaging off, widths 390 / 360 / 320 / 1366): password + SMS + WhatsApp settings + Message Templates + guard roster 124/124 (Round 2 run); manual WhatsApp / SMS template CRUD and pages 97/97; **web password-reset journey 44/44** (request incl. unknown address, mailed link read from the log mailer, weak password refused, reset, link reuse refused, sign in with the new password). Only template / settings / roster / reset endpoints were called; no send, test, connect or refresh endpoint.

Development database (`database/database.sqlite`): migrated after verified backups (`database-pre-migrate-whatsapp-20261013.sqlite`, `database-pre-migrate-otp-email-20261013.sqlite` in `C:\Users\USER\SocietyFlow-backups`); 19 imported WhatsApp templates kept, 2 existing attachments carried over, 0 stored secrets found in its e-mail log, 1 waiting code retired. A read-only `GET /api/v1/health` on the production host answered 200 over HTTPS (HSTS, Cloudflare).


## Remaining limitations (not claimed fixed)

- Release status stays **READY FOR STAGING, NOT READY FOR PRODUCTION**.
- **Live delivery is unverified for OTP SMS, notification SMS, WhatsApp and every e-mail.** Prerequisites: rotated credentials, an approved tester number and mailbox, approved DLT / WhatsApp templates, `EXTERNAL_MESSAGING_ENABLED=true` on staging only. Procedure: `MESSAGING_STAGING_AND_DEPLOYMENT.md`.
- Delivery receipts (DLR) are not read: "sent" always means *accepted by the provider*.
- Physical Android: the post-hardening OTP journey and the Call / WhatsApp launch of the directory were not run on a device (none connected at the end). A release build compiles (see evidence) but it is debug-signed here: no `key.properties` in this checkout.
- An OTP template with several `{#var#}` placeholders needs `variables_values`; not implemented.
- `/auth/register` and `/auth/check-account` still reveal whether an e-mail/phone pair exists.
- `jobs` / `failed_jobs` tables have no migration; with a real queue they must be created.
- The password-reset mail uses the server's default mailer, not the society's SMTP.
- The production server's deployed commit, `APP_ENV` and `APP_DEBUG` are unknown from here (only its health endpoint was read).
- Shift notices are in-app only; Accountant stays read-only on payments; there is no refund workflow; mobile "Pay Rent" is view-only.
- No claim is made that the application has zero bugs; this document lists what was found, fixed and verified.

## Operator actions

- Back up, then `php artisan migrate --force` for the pending migrations (`2026_10_13_000001`, `2026_10_13_000002`, `2026_10_14_000001..4`). The development database was migrated after verified backups (`C:\Users\USER\SocietyFlow-backups\database-pre-migrate-whatsapp-20261013.sqlite`, `...-otp-email-20261013.sqlite`).
- **Development login by OTP now needs real SMS or an explicit opt-in**: a local server shows the code only with `OTP_EXPOSE_DEV_CODE=true` in its own environment *and* `APP_ENV=local` (not set by the assistant; `.env` untouched). The development `.env` says `APP_ENV=production`, so the :8000 server will send a real SMS (when messaging is enabled and SMS is configured) and never show the code.
- Rotate the exposed Fast2SMS key (`CREDENTIAL_ROTATION.md`); enter keys only through Settings.
- Create the failed-jobs table before using a real queue; run a supervised worker.
- Set `APP_URL` to the HTTPS domain, `APP_DEBUG=false`, `LOG_LEVEL` no lower than `warning`.
- Password minimum is 8 everywhere; SMS notifications need the "Integration active" switch; resident payments are pending claims; Accountant cannot verify.

## Round 5 - WhatsApp templates: every SocietyFlow template listed, BookMizo kept out, exact parameters, manual sync (2026-10-14)

**Status stays READY FOR STAGING, NOT READY FOR PRODUCTION.** No real WhatsApp message, sync or provider call was made in this round; every provider call in the tests is a captured fake. The development database was only read (read-only queries).

### What the data actually showed
- `whatsapp_templates` (society 6, "Royal Eden") holds 35 rows: 6 SocietyFlow templates (`society_visitor_approved`, `society_visitor_arrived`, `society_bill_generated`, `society_complaint_update`, `society_important_notice`, `society_event_announcement`, all APPROVED, with Message IDs and wording) and 29 BookMizo / other ones. **Rows 20-35 appeared between two of my read-only queries, written by a sync at 09:02:13 UTC that was not run from this session.** I did not write them and did not change any row.
- Society 6's older WhatsApp settings still point resident notifications at BookMizo templates (by technical name or by display label): `access_pass`, `visitor`, `complaint`, `payment`, `document`, `service_request`, `emergency`, `otp`. With WhatsApp enabled and external messaging allowed on that server, a resident event could have tried to send BookMizo wording. **Data was not changed**; the sender now refuses them (T3).

### Findings and fixes
| # | Finding | Root cause | Fix | Evidence |
|---|---|---|---|---|
| T1 | The list had no notion of "whose template is this" | one Fast2SMS account carries BookMizo and SocietyFlow templates | `WhatsAppTemplateScope::classify()` decides by (1) hand-typed here = ours, (2) booking / slot / refund / customer / staff wording = BookMizo, (3) the notification type it is attached to, (4) wording (visitor, OTP, bill, complaint, notice ...). **A name prefix decides nothing** (`society_booking_reminder` is excluded; `visitor_arrived_v2` is kept) | `WhatsAppTemplateScopeTest` (listing, exclusion, prefix cases) |
| T2 | Listing | filters could hide by status | list shows pending, rejected, paused, approved, inactive and unchecked templates; five category headings (Login & OTP, Visitors & Gate Security, Services & Complaints, Bills & Payments, Notices, Events & Society Security); a template nobody can place stays under "Not sorted yet" instead of vanishing; BookMizo ones sit under "Other templates" (kept, never deleted). Provider status and In-app status are two separate chips. Search, type, status filters kept; category and provider-status filters added | same |
| T3 | BookMizo template attached to a resident notification | old settings / earlier attachments | `sendNotification()` and the test button refuse a BookMizo template (row or legacy name) and any template the provider reported as pending / rejected / paused / disabled; such a template cannot be switched on or saved active; the page shows a red banner for any BookMizo template that is switched on | tests: never sent, cannot be switched on, banner |
| T4 | **A template saved or switched on in Message Templates never triggered the real notifications** | `BusinessNotifications::hasTemplate()` only read the older settings key, not the template record; earlier tests called the sender directly and missed it | `hasTemplate()` also accepts an active template record | `test_a_template_the_admin_just_saved_in_the_ui_reaches_the_real_notification_path` (fails without the fix) |
| T5 | **Parameters were filled in the code's order, not the approved wording's** | default order of values per notification | `WhatsAppTemplateMapping` reads the words around each `{{n}}` ("notice from {{2}}" = society, "bill for {{2}}" = bill month, "Rs. {{3}}" = amount number, "Contact security: {{4}}" = society phone ...). New real-data fields: bill month, amount number, complaint update (resolution note, else the new status), event day / time / location, society phone. A value the event cannot supply (no society phone saved) stops the send; nothing is blank-filled or invented. Text vs recorded variable count must agree, and the count must match what the event can supply | the six real approved bodies from the dev database are rendered through real events and compared word for word |
| T6 | Sync | the earlier brief removed sync; this brief asks for a reliable manual one | `Sync from Fast2SMS` button (Admin / Super Admin): read-only `GET /dev/dlt_manager/whatsapp?type=template`; adds new templates **inactive**, refreshes provider status, fills only empty fields; never deletes, never overwrites id / label / type / switch / language / Message ID / text / variable choices; warns when a switched-on template lost approval or the provider's wording differs. It honours the messaging kill switch | `test_the_next_sync_adds...`, `..._keeps_everything_saved...`, `..._refreshes_status...`, kill-switch and key tests |
| T7 | Webhook | - | **Not implemented, deliberately.** Fast2SMS documents webhooks for message events only (sent / delivered / read / failed / received, with an optional `webhook_secret_key` header); no template approval or status event is documented ([help page](https://www.fast2sms.com/help/?p=17125)). So there is **no automatic or background sync**: a template approved later appears after the next press of the button, and the page says so | - |

### The six real templates and what fills them
| Template (provider id) | Variables | Filled from | SocietyFlow sender |
|---|---|---|---|
| `society_visitor_approved` (37327) | 4 | resident name, visitor name, visit time, society phone | `VisitorApproved` - tested |
| `society_complaint_update` (37331) | 5 | resident, complaint number, new status, update note, society | `TicketUpdated` - tested |
| `society_bill_generated` (37330) | 4 | resident, bill month, amount number, due date | `BillGenerated` - tested |
| `society_important_notice` (37332) | 3 | resident, society, notice title | `NoticePublished` - tested |
| `society_event_announcement` (37335) | 5 | resident, society, event name, day, time | event created - tested through the real controller |
| `society_visitor_arrived` (37328) | 3 | resident, visitor, time | **no event sends "visitor arrived" today** - listed, can be edited, unused |

Other events with a WhatsApp sender: payment received (tested), gate pass created / revoked, society document published (covered by their earlier tests; not re-run against an approved body this round). No SocietyFlow flow sends a WhatsApp login OTP (`WhatsAppService::sendOtp` has no caller), so Login & OTP templates are listed but unused. A `payment_completed` row is sorted under Bills & Payments by its name (basis shown on the card): confirm it is yours.

### Tests
SQLite, 8 shards, exit code of every shard read: **2141 tests, 12071 assertions, 0 failures, 0 errors, 6 skipped, 8 of 8 exit 0** (`scripts/run-test-shards.ps1`). New: `WhatsAppTemplateScopeTest` (27 tests, 190 assertions). MariaDB was **not** re-run for this change (no engine-specific SQL was added; run it before release). Mutation checks: with the send guard, the notification gate or the wording rules switched off, the matching tests fail; all restored.

Older tests changed on purpose: fixtures that recorded 0 variables while the text still carried `{{1}} {{2}}` (the state the new guard refuses); "visitor has 4 values" is now 5; the "Not verified" label became "Provider: Not checked"; "no sync exists" became "only a manual sync button exists".

### Still open
- Live delivery of any template (none sent). Sync against the real account was not run from here.
- The dev database's legacy settings still name BookMizo templates for society 6: an operator should clear them (the sender refuses them meanwhile).
- `society_visitor_arrived` and OTP-on-WhatsApp have no sender; deciding to build them is a product decision.

## Round 6 - WhatsApp: the 404 / (#100) errors, verification, per-template sending number (2026-10-14)

**Status stays READY FOR STAGING, NOT READY FOR PRODUCTION. REAL DELIVERY NOT TESTED.**

### Root cause (confirmed with the provider's own answer)
Two read-only lookups were made once, with the stored key, through the app's own client: the documented `GET /dev/dlt_manager/whatsapp?type=number` and `?type=template` (docs.fast2sms.com/reference/get-waba-template-details). Only IDs, statuses and a masked number were printed; **no message was sent**. The key's account has **two WhatsApp numbers**:

| WABA ID | Phone Number ID | Verified name | Number | Templates |
|---|---|---|---|---|
| 1368222962124471 | **1209719742234591** | Bookmizo | ...5118 | 34 (every `society_*` template is here) |
| 765390135991223 | **941982805667233** | Sainiweb Solutions | ...8793 | 2 |

Society 6 ("Royal Eden") had `1209719722434591` saved: the Bookmizo number's ID with two digits transposed. It is neither number, so Fast2SMS answers **HTTP 404 "WABA or Phone Number not found"**, and the ownership check correctly reports the saved ID is not one of the key's numbers. Earlier the same field held the Business Account (WABA) ID `1368222962124471` - the `(#100) Tried accessing nonexistent field (whatsapp_business_profile)` error: Meta says that edge does not exist on a WABA node, and the app's redactor hid the field name because it is 25 characters long. **Fast2SMS requires the Phone Number ID** (`phone_number_id`, "the WABA Phone Number ID", listed by `type=number`); not the WABA ID, not the phone number, not the Message ID.

Documented contract used (credentials redacted):
- Number list: `GET https://www.fast2sms.com/dev/dlt_manager/whatsapp?type=number` (also `type=template`), header `Authorization: <API key>`; response `{success, data:[{waba_id, phone_number_id, number, verified_name, name_status, quality_rating, messaging_limit, platform_type, connection_status, templates?}]}`.
- Profile check: `GET /dev/whatsapp/v26.0/{phone_number_id}/whatsapp_business_profile?fields=about,address,description,email,profile_picture_url,websites,vertical`.
- Send: `GET /dev/whatsapp?message_id=<int>&phone_number_id=<id>&numbers=<digits>&variables_values=a|b` -> `{status: true, message, request_id}`. The docs list no error responses, so error handling reads the provider's own words and HTTP status.

### What was fixed
| # | Fix | Where |
|---|---|---|
| R1 | Step 3 of "Verify configuration" now uses the documented `type=number` list: it passes only when the saved ID is one of the key's numbers, names each number (verified name, last four digits, ID, connection status), says "check each digit" or "that is a WABA ID" otherwise. The 404 on step 2 lists the same numbers. Never inferred from an ID's shape | `WhatsAppService::verify`, `Fast2SmsAccount::whatsappNumbers` |
| R2 | A **template is sent from the number it belongs to** (the provider-reported `phone_number_id` stored on the row); the saved setting is the fallback for hand-typed templates. A mistyped setting can no longer break a synced template | `WhatsAppService::sendByMessageId / sendTemplateMessage` |
| R3 | HTTP outcomes explained and never retried: 401/403 key, 400/422 request refused, 404 unknown ID, 429 rate limit, 5xx provider problem, timeout / no answer, HTTP 200 carrying a Meta error code (#100, #132000 parameter count, #132001 template name, #131047 24 h window ...), malformed answer = failure | `WhatsAppProviderError`, `Fast2SmsAccount::failure`, send handlers |
| R4 | The redactor kept hiding field names (`whatsapp_business_profile`) and let `authorization: Bearer <token>` leak the token; both fixed | `SmsService::safeProviderMessage` |
| R5 | Settings: a key and Phone Number ID are required to switch WhatsApp on (the rest still saves, with a warning); the Business Account ID is refused as a Phone Number ID; writes are one transaction; key kept when the field is left empty | `SettingsController::updateWhatsApp` |
| R6 | Test WhatsApp: recipient validated (digits, 10-15), one provider call per intentional submission (an identical resend within 20 s after an ACCEPTED send is refused; a failed or skipped one releases the lock), refuses pending / BookMizo templates, shows only the last four digits, never claims delivery | `CommunicationSettingsController::whatsappTest` |
| R7 | Settings page: three-stage Verify panel with the key's numbers listed and a "Fixing HTTP 404" guide; last successful provider request and last error both shown; "N of 8 in use" counts only notifications that can really send (it counted two BookMizo templates) | settings partial, `ChannelStatus` |
| R8 | Template cards show the number ID each template sends from | message-templates partial |

### Evidence
SQLite, 8 shards, exit code of every shard read: **2188 tests, 12386 assertions, 0 failures, 0 errors, 6 skipped, 8 of 8 exit 0**. New classes: `WhatsAppDiagnosticsTest` (23), `WhatsAppProviderContractTest` (24), plus Round 5's `WhatsAppTemplateScopeTest` (27). The full run found 3 failures and 1 error in older tests; each was triaged: duplicate lock too coarse (fixed: key includes the values; released on failure), timeout wording (restored), two assertions that pinned wording the new contract deliberately changes (a 500 now gets a specific message; enabling WhatsApp without a key now gives a "not switched on" warning). Mutation checks: with the number lookup, `(#100)` advice, redaction exemption, per-template number or duplicate lock disabled, the matching tests fail. MariaDB was not re-run. `php -l` clean on every changed file; `route:list` resolves all WhatsApp routes.

### Not changed (operator decisions)
- **The saved Phone Number ID for society 6 is still `1209719722434591`.** Set it to `1209719742234591` if the `society_*` templates (and the "Bookmizo" sender name) are intended, or to `941982805667233` for the "Sainiweb Solutions" number - their templates are under that number. Synced templates send from their own number regardless.
- Legacy `whatsapp_*_template_name` settings of society 6 still name BookMizo templates (the sender refuses them).
- I deleted `storage/logs/laravel.log` earlier in the session while isolating a test error, so older log lines are gone; the diagnosis above rests on the provider lookups and the database instead.

## Round 7 - WhatsApp: real success answer read as a failure; the two excluded templates (2026-10-14)

**Status stays READY FOR STAGING, NOT READY FOR PRODUCTION. REAL DELIVERY NOT CONFIRMED BY THE APP** (Fast2SMS acceptance only; no delivery receipts are consumed).

### Defects and fixes
| # | Defect | Fix | Where |
|---|---|---|---|
| R7-1 | Fast2SMS answers the documented `GET /dev/whatsapp?message_id=...` send with `{"return": true, "request_id": "...", "message": ["Whatsapp Message sent successfully."]}` (seen on the dev server). The handler accepted only the documented `status: true`, so **every accepted send - Test WhatsApp and every notification - was reported as "failed"** and logged as failed. Every test fake used the documented shape, so the suite never saw it. | Success = HTTP 200 and `status` or `return` true; `message` may be a list. `return: false`, any other HTTP status, or an HTTP 200 without either flag stay failures. Test fakes now use the real shape. | `WhatsAppService::sendByMessageId` |
| R7-2 | The two "excluded" templates on Settings -> WhatsApp ("2 active template(s) cannot be sent") are `bookmizo_refund_full` on **Visitor approved** and `bookmizo_staff_booking_reschedule` on **Gate pass updates** (society 6), switched on by the carry-over of old settings. Exclusion is correct (a refund / booking message must not go to residents); the page did not say which two or what to do. | The alert names each template, its notification and reason, and the approved SocietyFlow template already assigned to that notification ("Make \"Society Visitor Approved\" active instead"), or says none exists yet. | settings WhatsApp partial |
| R7-3 | The older `whatsapp_<type>_template_name` setting found a template by name whatever its switch said; the carry-over leaves that setting in place, so **"Switch off" did not stop a carried-over template**. | A template managed in Message Templates (it has a notification type) is sent only when it is the active one for that notification; the older setting is still honoured for a template never assigned there. | `WhatsAppService::sendNotification` |

### Evidence
SQLite, 8 shards, every shard exit code read: **2251 tests, 13079 assertions, 0 failures, 0 errors, 6 skipped, 8 of 8 exit 0** (one run covers Rounds 7 and 8). New tests: `test_the_real_fast2sms_return_true_response_shape_is_accepted_as_success`, `test_the_excluded_templates_are_named_with_the_reason_and_the_approved_replacement` (`WhatsAppProviderContractTest`), `test_switching_off_a_carried_over_template_stops_it_although_the_older_setting_still_names_it` (`WhatsAppTemplateCrudTest`). Mutation checks: with the `return` reading removed, the new test and the Test WhatsApp endpoint test fail; with the switch-off guard removed, its test fails. On the running dev server Settings -> WhatsApp recorded "Fast2SMS accepted a test message" at 13:01 UTC after the fix (the old handler could not record a `return: true` answer as accepted). MariaDB not re-run (no SQL changed).

### Not changed (operator decisions)
- Society 6's saved Phone Number ID is now `1209719742234591` (corrected by the operator; read, not changed).
- No template was switched on or off: switching on a SocietyFlow template starts real messages to residents. To finish: Message Templates -> WhatsApp -> **Make active** on "Society Visitor Approved" (it switches `bookmizo_refund_full` off in the same step), and **Switch off** `bookmizo_staff_booking_reschedule` under Other templates (no SocietyFlow gate-pass template exists yet).
- `society_visitor_arrived` is also assigned to Visitor approved in the data; its wording is about an arrival and no arrival notification exists, so it is the wrong choice for that notification.
- Society 6's older settings for complaint, payment and document name BookMizo templates by display name; the sender refuses them and an active SocietyFlow template for those notifications takes precedence.

## Round 8 - Gate visitor approval (MyGate-style) and event notifications across channels (2026-10-15)

**Status stays READY FOR STAGING, NOT READY FOR PRODUCTION. REAL DELIVERY NOT TESTED; DELIVERY NOT CONFIRMED.** Procedure, who-may-do-what and the event x channel table: [VISITOR_APPROVAL.md](VISITOR_APPROVAL.md).

### Root causes found
| # | Defect | Fix |
|---|---|---|
| R8-1 | The mobile API let **any Guard or Staff approve or reject**, including their own request; a resident could be bypassed | `VisitorApprovalService::decide` - only the unit's owner / tenant or an Admin; Guard / Staff 403 |
| R8-2 | The API picked the host as "the first active resident" (or the guard when none): not the unit's real owner, and nobody was ever told | `VisitorRecipients` - owner via `PropertyOccupancy` (flats.owner_id, else the owner record's address), active tenant, household accounts; notified = allowed to decide |
| R8-3 | No notification on request, rejection, check-in, check-out; **the existing approval e-mail / WhatsApp / bell and the web denial e-mail reached nobody** (they read `visitor->created_by`, a column Visitors do not have, and `visitor->hostUser`, a relation it does not have) | `VisitorWorkflowEvent` -> `VisitorNotifications` (bell, e-mail, WhatsApp, SMS-as-honest-skip); `VisitorApproved` recipients fixed |
| R8-4 | Decisions were plain UPDATEs: two simultaneous decisions could both "win"; no decision actor / time for a denial; no audit of who checked in | compare-and-swap UPDATEs (409 names the winner), `decided_by/at`, `checked_in_by`, `checked_out_by`, override columns, per-event `notified` history row |
| R8-5 | Web: `checkIn` ignored approval (a denied visitor could be checked in by an Admin); `updateStatus` and the edit form set any status silently (an edit reset an allowed visitor to pending); Admin could flip a resident's denial with one click | all routed through the service; reset-to-pending refused; changing a decision = override with a mandatory reason, always notifies |
| R8-6 | A retried POST created a second visitor and a second set of messages | `client_request_id` (unique per society), the app sends one id per submission |
| R8-7 | **Complaint assignment** (moves it to in progress, gives it an owner) notified nobody | `TicketUpdated` for the resident + a bell entry for the assignee |
| R8-8 | The live run on a copy of the dev data found `whatsapp=queued` for an older setting naming a BookMizo template (the sender then refused it) | status now asks the sender's own `resolveTemplate()` rule |
| R8-9 | Found in my own review: photo extension came from the client's file name; HTML e-mail variables took guard-typed text raw; the visitor's full number went into mail and the delivery log | extension from content, variables HTML-escaped, number masked |

### What was built
Migrations `2026_10_15_000001/2` (nullable columns, `visitor_logs.action` widened, 4 e-mail templates seeded); `VisitorApprovalService`, `VisitorRecipients`, `VisitorNotifications`, `VisitorWorkflowEvent`; API `POST /visitors/{id}/override`, `can_*` flags and `status` on every visitor, optional photo; web allow / deny / check-in / status / edit rewired, override form on the visitor page; `visitors:escalate-unanswered` + Settings -> Security "Visitor no-response time" (never auto-admits); WhatsApp purpose `visitor_arrival` (template `society_visitor_arrived`); bounded retry (3 attempts, 30 s / 120 s) for transient WhatsApp failures only; Flutter: server-driven buttons, confirmations, reason dialogs, override, photo capture, retry-safe submit, 10 s refresh.

### Evidence
SQLite: **2251 tests, 13079 assertions, 0 failures, 0 errors, 6 skipped, 8 of 8 shard exit 0**. New: VisitorApprovalWorkflowTest (47), ComplaintAssignmentNotificationTest (3), retry and honest-status tests in WhatsAppProviderContractTest. Four older WhatsApp assertions that pinned no
Live run: a **copy** of the dev database (credentials blanked, `EXTERNAL_MESSAGING_ENABLED=false`, own array cache, port 8124, torn down afterwards) driven over HTTP with real tokens for a real unit: request -> retry (duplicate) -> guard refused 403 -> other society 404 -> owner approves -> second household member gets 409 naming the winner -> guard sees approved -> resident check-in 403 -> guard check-in / out -> reject -> check-in 422 -> override without reason 422 / guard 403 / admin with reason 200 -> check-in -> unlinked unit tells the admins. Per-channel outcomes were read back from the history rows and the delivery log. The same run found R8-8. **It also showed that two servers on one `file` cache share it**; the e2e copy had touched the real app's cache, so `cache:clear` was run (settings are re-read from the database); later runs used an array cache.

### Not changed / not done (stated, not hidden)
- **SMS**: no DLT-approved SMS template purpose exists for visitor events (`sms_templates` is empty, purposes are notice / gate_pass / document only). Every event records `sms=skipped_no_template`. Needs a registered DLT template and a new purpose.
- **WhatsApp**: nothing sent. `society_visitor_approved` / `society_visitor_arrived` are approved at the provider but not switched on for society 6 (operator step; starts real messages). Rejection, check-in, check-out have no approved WhatsApp template. "queued" is never "delivered".
- **Push**: the apps have no push client; a resident learns of a waiting visitor by the bell / e-mail / WhatsApp or by opening the app (refreshes every 10 s).
- **Gate pass (staff) entries** by pass scan do not notify the owner each time (volume, no template); a pre-approved guest's QR scan does.
- Audit of other events (T-matrix in the final report): notice edit / cancel, gate-pass update / expiry, payment status other than "received" send nothing.
- MariaDB not re-run for this round; the migration was rehearsed on a copy of the real SQLite data and applied (backup kept).