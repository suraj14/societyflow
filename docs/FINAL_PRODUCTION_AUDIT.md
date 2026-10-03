# SocietyFlow — Final Production Audit

**Date:** 2026-09-29 · **Scope:** this local dev checkout
(`C:\xampp\htdocs\SocietyFlow`), SQLite dev DB, no Flutter SDK, no access to
the live server (`https://societyflow.joboapps.com`).

This document synthesizes eleven prior QA/hardening sessions (full trail in
[CURRENT_STATUS.md](CURRENT_STATUS.md) and [TODO.md](TODO.md)) plus fresh
verification performed for this pass. Where a line says "verified this
session," it was actually re-checked now — greep'd, tested, or read — not
carried forward from memory. Where it says "per prior session," it is cited
from this repo's own recorded history, not re-derived from scratch (re-
deriving eleven sessions' worth of audit in one pass would itself be
guessing, not verifying).

---

## 1. Architecture

| Layer | Location | Notes |
|---|---|---|
| Web | `routes/web.php` (~400 routes), `app/Http/Controllers/*` (~90 files) | Blade + Tailwind (CDN), Alpine.js |
| Mobile API | `routes/api.php`, `app/Http/Controllers/Api/*` | `/api/v1`, Sanctum bearer tokens, hand-built array formatters |
| Auth | `AuthController` (web), `Api/AuthApiController` + `Api/OtpAuthController` (mobile) | Email+password, phone OTP |
| Roles | Spatie `laravel-permission`, guard `web` | `Super Admin, Admin, Owner, Villa Owner, Apartment Owner, Tenant, Staff, Guard, Accountant` — **9 roles in the live system**, not the 5 this brief's own role matrix names (see §7) |
| Notifications | `app/Services/{DynamicEmailService,EmailTriggerService,SmsService,PushNotificationService,FirebaseService}.php` | Event→listener via `EventServiceProvider` |
| Scheduler | `app/Console/Kernel.php` | Event status updates, bill reminders |
| Mobile app | `societyflowmob/` (Riverpod + Dio) — **not** `societyflow/` and **not** under a `D:\` drive; this environment is `C:\xampp\htdocs\SocietyFlow\societyflowmob` |
| DB | SQLite dev (`database/database.sqlite`, git-ignored, real PII), MySQL/MariaDB is the production target | Two engines hide each other's bugs — see §12 |

**Database**: 70+ migrations applied, 0 pending. Core tables: `societies`,
`buildings`, `flats` (apartments+villas via `property_type`), `owners`,
`tenants`, `residents` (legacy, still read), `users`, `visitors`,
`visitor_logs`, `access_passes`, `complaints`, `maintenance_bills`,
`payments`, `facilities`, `facility_bookings`, `services`, `notices`,
`events`, `system_settings`.

**Three overlapping person models** (`residents` legacy, `owners`,
`tenants`, plus `users.owner_id`/`users.tenant_id`) are consolidated at read
time by `App\Support\UserFlatResolver` and (for visitor-specific scoping)
`App\Support\VisitorPropertyScope`. This is a known, accepted design debt
(`P2-2`, open, "design first") — not something this pass re-architects.

## 2. Modules present and their controllers

| Module | Web controller | API controller | Status |
|---|---|---|---|
| Auth | `AuthController` | `Api/AuthApiController`, `Api/OtpAuthController` | working, tested |
| Societies | `SuperAdmin/SocietyController` | — | working, isolation tested |
| Buildings/Flats/Villas | `FlatController`, `VillaController`, `VillaAreaController` | `Api/DashboardApiController` (read) | working |
| Owners/Tenants | `OwnerController`, `TenantController`, `Admin/{Apartment,Villa,Tenant}AssignmentController`, `Admin/PropertyOccupancyController` | — | working; `Owner` is now a real, canonical role (migrated session 9) |
| Visitors | `VisitorController`, `VillaOwner/VisitorController`, `GuardController` | `Api/VisitorApiController` | working, property/role-aware (session 10), API approval path test-covered (session 11) |
| Access Pass / QR | `AccessPassController`, `GuardController::scan` | `Api/AccessPassApiController` | working, 27+9 tests, live-verified end to end on the web gate; Flutter side written but not compiler-verified (SDK absent) |
| Complaints | `ComplaintController` | `Api/ComplaintApiController` | working |
| Bills/Payments | `PaymentController`, `Admin/RentController`, `UtilityBillController` | `Api/PaymentApiController` (if present) | working, bill-first UX (session 8), cross-property IDOR closed (sessions 8-9) |
| Facilities | `FacilityBookingController` | `Api/FacilityApiController` | working, double-booking lock tested |
| Events | `EventController` | — (web-only today) | working; Super Admin lockout + `visible_users` crash fixed this session |
| Notices | `NoticeController` | — | working, show()-leak closed (session 4) |
| Settings | `SettingsController`, `SuperAdmin/SettingsController` | — | working, persistence bugs fixed session 8; permission-matrix UI is cosmetic only (`P2-16`, documented, not enforced) |
| Reports | `ReportController` | — | working, `hasRole('Owner')` dead-role bug fixed session 7 |
| Dashboard | `DashboardController` | `Api/DashboardApiController` | per-role dashboards exist for all 9 roles (Accountant added session 7) |

## 3. Dead code / unused files — checked this session

- `dd()`, `dump()`, `var_dump()`: **zero occurrences** in `app/`.
- Debug routes (`Route::get('debug...')`, `test-...`): **zero** in
  `routes/`.
- Hardcoded credentials/secrets: **zero** — every `password`/`secret`
  match in `app/` is a validation rule, a `SystemSetting::get()` DB read, or
  a cast declaration. (31 dev-DB *accounts* still use weak passwords —
  `P0-1`, a data issue, not a code one, deliberately not rotated without
  being asked.)
- `TODO`/`FIXME`: **3 matches**, 2 are doc cross-references (not real
  TODOs), 1 is a genuine stub (`DashboardController.php:378`,
  "assigned_services: 0 — service assignment feature not built").
- Dead markup removed this session: 3 `display:none` divs in
  `layouts/app.blade.php` with zero JS consumers anywhere in the repo
  (verified by full-repo grep before deleting).
- Per prior sessions: 10 files (~115 KB) already removed session 7 (dead
  sidebar partials, 7 orphaned "instant update" JS files); the residents
  `Route::redirect` swallowing POST already fixed session 2 — re-swept this
  session, no second instance found.
- **Not re-swept this session** (would need its own pass, not safe to
  compress into this one): unused Blade views, unused CSS, a systematic
  "grep every route against every controller method" audit. Nothing found
  by chance during this session's other work was left in place.

## 4. Known, accepted architectural debt (not fixed, not hidden)

From `TODO.md`, still open, not attempted this pass because each is a
multi-file/design decision, not a bug:

- `P1-7` — `flats.owner_id` unset on ~89% of dev rows; backfill needs human
  judgement, not inference (documented at length in `UserFlatResolver`).
- `P1-8` — `SocietyMiddleware` (subscription enforcement) unregistered.
- `P2-2` — three overlapping person models (`residents`/`owners`/`tenants`).
- `P2-14` — legacy `Villa Owner`/`Apartment Owner` roles kept registered
  alongside the new canonical `Owner` (safe coexistence, not yet fully
  retired from ~15 display-only strings).
- Schema parity gaps between SQLite and MySQL (`P1-15/17/18/19`,
  `docs/SCHEMA_PARITY.md`) — each documented with its own regression test
  plan, none silently ignored.

## 5. Test suite (this session, actually run)

`php artisan test` (in-memory-equivalent SQLite): **586 passed, 1737
assertions, 0 failed, 0 errors, 0 skipped.** Full raw output retained in the
session's task log. Not re-run again after this document was written since
no further code changes followed it — see `FINAL_PRODUCTION_READINESS.md`
for the exact run this number comes from.

MySQL/MariaDB: **not re-run this session** — last confirmed on MariaDB
10.4.32 was 472/1261 (session 4) and 442/1187 (session 2); several
sessions' worth of new tests since then have not been re-verified against
that engine. This is a real, open gap — see the readiness doc.
