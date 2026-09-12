# SocietyFlow — current status

Update this after every major task. Keep it short; link, don't paste.

**Last updated:** 2026-09-12

## Branch and commit

- Branch: `production-readiness` (`main` untouched at `2f65516`)
- Latest verified commit: `1719c31` — society property-type architecture (web + API + mobile)
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
- Release **APK 52.3 MB** / **AAB 42.3 MB** — verified 2026-09-09, but the Android
  build is **currently blocked**: the Android Studio JDK install is incomplete (P0-9)
- Flutter project now 13 MB on disk (build/ removed); backend source 11 MB
- Cleaned: removed 3.36 GB of generated/cache content + 2 JVM crash dumps; dropped
  unused `intl` and `cupertino_icons`. See [FLUTTER_PRODUCTION_DEPLOYMENT.md](FLUTTER_PRODUCTION_DEPLOYMENT.md)
- **Not publishable yet**: `applicationId` is `com.example.*`, release builds are
  debug-signed, and the JDK is broken (P0-7, P0-8, P0-9)
- No device/emulator run in this environment

## Tests

- Backend suite: **232 passed, 581 assertions** (2026-09-12); `route:list` (393) and
  `composer validate` both clean
- Flutter: `analyze` 4 informational, `test` 21/21 (2026-09-12)
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
- **Android builds blocked**: Android Studio JBR is incomplete (no `jvm.cfg`), no
  other JDK installed — see [DISK_SPACE_AND_DEPLOYMENT.md](DISK_SPACE_AND_DEPLOYMENT.md) §5
- **D: drive 97% full (4 GB free)** — project is only 233 MB of it; biggest safe
  win is the 5.7 GB `D:$RECYCLE.BIN`
- Mobile release blocked on operator: real `applicationId` (P0-7) and a release
  keystore (P0-8) — both are identity/credential decisions

## Current task

Society property-type architecture — foundation, enforcement, config UI, API,
navigation, dashboard and Flutter model are done and committed. Remaining
brief sections: Guard experience (§8), resident "My Property" screens (§7),
query scoping expressed through the new helpers (§4), production cleanup (§24).

## Next recommended task

Finish the property-type brief: Guard experience (§8), resident "My Property"
screens (§7), then the 2 open browser bugs above. See
[PROPERTY_TYPE_ARCHITECTURE.md](PROPERTY_TYPE_ARCHITECTURE.md) §8 for what is
not yet done.
