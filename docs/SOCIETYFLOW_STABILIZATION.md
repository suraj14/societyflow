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

### Test evidence (final, after the last code change; shard runner now records each process's real exit code)

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

## Remaining limitations (not claimed fixed)

- Release status stays **READY FOR STAGING, NOT READY FOR PRODUCTION**. Blockers: credential rotation (operator), staging delivery
  verification with approved recipients, Android device run, mobile payment-claim flow decision, Society Expenses / utility bill
  deep audit, load testing.
- Live SMS / WhatsApp / email delivery: **not tested**. The DLR delivery check is not implemented (response format undocumented).
- Android device / emulator: **not tested** (none available). Flutter widget/unit tests only.
- Shift notices are in-app only; an external channel needs an approved DLT / WhatsApp template and its own toggle.
- Admin deleting a verified payment is allowed (audited) - there is no refund / reversal workflow; adding one is a product decision.
- No claim is made that the application has zero bugs; this document lists what was found, fixed and verified.

## Operator actions

- `php artisan migrate --force` for the two new migrations (`2026_10_13_000001`, `2026_10_13_000002`) after a backup. The dev DB was migrated after a backup.
- Rotate the Fast2SMS key that was pasted in chat (not done by me). `.env` was not touched.
- Password minimum is now 8 everywhere (mobile used to accept 6).
- SMS notifications now require the "Integration active" master switch.
