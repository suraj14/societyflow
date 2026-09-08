# SocietyFlow — TODO

Status values: `open` · `in progress` · `blocked` · `done (verified <commit>)`.
A task is `done` only when its tests pass and, where it has a UI, it was
checked in a browser. Update after each task. Keep entries short.

**Last updated:** 2026-09-08 · see [CURRENT_STATUS.md](CURRENT_STATUS.md)

---

## P0 — Blocking production

| # | Task | Module | Status | Relevant files | Tests required |
|---|---|---|---|---|---|
| P0-1 | Rotate the 31 accounts using known default passwords | Auth | **blocked** — operator must run `php artisan users:rotate-weak-passwords --force` (locks users out until they read the email; confirm mail works first) | `app/Console/Commands/RotateWeakPasswords.php` | Re-run command in report mode → 0 rows |
| P0-2 | Decide on purging `database.sqlite` from git history | Repo | **blocked** — history rewrite is an operator decision | `.gitignore` (already ignores it) | `git log --all -- database/database.sqlite` shows no reachable commits |
| P0-3 | Run the full suite against MySQL | DB | open | `phpunit.xml`, `docs/TESTING.md` | `DB_CONNECTION=mysql ./vendor/bin/phpunit` green |
| P0-4 | Verify money columns keep decimals on MySQL (`NUMERIC(10,0)` in SQLite schema) | Bills/Payments | open | migrations creating `maintenance_bills`, `payments`; add corrective migration if truncated | Insert 1234.56, read back 1234.56 on MySQL |
| P0-5 | Production env hardening: HTTPS `APP_URL`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, `CORS_ALLOWED_ORIGINS`, real queue driver + worker, `schedule:run` cron | Deploy | open | `.env` (operator), `config/cors.php`, `app/Console/Kernel.php`, `docs/PRODUCTION_CHECKLIST.md` | Manual checklist sign-off |
| P0-6 | Mobile: `useProduction=true`, HTTPS `prodUrl`, drop `usesCleartextTraffic` | Flutter | open | `societyflowmob/lib/core/config/api_config.dart`, `android/app/src/main/AndroidManifest.xml` | `flutter build apk --release`; login against prod URL |

## P1 — Important functionality

| # | Task | Module | Status | Relevant files | Tests required |
|---|---|---|---|---|---|
| P1-1 | Restore `users.create` and `users.edit` views (500 today) | Users | open | `resources/views/users/{create,edit}.blade.php` (new), `app/Http/Controllers/UserController.php` | `WebSmokeTest` create/edit rows; browser check as Admin + Super Admin |
| P1-2 | Restore push-notification `templates` and `edit-template` views (500 today) | Notifications | open | `resources/views/admin/push-notifications/{templates,edit-template}.blade.php` (new), `Admin/PushNotificationController.php` | `WebSmokeTest`; browser check |
| P1-3 | Fix `/complaints/create` JS "Complaint form elements not found" | Complaints | open | `resources/views/complaints/create.blade.php` | Browser check: no console errors, form submits |
| P1-4 | Fix Admin `/settings` `Unexpected token '<'` page error (script fetching HTML) | Settings | open | `resources/views/settings/index.blade.php`, `app/Http/Controllers/SettingsController.php` | Browser check: no pageerror on all tabs |
| P1-5 | Per-role CRUD / validation / search / pagination browser flows for every module | All | in progress (sweep paused) | scratchpad Playwright harness; route list in `tests/Feature/WebSmokeTest.php` | Sweep report: 0 × 5xx, 0 error signatures, 0 cross-society hits |
| P1-6 | Closing run: `php artisan test`, `route:list`, `composer validate`, clean boot | All | open | — | All green; recorded in `CURRENT_STATUS.md` |
| P1-7 | Backfill `flats.owner_id` (unset on ~89% of rows) and retire the address-matching fallback | Owners/Flats | open | `app/Support/UserFlatResolver.php`, new artisan command + dry-run report, new migration | `UtilityBillAccessTest` resolver cases; dry-run diff reviewed before write |
| P1-8 | Register `SocietyMiddleware` and build a subscription-expired page | SaaS | open — **only if explicitly requested** | `app/Http/Middleware/SocietyMiddleware.php`, `app/Http/Kernel.php`, `routes/web.php` | Feature test: expired society → 402/redirect; trial/active → pass |
| P1-9 | Mobile push client (`firebase_messaging`) so visitor approvals reach residents | Flutter/Notifications | open | `societyflowmob/pubspec.yaml`, `lib/core/services/`, `AppFeatures.enablePushNotifications` | Device test: guard registers visitor → resident notified |
| P1-10 | Per-society counter table so document numbers are never reissued | Bills/Complaints | open | `app/Models/Concerns/GeneratesDocumentNumber.php`, new migration | `DocumentNumberTest`: delete newest, next number differs |

## P2 — Improvements

| # | Task | Module | Status | Relevant files | Tests required |
|---|---|---|---|---|---|
| P2-1 | Replace `RestrictStaffAccess` URL-prefix denylist with permission checks | Auth | open | `app/Http/Middleware/RestrictStaffAccess.php`, `RolesAndPermissionsSeeder.php` | Feature test per staff-forbidden route |
| P2-2 | Consolidate `residents` / `owners` / `tenants` identity models | Owners/Tenants | open — design first | `app/Models/{Resident,Owner,Tenant,User}.php`, `UserFlatResolver.php` | Full suite + data migration dry run |
| P2-3 | Introduce FormRequest classes for the ~90 inline validators | All | open | `app/Http/Requests/` (new) | Existing feature tests stay green |
| P2-4 | Move file uploads off `public_path()` + `chmod 0777` onto the Storage facade | Uploads | open | `UserController`, `NoticeController`, `FacilityController`, `OwnerController` | Upload test writes to configured disk |
| P2-5 | Drop orphan `flats_old` table (after confirming nothing reads it) | DB | open | new migration | `SchemaContractTest` asserts absence |
| P2-6 | Extract shared villa/apartment resolution duplicated in `AuthApiController::formatUser` and `ProfileApiController::show` | API | open | those two controllers, `UserFlatResolver.php` | `AuthSecurityTest::test_login_and_me` |
| P2-7 | Flutter: call `/bills`, `/payments/history`, `/notices/{id}` (read receipts), `/events/{id}` — add pagination | Flutter | open | `lib/features/{payments,notices,events}/providers/*` | Widget/provider tests with mocked Dio |
| P2-8 | Add model factories; consolidate the ~44 seeders | Tests/DB | open | `database/factories/` (new), `database/seeders/` | Suite green; `db:seed` idempotent |
| P2-9 | Make `ServerDiscovery` injectable via a provider rather than a static override | Flutter | open | `lib/core/services/server_discovery.dart` | `test/widget_test.dart` |
| P2-10 | Switch demo/test seeders with fixed passwords to generated ones or a production guard | Seeders | open | `DemoUsersSeeder`, `ComprehensiveTestDataSeeder`, `GuardRoleSeeder`, etc. | Fresh seed → 0 weak passwords (already asserted for default path) |

## P3 — Nice-to-have

| # | Task | Module | Status | Relevant files | Tests required |
|---|---|---|---|---|---|
| P3-1 | Migrate 4 `DropdownButtonFormField value:` → `initialValue:` after device verification | Flutter | open | `complaints_screen.dart`, `facilities_screen.dart`, `visitors_screen.dart` | `flutter analyze` 0 issues; manual dropdown check on device |
| P3-2 | Avatar upload (API endpoint + `edit_profile_screen`) — `image_picker` already a dependency | Profile | open | `Api/ProfileApiController.php`, `lib/features/profile/screens/edit_profile_screen.dart` | API test + widget test |
| P3-3 | Rename `*_temp_*` / `*_rebuild_*` index names left by SQLite table rebuilds | DB | open | new migration (drop + create) | `SchemaContractTest::test_visitors_table_has_its_lookup_indexes` |
| P3-4 | Tidy root: `composer.phar` (3.2 MB tracked), 6 status `.md`/`.txt` files | Repo | open — confirm owner doesn't rely on `php composer.phar` | root | — |
| P3-5 | Dashboards for `Guard` and `Accountant` roles (currently generic) | Dashboard | open | `DashboardController.php`, new views | `WebSmokeTest` dashboard rows for both roles |
| P3-6 | Extract a service layer from the 700–900-line controllers (`SettingsController`, `FacilityBookingController`, `PaymentController`) | Code quality | open | those controllers | Existing suites green |

---

## Done (verified)

Kept brief; full detail in commit messages `git log 5913335..HEAD`.

| Task | Commit |
|---|---|
| Test suite runnable (in-memory SQLite); 3 schema defects; visitor FKs/indexes | `eb8c39e` |
| Hardcoded passwords removed; per-request `Cache::flush()` removed; owner creation transactional | `b155708` |
| IDOR closed in Payment/Flat/Owner/Facility/Complaint controllers | `016f90c` |
| UtilityBill policy; `manage_settings` permission; `owner_id` FK fixed in 25 sites; `UserFlatResolver` | `90c499f` |
| Dashboard columns; villa-owner visitor form; visitor-approved email attributes | `d8fc9a1` |
| Event/Facility API payloads; event visibility; document numbering; services dedupe | `ac884da` |
| Search-scope leaks; utility-bill screens; broken seeder removed | `5780a0d` |
| Mobile server-identity check; analyzer 121→4; 12 Flutter tests; APK builds | `c6b9ee5` |
| Token revocation on password change; CSRF override removed; dead middleware/debug code | `ff5fa2a` |
| FCM v1 wiring | `ec63bca` |
| `getFillable()` introspection (94→4 queries); CORS configurable | `5202d7e` |
| MySQL portability (2 queries); root debug scripts removed; docs | `8b54a65` |
| Fresh install: services wipe bug; seeder credentials | `1f37780` |
| Notice cross-society leak; `notices.history` view; Super Admin settings routes | `53ec880` |
