# SocietyFlow — current status

Update this after every major task. Keep it short; link, don't paste.

**Last updated:** 2026-09-16

## Branch and commit

- Branch: `production-readiness` (`main` untouched at `2f65516`)
- Latest verified commit: `c5914c0` — assignment-screen nav gating, instant guard check-in/out
- Working tree: clean
- **Note:** several commits between `6aa8512` and `530a492` (public_id, MySQL
  parity fixes, owner_id FK fixes) landed before this file was updated —
  see `git log 6aa8512..530a492 --oneline` for the full list.

## Backend

- Laravel 10.50 / PHP 8.2; SQLite dev DB, 70 migrations applied, 0 pending
- `composer validate` clean; `route:list` 400 routes
- 31 accounts still use `password`/`password123` (rotation deliberately not run)
- Dev server for verification: `php artisan serve --port=8123`

## Web

- All 8 roles log in and land on the correct dashboard
- Guards now land on `/guard`, not the admin dashboard
- Browser-verified console-clean: Super Admin, Admin, Villa Owner, Tenant,
  Apartment Owner, Guard — see [PRODUCTION_QA_REPORT.md](PRODUCTION_QA_REPORT.md) §3
- **No open browser bugs** (the two long-standing ones were fixed in `6aa8512`)

## Flutter

- `flutter analyze`: 4 informational (intentional deprecations); `test` 21/21
- Release **APK 52.3 MB** / **AAB 42.3 MB** last built 2026-09-09; builds are
  **currently blocked** by an incomplete Android JDK (P0-9)
- **Not publishable**: `com.example.*` applicationId, debug-signed (P0-7, P0-8)

## Tests

- Backend: **260 passed, 674 assertions** (2026-09-12)
- Flutter: 21/21
- MySQL run: **never executed** (no server) — required before release

## Known bugs (open)

None currently open. Previously tracked:

- ~~`/complaints/create` JS "Complaint form elements not found"~~ — fixed `6aa8512`
- ~~Admin `/settings` `Unexpected token '<'`~~ — fixed `6aa8512`, which also
  restored the Language, Storage and Currency tabs that had never rendered

## Recently fixed (this session)

- Guard fell through to the admin dashboard, exposing revenue and dues — `bf15237`
- Rent/Tenant accepted property types the society does not support — `bf15237`
- Seven forms plus `owners/edit` offered both types unconditionally — `bf15237`
- Owner portfolio showed "Apartments: 0" in a villa township — `10b014b`
- Settings page swallowed three tabs; complaint handler never attached — `6aa8512`

## Blockers

- MySQL portability unverified end to end (P0-3)
- **Android builds blocked**: JBR incomplete, no `jvm.cfg`, no other JDK (P0-9)
- Mobile release needs a real `applicationId` (P0-7) and keystore (P0-8)
- Weak credentials on 31 accounts — operator runs `users:rotate-weak-passwords --force`
- `database.sqlite` still in git history — purge is an operator decision
- **Two societies need an admin's review** after the property-type backfill —
  see [FINAL_SYSTEM_STATUS.md](FINAL_SYSTEM_STATUS.md)

## Current task

A 23-section "final master prompt" asked for the full remaining scope
(payment gateways, SMS, email, impersonation, instant-update UI everywhere,
Flutter parity) in one pass. Two blockers were surfaced before writing code:
the brief's 6-role list conflicts with the verified 8-role system (kept the
existing 8, per the user), and the remaining ~15 subsystems are each
independently large — scoped to 3 items done completely rather than 23 done
shallowly (also the user's call). Delivered:

- **Super Admin impersonation** ("Login as Admin") — `SuperAdminSocietyController`
  action menu, banner, audit trail via new `ActivityLog` model.
- **Cross-society authorization audit** — found and fixed 3 controllers with
  zero society check on route-bound show/update/destroy: `ResidentController`,
  `VillaController` (also fixed a `users.id`/`owners.id` confusion bug in the
  owner-role-assignment step, same class as `4e35ffa`), `FacilityBookingController`.
- **UPI payment links** (brief §12) — `UpiPaymentService`, amount/payee/VPA
  all server-resolved, new `GET /bills/{bill}/upi-link`.

Not attempted: Razorpay, Cashfree, Fast2SMS, Zoho SMTP, WhatsApp scaffolding,
instant-update UI pass, Flutter changes for any of this — each needs live
credentials or much broader surface area to verify honestly.

Tests: 33 new (`ImpersonationTest` 11, `CrossSocietyAuthorizationGapsTest` 13,
`UpiPaymentTest` 9), all green on SQLite and MariaDB 10.4.27. Full suite: 380
passing / 1031 assertions (SQLite).

## Follow-up task (2026-09-16)

A further brief asked for full property-type UX / role-based UI / repeated-
field removal / instant updates across every module / Flutter parity in one
pass. Audited what prior sessions already covered (owner/tenant creation,
visitor forms, registration, complaint forms already read `property_type`
from the society) before building anything new. Delivered:

- **ResidentController investigated per explicit ask, not deleted.** Its
  data model is active (Owner/Villa/Tenant assignment controllers still
  write a `Resident` row; dashboards and `UserFlatResolver` read it) but its
  own CRUD screens are orphaned — the index route is dead (redirected to
  `/owners`) and both live nav files link only to that index. Documented in
  the controller's own docblock rather than touched further; authorization
  was already fixed in the prior session.
- **Found and fixed a real orphaned-feature gap**: `admin/{apartment,villa,
  tenant}-assignments` are fully built, tested, society-scoped screens with
  no live link anywhere — only `partials/sidebar.blade.php`, which no
  layout actually includes. Wired into the live sidebar's existing
  property-type-gated groups.
- **One complete instant-update slice** (Phase 4 asked for 15 actions
  across every module; built and fully tested one rather than 15 shallow
  attempts): Guard check-in/check-out now respond with JSON when asked,
  patching counters and removing the visitor card without a page reload,
  with the button disabled on tap to prevent double-submission. A plain
  form POST is unaffected (progressive enhancement). Found seven pre-existing
  "instant update" JS files in `public/js/` that are included by **no**
  view — dead code from an earlier, apparently abandoned attempt at this
  same feature; left untouched rather than wired in unvetted.
- Browser click-through verification of the no-reload behaviour was
  **blocked by a persistent Chromium crash** in this environment (four
  attempts, different flags, confirmed unrelated to the app). Correctness
  rests on 16 passing Guard tests instead — a real gap, not glossed over.

Tests: `GuardInstantUpdateTest` (6, new) + one new
`PropertyTypeNavigationTest` case. Full suite: 387 passing / 1062 assertions
(SQLite); the 54 tests across this session's changed areas pass on MariaDB
10.4.27 too.

## Next recommended task

Pick up the untouched sections one at a time — Razorpay or Cashfree (need
live sandbox credentials), or the remaining 13 of 15 instant-update actions
(module-by-module, not in one sweep). Also worth a look: the seven orphaned
`instant-*`/`universal-instant-refresh` JS files in `public/js/` — either
finish and wire them in, or remove them; right now they're dead weight of
unknown correctness. P0-3 (MySQL) is closed; see `docs/SCHEMA_PARITY.md` for
the constraint-parity follow-ups still open (P1-15 host_user_id, P1-17
tenant delete rules, P1-18 flat-number uniqueness, P1-19 NOT NULL-without-
default columns).
