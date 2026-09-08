# SocietyFlow — current status

Update this after every major task. Keep it short; link, don't paste.

**Last updated:** 2026-09-08

## Branch and commit

- Branch: `production-readiness` (`main` untouched at `2f65516`)
- Latest verified commit: `8be3e6f` — push-template screens restored, update IDOR closed (P1-2)
- Working tree: clean

## Backend

- Boots on Laravel 10.50 / PHP 8.2; SQLite dev DB, 70 migrations applied, 0 pending
- Fresh install (migrate + default seed) verified clean: 17 services, 38 permissions, 0 weak-password accounts
- Dev DB integrity OK; 31 accounts still use `password`/`password123` (rotation deliberately not run)
- Dev server for verification: `php artisan serve --port=8123`

## Web

- All 8 roles log in and land on the correct dashboard
- Browser sweep (Playwright): 648 checks across 8 roles × 81 pages
- Verified in-browser: Super Admin settings (all 10 tabs), `/notices-history` (3 roles)
- **Still failing in-browser** — see Known bugs

## Flutter

- `flutter analyze`: 4 informational (intentional `DropdownButtonFormField value:` deprecations)
- `flutter test`: 12/12
- Release APK builds (52.4 MB)
- No device/emulator run in this environment

## Tests

- PHP: 129 + 76 new (NoticeIsolation 21, CrossSocietyListing 43, UserManagement 7, PushNotificationTemplate 5) — focused runs green; full suite not re-run since `1f37780`
- Full `php artisan test` / `route:list` / `composer validate` closing run **not yet done** for this session
- MySQL run: never executed (no server available) — required before release

## Known bugs (open)

1. `/complaints/create` — JS console error "Complaint form elements not found" (renders, script fails)
2. Admin `/settings` — `PAGEERROR: Unexpected token '<'` (a script fetches HTML where JS expected)

## Recently fixed (this session)

- Push-template list/edit 500 (views missing); `updateTemplate` had no society check (IDOR); save redirected to edit page instead of list — `8be3e6f`
- `/users/create` and `/users/{id}/edit` 500 (views missing); Super Admin could never create a user (no society selector); update/destroy returned raw JSON to browser forms — `c601c78`
- Notice listings leaked every society to 6 non-admin roles (`NoticeController` index/pending/history)
- `/notices-history` 500 for every role — view was missing
- Super Admin `/settings` 500 on every tab — phantom `super-admin.settings.*` routes in 4 views

Earlier session (13 commits): see `git log 5913335..1f37780` and [PRODUCTION_CHECKLIST.md](PRODUCTION_CHECKLIST.md).

## Blockers

- Weak credentials on 31 accounts — needs operator to run `users:rotate-weak-passwords --force`
- `database.sqlite` still in git history — purge is an operator decision
- MySQL portability unverified end to end

## Current task

Browser-facing verification of every role and module — P1-1 and P1-2 done; P1-3 (complaints/create JS error) is next.

## Next recommended task

Resume the sweep: fix the 2 open bugs above, then run per-role CRUD/validation/search/pagination flows, then the closing run (`php artisan test`, `route:list`, `composer validate`) and update this file.
