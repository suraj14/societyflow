# Web audit tracker — 2026-10-10 / 2026-10-11

Scope: **web only** (Blade + the API paths the web shares). Android device testing: **NOT TESTED** (no device on this machine).
Environment: isolated server `127.0.0.1:8124` on a copy of the dev DB (`audit.sqlite`, provider credentials stripped),
`EXTERNAL_MESSAGING_ENABLED=false`, mail driver `log`, system Chrome via Playwright. The original XAMPP MariaDB data dir and
`database/database.sqlite` were not touched. Nothing was sent (email / SMS / WhatsApp / payment): **real delivery NOT TESTED**.

Status key: PASS · FIXED (defect found, fixed, regression test + mutation check) · PARTIAL · NOT TESTED · BLOCKED · PENDING.

## Modules

| Module | Coverage | Status | Evidence |
|---|---|---|---|
| A Auth / sessions | login, wrong password, 5-failure lockout (even for the right password), expired session, GET vs POST logout (browser); reset link target/recipient, throttle, expired / replayed / foreign / invalid token, weak password, confirmation messages (tests + browser) | **PASS** (mail *delivery* NOT TESTED — log driver only) | `PasswordResetEdgeCasesTest` (9), `PasswordManagementTest`, `LogoutTest`, `AuthSecurityTest`; `e2e/auth.js` 6/6, `flows.js` G |
| B User mgmt | edit page, Add User, photo parity, role-permission lockdown | PASS | `SuperAdminSurfaceTest`, `flows.js` F |
| C Owner / Tenant / Resident | convert resident → owner, tenant create form, society-scoped lists | FIXED | `OwnerConvertResidentTest`, `PropertyTypeFieldContractTest`, `SuperAdminSocietyRecordAccessTest` |
| D Villa / Apartment / Flat | assignment, unit resolution from active tenancy | FIXED | `TenantUnitFromTenancyTest`, `TenantBookingFormUnitsTest` |
| E Facilities & bookings | submit → clash refusal → approve → resident sees + bell (browser, desktop and 360px); advance window / past / invalid / timezone on **web and API** | FIXED | `FacilityAdvanceWindowTest` (8), `BookingApprovalConflictTest`, `FacilityBookingWebNotificationTest`, `flows.js` C, `phoneflow.js` |
| F Bills / Rent / Payments | rent add-payment validation and dialog, rent validate endpoint, maintenance report PDF, Super Admin billing | FIXED | `AdminUtilityEndpointsTest`, `SuperAdminBillingTest` |
| G Complaints / Services | tenant files → admin responds → tenant sees status + bell; villa-owner requests; service attendance auth | FIXED | `OwnerPortalRequestTest`, `ServiceAttendanceAuthorizationTest`, `flows.js` A |
| H Notices / Events / Notifications | draft → publish, resident visibility on list/pending/history/show/villa portal, bell; event notifications | FIXED (D-31, D-33) | `NoticeDraftVisibilityTest` (3), `NoticeIsolationTest`, `EventNotificationTest`, `flows.js` B |
| I Visitors / Guard / access passes | **Guard screens in a real browser at 390px**: register (property search UI), double-click, validation, approval gate, check-in / check-out, repeat refused, history, invalid scan, cross-society and role denial. Access-pass lifecycle: backend suites only | **PASS** (valid-pass QR scan in browser NOT TESTED — needs a camera/token fixture) | `e2e/guard.js` 31/31; `GuardGateTest`, `AccessPass*Test`, `GuardRoleContainmentTest` |
| J Society info & documents | upload → publish → resident download (PDF) | PASS | `flows.js` E |
| K Staff / Guard / Accountant | shifts, gates, providers refuse Super Admin writes without a society; Guard / Staff / Accountant pages checked at phone width; Staff blocked from gate | FIXED | `ShiftFlowTest`, `phone.js` |
| L Dashboards / counts | Owner / Tenant / Accountant / Staff figures compared with independently computed records (partial pay, settled, other unit, other society, empty state); Staff events on SQLite **and** MariaDB; Admin KPIs existing | **PASS** (D-34, D-35) | `DashboardFiguresMatchRecordsTest` (4), `StaffDashboardEventsTest` (2), `AdminDashboardKpiTest` |
| M Settings / communication | payment / storage / email-test settings, secret masking, template mappings, packages, landing editor | FIXED | `SuperAdminSettingsSecretsTest`, `EmailTemplateMappingTest`, `SuperAdminPackageCrudTest`, `LandingSiteEditorTest` |
| N Responsive | 77 pages × 3 viewports (320 / 360 / 390 px, touch + mobile emulation) for 6 roles; drawer, keyboard login, photo preview, native + server validation, booking, complaint, notice, add-payment dialog, user / owner edit forms | **PASS** after D-37 (small icon tap targets noted, P3) | `e2e/phone.js`, `e2e/phoneflow.js` 29/30 (the 30th, the profile flash, confirmed by `dbg3.js`) |

## Defects found by this audit (all with regression tests unless noted)

| ID | Sev | Defect | Root cause | Fix |
|---|---|---|---|---|
| D-01 | High | Settings → Payment 500 when optional keys absent | `validate()` omits absent optionals | defaults merged after validation |
| D-02 | Med | Rent add-payment validation surfaced as 500 | `ValidationException` uncaught | 422 JSON / 404 |
| D-03 | High | Storage settings re-saved a masked AWS secret | masked value written back | `SettingSecrets::resolve`, `storage` marked sensitive |
| D-04 | Med | Super Admin billing used fabricated ids / wrong status; no show / invoice | view / model mismatch | relations, new views, idempotent `markAsPaid` |
| D-05 | Med | Package features stored as a non-list; "0" instead of Unlimited | request array stored raw | `array_values`, Unlimited label |
| D-06 | Med | Landing uploads accepted any file type; reorder accepted non-integers | missing rules | `mimes:`, `integer` |
| D-07 | Med | Tenant booking form offered a stale residency unit | legacy `residents` rows | `unitsOf()` via `UserFlatResolver` |
| D-08 | Med | Bookings: approve / cancel / edit sent no notification; double approval | no hook / guard | `BusinessNotifications`, `transitionConflict` |
| D-09 | High | Super Admin could create society-less tenants / shifts / gates / providers; cross-society attendance | missing helper / authorize | `requireSocietyForWrite`, checks |
| D-10 | High | System email templates editable by society admins; non-deterministic template pick | no ownership check | `mayChange()`, society-first resolver |
| D-11 | Med | Convert resident → owner: no authorization, duplicate email 500 | missing checks | `authorizeSocietyAccess`, global email check, transaction |
| D-12 | Med | Villa-owner requests dropped category, never notified the office | wrong column / no event | `complaint_category_id`, `complaintCreated` |
| D-13 | Med | Property-type radio on tenant create did nothing | handler not global | `window.updatePropertyTypeSelection` |
| D-14 | Med | "Initialize defaults" duplicated templates | no existence check | create only the missing |
| D-31 | **High** | Residents saw other users' **draft and pending notices** (list; stats counted every society; direct show) | list filtered society only, never status | published-only for non-managers, society-scoped stats, 404 on unpublished show |
| D-32 | Med | Admin rent add-payment rolled back a transaction it never opened (validation / not-found paths) | unconditional `DB::rollback()` | roll back only when `$began` |
| D-33 | **High** | `/notices-pending` and `/notices-history` listed unpublished notices to any resident; villa-owner portal list filtered on non-existent status `approved` (always empty) and its `show` had no status check | same pattern as D-31 on the other entry points | pending = own submissions for non-managers, history = published only, portal uses `published` and 404s others |
| D-34 | **High** | Owner / Tenant dashboard "Pending Bills / Total Due" counted the user's own pending *Payment* rows, so an unpaid maintenance bill showed ₹0 on web while mobile showed it | wrong source table | outstanding `MaintenanceBill`s on the user's units via `UserFlatResolver` (same rule as `Api\DashboardApiController`) |
| D-35 | Med | Staff dashboard "today's events" used the server's UTC date; wrong between 00:00 and 05:30 IST. The test was time-of-day dependent and failed on MariaDB at 23:01 UTC | `whereDate(..., today())` | society `dayBounds()`; test freezes the clock and covers the boundary |
| D-36 | Med | Web booking form validated `after_or_equal:today` in the app timezone, refusing a valid same-day booking for a society behind UTC | validator ignored society clock | date computed with `SocietyClock::today()` (store + update) |
| D-37 | Med | Add-payment dialog (and 4 sibling dialogs) were a fixed 384px card: clipped on a 360px phone | `w-96` without cap | `max-w-[calc(100%-1.5rem)]`; `ModalWidthContractTest` scans all views |
| D-38 | Med | **Forgot-password and login never displayed the flashed confirmation**: after "Send Reset Link" and after a successful reset the user saw no feedback | views did not render `session('success')` | notice block on both pages (found only in the real browser) |
| T-01 | Test | `PendingMigrationsRollbackTest` leaked committed rows on MariaDB (DDL implicit commit) and broke a later test in the same shard DB | missing cleanup | test removes its rows |

IDs are not contiguous (D-15…D-30 reserved, unused). Smaller scoping fixes from the audit are covered by the test classes named above.

## Booking advance-window — root cause and tests

- The headless report ("a date beyond the window showed no clear refusal") was **a script artifact**: the page did refuse with
  *"[DEMO] Clubhouse can be booked up to 30 days in advance."* (verified in a real 360px browser for +40 d and +120 d), and the script
  matched the wrong text. No product defect existed in that path.
- The rule is **not invented**: `facilities.advance_booking_days` (default **30** when unset) enforced in
  `FacilityBookingService::dayClosedReason()`, shared by the web form and `POST /api/v1/facility-bookings`; staff with
  `manage_facilities` may book outside it (never into a taken slot) — unchanged, documented behaviour.
- A *real* neighbouring defect was found and fixed (D-36). `FacilityAdvanceWindowTest` pins, on **both channels**: last day of the
  window accepted, one day beyond refused with the "N days in advance" message, yesterday refused, today accepted, garbage dates refused,
  nothing created by any refusal, and the society-vs-UTC calendar in both directions (IST ahead of UTC, New York behind it).
- Product ambiguity (none blocking): the advance window counts calendar days in the society's timezone from "today", inclusive of day N.

## Verification (final)

See [CURRENT_STATUS.md](CURRENT_STATUS.md) for the exact suite totals and commit state. Browser scripts live in the session scratchpad
(not the repo): `flows.js` 28/28 (desktop), `guard.js` 31/31 (390px), `phone.js` 77/77 pages at 320 / 360 / 390 px, `phoneflow.js` 29/30,
`auth.js` 6/6. Routes exercised: login, logout, forgot / reset password, `/dashboard`, `/my/*`, `/guard*`, `/complaints*`,
`/notices*`, `/events`, `/facility-bookings*`, `/visitors*`, `/profile/edit`, `/society-rules`, `/admin/*` (rents, tenants, shifts,
society-documents, villa-assignments), `/users*`, `/owners*`, `/flats`, `/villas`, `/payments*`, `/utility-bills`, `/reports/financial`,
`/settings`, `/access-passes`, `/notifications` (77 distinct pages in `phone.js`).

## Credentials, delivery, other open items

- **Credentials NOT ROTATED.** `php artisan credentials:audit` on 2026-10-11 prints the same fingerprints as the 2026-10-10 baseline for
  all 10 stored secrets and still reports two secrets duplicated in `.env` (exit 1). Rotation needs provider consoles — an operator
  task ([CREDENTIAL_ROTATION.md](CREDENTIAL_ROTATION.md)). Verification step: re-run the audit; every fingerprint must change.
- Real email / SMS / WhatsApp / payment delivery: **NOT TESTED** (needs approved staging credentials and recipients). The reset mail was
  rendered and asserted through `Notification::fake()`; the log driver captured nothing in the browser run, so the mail transport path
  itself is **not** evidenced.
- Android device: **NOT TESTED**. Event SMS stays disabled until its own approved DLT template + a separate society setting exist.
- Dead code: `SuperAdmin\SettingsController` confirmed unrouted (no route, view, test or reference); **kept**, with
  `DeadControllerStaysUnroutedTest` so it cannot be wired up by accident (it predates the kill switch and secret masking).
  Deleting it is an owner decision (P3).
- P3 notes: icon-only action buttons on list pages are smaller than 28px on phones (27–54 per page on `/users`, `/visitors`,
  `/complaints`, `/notices`); complaint list rows have no text link (icon only).
- Not covered: valid access-pass QR scan in a browser (camera), real push, payment gateway (none exists), load / soak testing.

Release decision: **NOT READY for production; READY FOR STAGING** (blockers: credential rotation, staging delivery verification,
Android device pass, event SMS template).
