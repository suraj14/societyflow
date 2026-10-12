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

| Role | Bills / rent | Record payment | Verify / reject | Reports | Receipts |
|---|---|---|---|---|---|
| Super Admin | all societies | yes | yes | all | yes |
| Admin | own society | yes (success) | yes | own society | yes |
| Accountant | own society, read | **no** (pinned by test; decision for the owner) | no | own society | own society |
| Owner / Villa Owner | own units | claim only (pending) | no | no | own units, success only |
| Tenant | own tenancy unit | claim only (pending) | no | no | own unit, success only |

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
| Facility booking conflict / cancellation notice | yes | yes | conflict tested | cancellation notice **missing / not audited** |
| Guard shift change notifications | none | none | none | **missing** |
| Push notifications | removed by decision | removed | — | not reintroduced |

## Verification

See `docs/CURRENT_STATUS.md` (2026-10-12 stabilization entry) for the run counts and what was NOT tested
(Android device, live provider delivery, browser pass of the new screens).

## Operator actions

- `php artisan migrate --force` for the two new migrations (`2026_10_13_000001`, `2026_10_13_000002`) after a backup. The dev DB was migrated after a backup.
- Rotate the Fast2SMS key that was pasted in chat (not done by me). `.env` was not touched.
- Password minimum is now 8 everywhere (mobile used to accept 6).
- SMS notifications now require the "Integration active" master switch.
