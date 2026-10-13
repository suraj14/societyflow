# SocietyFlow — development instructions

Residential society management SaaS: a Laravel 10 web app + REST API and a
Flutter mobile client. Multi-tenant by `society_id`. Read this before
touching anything; it records decisions that are not derivable from the code.

Detailed docs live in [docs/](docs/): [TESTING.md](docs/TESTING.md),
[PRODUCTION_CHECKLIST.md](docs/PRODUCTION_CHECKLIST.md). Keep them current.

**Start every session by reading [docs/CURRENT_STATUS.md](docs/CURRENT_STATUS.md)**
— it holds the current branch, verified commit, open bugs, blockers and the
next task. Prioritised work is in [docs/TODO.md](docs/TODO.md) (P0–P3; a task
is `done` only when verified). **Update both after every task.** Keep them
short; link, don't paste.

---

## 1. Architecture at a glance

| Layer | Where | Notes |
|---|---|---|
| Web (Blade + Tailwind) | `routes/web.php`, `app/Http/Controllers/*` | ~90 controllers, 200+ views under `resources/views/` |
| Mobile API v1 | `routes/api.php`, `app/Http/Controllers/Api/*` | Sanctum bearer tokens, hand-built array formatters, `/api/v1/health` |
| Auth | `AuthController` (web), `Api/AuthApiController` + `Api/OtpAuthController` (mobile) | Email+password and phone OTP |
| Roles/permissions | Spatie `laravel-permission`, guard `web` | Seeded by `database/seeders/RolesAndPermissionsSeeder.php` |
| Notifications | `app/Services/{DynamicEmailService,EmailTriggerService,SmsService,PushNotificationService,FirebaseService}.php` | Events → listeners in `app/Providers/EventServiceProvider.php` |
| Scheduler | `app/Console/Kernel.php` | Event status updates, bill reminders |
| Flutter | `societyflowmob/` | Riverpod + Dio; see §4 |

Dev DB is SQLite (`database/database.sqlite`, **git-ignored, contains real
PII**). Production target is MySQL. The two engines hide each other's bugs —
see §8.

## 2. Roles

`Super Admin`, `Admin`, `Apartment Owner`, `Villa Owner`, `Tenant`, `Staff`,
`Guard`, `Accountant`. There is **no role named `Owner`** — code that checks
`hasRole('Owner')` is a bug (see `PaymentController::receipt` history).

- **Super Admin**: global, `society_id = null`. `Gate::before` in
  `AuthServiceProvider` grants every ability.
- **Admin**: manages one society.
- **Apartment Owner / Villa Owner**: residents who own units. A user may hold both.
- **Tenant**: rents a unit. **Staff / Guard / Accountant**: society workers.
  `Guard` and `Accountant` have no dedicated dashboards.

Web routing authorises mostly by **role name**; the 36 permissions are only
partly wired. `RestrictStaffAccess` (`restrict.staff`) is a URL-prefix
denylist — new routes outside it are implicitly staff-accessible.

## 3. Society (tenant) isolation — the rule

**Scope by default; exempt Super Admin explicitly.**

```php
if (!$user->hasRole('Super Admin')) {
    $query->where('society_id', $user->society_id);
}
```

Never write `if ($user->hasRole('Admin')) { scope }` — every role you did
not name falls through unscoped. This exact pattern leaked notices to six
roles and payments to Guard/Accountant.

For route-model-bound records use
`app/Http/Controllers/Concerns/AuthorizesSocietyAccess.php`
(`authorizeSocietyAccess($model)`, `authorizeOwnedRecord($model)`); it is
already mixed into `BaseController`. Group `orWhere`/`orWhereHas` inside a
closure — an ungrouped OR escapes the scoping above it.

Regression suites: `tests/Feature/WebTenantIsolationTest.php`,
`CrossSocietyListingTest.php`, `NoticeIsolationTest.php`,
`AuthorizationIsolationTest.php`, `SearchScopeLeakTest.php`.

## 4. Flutter (`societyflowmob/`)

- `lib/core/config/api_config.dart` — base URLs, `useProduction` flag, endpoint constants.
- `lib/core/services/server_discovery.dart` — LAN discovery; **must** confirm
  `/api/v1/health` returns `application: societyflow` before trusting a host.
  `ServerDiscovery.overrideBaseUrl` exists for tests.
- `lib/core/services/api_service.dart` — Dio client, bearer auth, 401 → `onSessionExpired`.
- `lib/features/<module>/{models,providers,screens}` — one folder per module.
- `lib/features/shell/main_shell.dart` — bottom nav; Guard/Staff get a different home.
- JSON keys in `features/*/models/*.dart` are the **API contract**. When a
  backend column is renamed, fix the mapping in the API formatter — do not
  rename the JSON key without a coordinated app release.
- Tests: `test/models_test.dart` (payload parsing), `test/widget_test.dart` (boot).

## 5. Key database relationships (the traps)

- `flats` holds both apartments (`building_id`) and villas (`villa_area_id`),
  discriminated by `property_type`.
- **`flats.owner_id` → `owners.id`, NOT `users.id`.** Compare it to
  `$user->owner_id`, never `$user->id`. Use `App\Support\UserFlatResolver`
  to get a user's units — it covers residency, ownership (with the address
  fallback real data needs) and tenancy.
- Three overlapping person models: `residents` (legacy), `owners`, `tenants`,
  plus `users.owner_id` / `users.tenant_id`. Consolidation is open work.
- `services` is a **global** catalogue (no `society_id`) — by design.
- Money: `payments.status` is `pending|success` (never `paid`);
  `visitors` uses `expected_entry_time` (no `visit_date`); `events` uses
  `event_name`/`location`/`start_date` (no `title`/`venue`/`event_date`).
- Document numbers (`CMP…`, `MB…`, `BKG…`, `EMP…`) come from
  `app/Models/Concerns/GeneratesDocumentNumber.php`. Never `count()+1`.
- Enum-ish columns are SQLite `CHECK` constraints. If a validation rule
  accepts a value, the column must too — `tests/Feature/SchemaContractTest.php`
  asserts this. Widening a constraint on SQLite means a table rebuild
  (pattern: `2026_09_07_000003_repair_visitors_table.php`).

## 6. API conventions

- Prefix `/api/v1`, `auth:sanctum` on everything except `/health` and `/auth/*`.
- Response shape: `{ success, data, message?, meta? }`; lists paginate.
- Formatters are private `formatX()` methods in each controller. Verify every
  attribute they read exists (`tests/Feature/ApiContentVisibilityTest.php`).
- Image paths go through `ApiImageHelper::storageUrl()`; the app prepends the base URL.
- `ResolvesUserFlats` trait delegates to `UserFlatResolver`.
- Auth endpoints are rate-limited and enumeration-safe — preserve that.

## 7. Security requirements

- **Never hardcode credentials.** New accounts get
  `App\Helpers\TemporaryPassword::generate()`, delivered via the `UserCreated`
  event, never echoed to the creating admin's screen. Seeders in the default
  `db:seed` path must not ship known passwords.
- Password change / reset must revoke other Sanctum tokens.
- Validate with explicit rule sets; never `$model->update($request->all())`.
- Don't add debug routes, `dd()`, or per-request `Log::info` dumps.
- Don't weaken `VerifyCsrfToken` (the `tokensMatch` override was removed deliberately).
- CORS origins come from `CORS_ALLOWED_ORIGINS`; default `*` is for local dev only.
- Push: FCM **v1** via `FirebaseService` only. The legacy `fcm/send` endpoint is dead.
- **One-time codes** (`App\Models\MobileOtp`): issued and spent only through `MobileOtp::issue()` / `check()` (4 digits from `random_int`, stored as an HMAC, one use, 5 min, 5 wrong tries, per-purpose, atomic). The code appears in an API answer only when `OTP_EXPOSE_DEV_CODE=true` on a local/development/testing server - never in production, never in a log. Unknown numbers get the same answer as known ones.
- **No `env()` outside `config/`** (it is null after `config:cache`): read `config('sms.*')`, `config('otp.*')` etc. `SmsConfigCacheSafetyTest` fails the build otherwise.
- `email_logs.variables` never holds a password, reset link or code (`EmailLog::scrubbed`); the notification job payload is encrypted (`ShouldBeEncrypted`).
- **Never seed, QA, or browser-test against a DB with real provider credentials without EXTERNAL_MESSAGING_ENABLED=false.** Per-society SMTP/SMS/WhatsApp settings are stored in system_settings, and the mail layer applies them over MAIL_MAILER at send time - MAIL_MAILER=log alone does not stop a real send. The flag (config/messaging.php) forces mail to log and suppresses SMS/WhatsApp calls; RoyalEdenDemoSeeder refuses to run without it. Test copies should also blank credentials.

## 8. Testing requirements

```bash
./vendor/bin/phpunit                      # in-memory SQLite, no setup
./vendor/bin/phpunit --testsuite Feature --testdox
DB_CONNECTION=mysql DB_DATABASE=... ./vendor/bin/phpunit   # before release
cd societyflowmob && flutter test && flutter analyze
```

- Every bug fix ships with a regression test in `tests/Feature/`.
  Helpers: `tests/TestCase.php` (`makeSociety`, `makeFlat`, `makeResidentUser`,
  `makeUserWithRole`, `makeRole`).
- Seed data before asserting a page renders (`WebSmokeTest` found fatals that
  only appear with rows present).
- Guard list endpoints with query-count assertions (`QueryPerformanceTest`).
- Run against MySQL too: SQLite treats an unknown double-quoted identifier as
  a string literal and returns zero rows instead of erroring; `YEAR()`/`MONTH()`
  are MySQL-only; `"sent"` is an identifier in MySQL. Use bound params and
  driver checks in raw SQL.
- Do not edit a test to make it pass. Fix the app, or explain why the test is wrong.
- `Bus::fake()`/`Event::fake()` only prove a dispatch. Every notification
  also needs a test that runs the real job and asserts a `sent` `EmailLog`
  row — faked tests hid a missing `config/queue.php` (NullQueue dropped every
  email) and `EmailEvent` gaps. See `QueueConfigurationTest`,
  `EmailEventWhitelistTest`.

## 9. Performance requirements

- No `Cache::flush()`, schema introspection (`Schema::hasColumn`), or PDO
  probes in request paths or model hooks. `$fillable` is static.
- Eager-load relations used by formatters/views. Paginate lists.
- Flutter: prefer `const` widgets; models parse defensively (`?.toString() ?? ''`).

## 10. Production-readiness decisions already made

Recorded in commit history on `production-readiness` and in
[docs/PRODUCTION_CHECKLIST.md](docs/PRODUCTION_CHECKLIST.md). Do not undo:

- `database/database.sqlite` is git-ignored (was tracked with real PII).
- Test suite runs on in-memory SQLite; the MySQL test DB dependency was removed.
- `AppServiceProvider::boot()` no longer flushes cache per request.
- `php artisan users:rotate-weak-passwords` exists (report by default,
  `--force` to rotate). **Rotating live credentials is an operator decision** —
  do not run `--force` without being told to.
- Missing views/routes are fixed by restoring the intended feature in the
  existing design idiom (see `resources/views/notices/history.blade.php`),
  not by removing the route.

## 11. Known limitations (not regressions — open work)

- No payment gateway; Razorpay/Stripe keys are stored but nothing charges.
- No subscription enforcement; `SocietyMiddleware` is unregistered.
- No mobile push client (`firebase_messaging` absent, `AppFeatures.enablePushNotifications=false`).
- `flats.owner_id` unset for most rows → ownership falls back to address matching.
- Document numbers can be reissued after deleting the newest record.
- 31 dev-DB accounts still use `password`/`password123` until rotated.
- 4 Flutter `DropdownButtonFormField value:` deprecations left intentionally.
- Views still missing: `users.create`, `users.edit`,
  `admin.push-notifications.templates`, `admin.push-notifications.edit-template`.

## 12. Do not modify unnecessarily

- `.env` (secrets; `APP_ENV=production` on the dev box is mislabelled, not a bug to "fix" here).
- `database/database.sqlite` and `storage/backups/*` (real data + backups).
- Applied migrations under `database/migrations/` — add a new one instead.
- `resources/views/**` design/markup — fix behaviour, keep the Tailwind idiom.
- `societyflowmob/lib/features/*/models/*.dart` JSON keys (API contract).
- Root dev scripts (`START-DEV.bat`, `start-server.ps1`, etc.) the owner uses.
- `main` branch — work on `production-readiness` or a feature branch.

## 13. Commands

```bash
# Backend
php artisan serve --host=127.0.0.1 --port=8123
php artisan migrate --force            # forward-only; never migrate:fresh on dev DB
php artisan db:seed                    # safe default path (RolesAndPermissions, SystemSettings, Services, SimpleTwoAdmin)
php artisan route:list
php artisan view:clear                 # after editing Blade during a running server
php artisan storage:setup
php artisan users:rotate-weak-passwords          # report only
php composer.phar validate --no-check-publish
for f in $(git ls-files '*.php'); do php -l "$f"; done | grep -v "No syntax"

# Mobile
cd societyflowmob
flutter pub get
flutter analyze
flutter test
flutter build apk --release
```

Browser verification harness (Playwright, Chromium) lives in the session
scratchpad, not the repo; recreate from `tests/Feature/WebSmokeTest.php`
route lists if needed. Test accounts for each role exist in the dev DB —
see `git log` for the sweep methodology.
