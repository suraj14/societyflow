# SocietyFlow — current status

Update this after every major task. Keep it short; link, don't paste.

**Last updated:** 2026-09-12

## Branch and commit

- Branch: `production-readiness` (`main` untouched at `2f65516`)
- Latest verified commit: `6aa8512` — settings/complaint browser fixes
- Working tree: clean

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

Property-type architecture and guard experience are complete and committed.
Deliverables written: [PROPERTY_TYPE_ARCHITECTURE.md](PROPERTY_TYPE_ARCHITECTURE.md),
[PRODUCTION_QA_REPORT.md](PRODUCTION_QA_REPORT.md),
[FINAL_SYSTEM_STATUS.md](FINAL_SYSTEM_STATUS.md).

## Next recommended task

**P0-3: run the suite against MySQL.** It is the largest unknown left and the
only blocker that is engineering work rather than an operator decision.
After that, P1-12 (guard approval actions, photo capture, blacklist,
Flutter guard screens).
