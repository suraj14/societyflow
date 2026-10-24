# SocietyFlow release-readiness record (2026-10-23)

Supersedes nothing: [RELEASE_READINESS_2026-10-21.md](RELEASE_READINESS_2026-10-21.md) stays as the historical record of that day.
Status words: **PASS** (run, exit code and result inspected), **FAIL**, **BLOCKED** (needs a person, a provider, a download or a decision), **NOT RUN**, **PREVIOUSLY REPORTED** (an earlier session's result, not re-run now).
Nothing here was committed, deployed, or sent to a real recipient. Fake transports prove application behaviour, not provider delivery.

**Verdict: NO-GO for production. GO for continued staging / local use.** Blockers are in section 9.

## 1. PHP upgrade (8.2.12 -> 8.2.35): NOT PERFORMED - BLOCKED on the download

| Item | Result |
|---|---|
| `G:\xampp\php-8.2.35` | exists, **empty** (0 entries): no package was downloaded or extracted. Nothing was fabricated. |
| CLI PHP in use | 8.2.12, ZTS, x64, Visual C++ 2019 (`API20220829,TS,VS16`), OpenSSL 3.0.11, cURL 8.4.0 (`php -v` / `php -i`) |
| PHP in Apache | **8.2.12** - `Server: Apache/2.4.58 (Win64) OpenSSL/3.1.3 PHP/8.2.12` and the same text in `apache\logs\error.log` (no `phpinfo()` page was created) |
| Apache config test | `httpd.exe -t` -> `Syntax OK` (exit 0), Apache Lounge VS17 build |
| VC++ runtime | 2015-2022 x64 installed (v14.50) |
| Official version check | **NOT VERIFIED**: php.net / windows.php.net could not be reached from this machine; only a third-party article mentions 8.2.35 (and CVE numbers) - confirm on php.net before relying on it |
| Rollback readiness | READY: config files backed up with matching SHA256 in `C:\Users\USER\SocietyFlow-backups\php-apache-config-20261023-180131\`; `G:\xampp\php` (179 MB) is untouched; procedure in [PHP_UPGRADE_RUNBOOK.md](PHP_UPGRADE_RUNBOOK.md) |
| Pre-flight | `scripts\php-upgrade-preflight.ps1` (read-only). Dry run against today's PHP: build type, 11 extensions and `composer check-platform-reqs` pass; against the empty folder it fails clearly (exit 1) |

**What to download:** https://windows.php.net/download/ -> the **PHP 8.2** section -> `php-8.2.35-Win32-vs16-x64.zip` (**Thread Safe, x64, VS16**), compare its SHA256 with the value shown there, extract so `php.exe` sits directly in `G:\xampp\php-8.2.35`. Do not use PHP 8.3+ without a separate compatibility review.
**Horizon:** PHP 8.2 receives security fixes only until 31 Dec 2026 (php.net supported-versions policy) - plan the move to a newer branch; it needs the Laravel upgrade below first.

## 2. Composer / Laravel validation (run with the current PHP 8.2.12)

| Command | Exit | Result |
|---|---|---|
| `php composer.phar validate --no-check-publish` | 0 | `./composer.json is valid` |
| `php composer.phar check-platform-reqs` | **2** | `ext-gd` and `ext-zip` **missing** (required by `phpoffice/phpspreadsheet`); every other requirement passes |
| same, with a throw-away ini enabling `gd` + `zip` | 0 | passes - the DLLs exist in `php\ext`; `;extension=gd` / `;extension=zip` are simply commented out in today's `php.ini` (which Apache also uses) |
| `php composer.phar audit` | 100 | **NOT RUN**: `repo.packagist.org` unreachable (curl error 28, timeout). Dependency advisories are therefore **unchecked** |
| `php artisan migrate:status` | 0 | 1 pending before this session's migration step, 0 after |
| `php artisan view:cache` / `view:clear` | 0 | every Blade view compiles |
| `php -l` on 22 changed PHP files | 0 | no syntax errors |

Observations from `composer.lock` (not advisories): Laravel 10.50.0 - the 10.x branch is outside Laravel's published security-support window; `intervention/image` 2.7.2 (2022), `livewire/livewire` 2.12.8, `dompdf/dompdf` 2.0.8, `laravel/sanctum` 3.3.3 are old. Upgrading is a project, not a patch; run `composer audit` from a machine with internet access before release.
`composer.lock` was not modified.

## 3. Security work this round (each with a regression test; "mutation" = the test fails when the fix is removed)

| # | Finding | Severity | Fix | Test |
|---|---|---|---|---|
| S1 | API password login had **no failed-attempt lockout** (only the general 60/min/IP limiter); web login had one | High | shared `App\Support\LoginThrottle` for web + API: society "login attempt limit" (3-10, default 5) per minute and 4x that per 15 minutes, per (e-mail, IP); API answers **429 + `retry_after` + `Retry-After`**; success clears; unknown addresses are limited and answer like a wrong password | `LoginLockoutTest` 13 (mutation: 8 fail without it) |
| S2 | Unknown e-mail answered faster than a wrong password (timing enumeration) | Medium | a dummy password check is spent when no account exists (web + API) | `LoginLockoutTest::test_an_unknown_address_still_costs_a_password_check` |
| S3 | Stored XSS: events calendar put event name / location / description into `innerHTML` unescaped (typed by staff, shown to residents); template picker built names into markup and an inline `onclick` string | High | `esc()` helper in the calendar; picker rebuilt with `textContent` + a click handler | `StoredXssOutputTest` |
| S4 | Admin e-mail template body printed as raw HTML on the template page and the e-mail-log page (Admin -> Super Admin script injection) | Medium | template preview in a script-less sandboxed `<iframe srcdoc>`; log shows plain text | `StoredXssOutputTest` |
| S5 | `json_encode` placed in `<script>` blocks without tag escaping (user list, tenant / resident forms) | Low (defence in depth; PHP already escapes `/`) | `JSON_HEX_TAG\|AMP\|APOS\|QUOT` | `StoredXssOutputTest` |
| S6 | Rent payment proofs stored on the public disk under the **client-supplied extension**; the `mimes` rule judges content. Laravel already blocks `.php/.phtml/.phar`, but `.pht`, `.phps` etc. reached the file name | Medium (depends on the Apache handler config) | `App\Support\SafeUploadName`: extension from the content, random name | `UploadedReceiptNameTest` |
| S7 | Owner / Payments / Utility Bills dropdown lookups listed any society's buildings, floors, flats and villas from a request id; owner update accepted another society's building / villa area | Medium | society-scoped (Super Admin exempt); cross-society ids refused | `OwnerUnitPickerTest`, `UnitLookupScopeTest` |
| S8 | Login, forgot / reset password and OTP reset matched e-mail case-sensitively on SQLite | Medium (availability) | `eloquent-ci` provider + `User::whereEmail()` | `EmailCaseInsensitiveAuthTest` |

**Re-verified in the current code, not assumed** (existing suites re-run, see section 5): session invalidation on password change (`AuthSessionRevocationTest`), token revocation on password reset (`AuthSessionRevocationTest`, `PasswordResetEdgeCasesTest`), inactive / suspended blocking (`InactiveAccountAccessTest`), account-bound OTPs and shared-phone rules (`OtpAccountBindingTest`, `OtpAmbiguousPhoneTest`, HTTP run below), impersonation (`ImpersonationTest`), cross-society isolation (`WebTenantIsolationTest`, `CrossSocietyListingTest`, `NoticeIsolationTest`, `AuthorizationIsolationTest`, `SearchScopeLeakTest`).

Sweeps with no finding: `$request->all()` into `update/create` (none), `$guarded = []` (none), raw SQL built from request input (none), `eval/exec/unserialize` (none), `env()` outside `config/` (one comment only), debug routes (none; one removed earlier), `APP_DEBUG=false`.

**Still open (security):** dependency advisories unchecked (section 2); Laravel 10 / PHP 8.2 support horizons; `public/storage` has no web-server rule forbidding script execution (recommended `.htaccess` below); Sanctum token expiry intentionally unchanged (`expiration` null); 31+ accounts still use default passwords until the operator rotates them; `TRUSTED_PROXIES` must be set behind a proxy or the lockout counts every visitor as the proxy's IP.

## 4. OTP migration `2026_10_22_000001_bind_mobile_otps_to_account`: APPLIED to the local dev database

* Target: `database/database.sqlite` (`DB_CONNECTION=sqlite`), local development only. Production was not touched. Applied on the strength of the Stage E instructions in the request; no other migration was pending.
* Migration is additive: one nullable `user_id` column + one index on `mobile_otps`; no data changes; `down()` drops both.
* **Backup:** `C:\Users\USER\SocietyFlow-backups\database-pre-migrate-otp-account-20261023-180014.sqlite`, made with SQLite `VACUUM INTO` (consistent while Apache is serving); `integrity_check` ok, 0 foreign-key violations, 83 tables / 1,467 rows identical to live; SHA256 `D1C69A4D507948754DFACF4203D9DA26B030BF325897574AC4ADEB08C6014D6D`.
* **Rehearsal** on a copy of that backup: up -> down -> up; `mobile_otps` stayed at 39 rows, integrity ok each time.
* **Live result:** status `Ran` (batch 52), column present, 39 rows unchanged, `integrity_check` ok, 83 tables, 1,468 rows (= 1,467 + the migrations row).
* **Behaviour on a scratch database with synthetic accounts, over real HTTP (20/20):** code issued; wrong code refused; right code + weak password refused without spending the code; reset succeeds; the same code cannot be reused; new password signs in, old one does not; **token issued before the reset is revoked**; a code requested for one account does **not** reset another account sharing the number (same society); no code at all for a number shared across societies and for an Admin on a shared number (and none stored - the count is compared against stored codes for other accounts, so it is not vacuous); expired code refused; five wrong tries burn the code; the sixth reset request in a row from one client gets 429. No code, password or token was printed.

## 5. Test results

| Run | Command | Result |
|---|---|---|
| PREVIOUSLY REPORTED (23 Oct, earlier code) SQLite | `scripts/run-test-shards.ps1 -Engine sqlite -Shards 8` | 2,537 tests / 14,987 assertions / 0 failures / 0 errors / 6 skipped, 8/8 exit 0 |
| PREVIOUSLY REPORTED MariaDB 10.4.32 (temporary instance :3307) | same, `-Engine mariadb` | 2,537 / 14,986 / 0 / 0 / 3 skipped, 8/8 exit 0 |
| **NEW** focused, 38 classes (OTP, auth, password, lockout, isolation, XSS, uploads, smoke, performance) | runner with `-Filter` | 441 tests / 2,040 assertions / 0 failures / 0 errors, 8/8 exit 0 (645 s) |
| **NEW** full SQLite, final code | `scripts/run-test-shards.ps1 -Engine sqlite -Shards 8` | **2,559 tests / 15,151 assertions / 0 failures / 0 errors / 6 skipped, 8/8 shards exit 0** (2,344 s; slowed by other work running alongside) |
| **NEW** full MariaDB 10.4.32, final code (temporary instance, port 3307) | `scripts/run-test-shards.ps1 -Engine mariadb -Shards 8` | **2,559 tests / 15,150 assertions / 0 failures / 0 errors / 3 skipped, 8/8 shards exit 0** (254 s). Two earlier attempts on temporary instances were discarded because the instance itself died (an InnoDB async-I/O assertion on this Windows machine; fixed with `--innodb_use_native_aio=0`) - they are not counted |
| **NEW** deployment tooling on a scratch copy (not CloudPanel) | scripted | package READY; dry run left the live tree byte-identical; mid-way failure stayed in maintenance mode; rollback restored 0-difference; full deploy kept `.env`, uploads, `public/storage`, server-only files; `db-backup.sh` verified on SQLite and MariaDB (82/82 tables, restore proven, failure leaks nothing) - see [DEPLOYMENT_CLOUDPANEL.md](DEPLOYMENT_CLOUDPANEL.md) section 10 |
| Flutter `test` / `analyze` | - | **NOT RUN**: no file under `societyflowmob/` changed; the app already shows the server's message for the new 429 (`ApiService._dioError` fallback), not exercised on a device |
| Real HTTP, 4 browsers x 8 rounds | scripted | PASS 0 sign-outs (re-run 23 Oct; the earlier failure was a stale scratch password) |
| Real HTTP, owner 19 on a scratch copy of the dev data | scripted | PASS 10/10 (below) |

New tests this round: `LoginLockoutTest` 13, `StoredXssOutputTest` 5, `UploadedReceiptNameTest` 4, `UnitLookupScopeTest` 3 (plus the 22 added on 22 Oct: `SubfolderInstallUrlsTest`, `OwnerUnitPickerTest`, `EmailCaseInsensitiveAuthTest`, `PasswordResetSocietyMailerTest`, `GuardWebLayoutTest`).
**Environment limits:** the XAMPP MariaDB on :3306 was **hung** (accepts a connection, never answers the handshake) and was left untouched - MariaDB results come from a temporary 10.4.32 instance on :3307 and say nothing about the :3306 service. MySQL 8 was not available. `ext-gd` / `ext-zip` are off in `php.ini`, so image-processing / Excel code paths are not exercised by tests. No phone, emulator or browser DOM run was done this round.

## 6. End-to-end coverage (this round)

| Surface | Coverage |
|---|---|
| Web | PHPUnit feature suites (all roles, isolation, notices, bills, bookings, complaints, visitors, guard); scripted real-HTTP sign-in / session checks; owner 19 edit page on a scratch copy: edit page opens, lookups point at the app root, area 7 / villa 160 present, area + number lookups return them, another society's id returns only the user's own, a blank-picker save keeps villa 160 |
| API | PHPUnit (`ApiContentVisibilityTest`, `AuthorizationIsolationTest`, OTP suites, lockout); real-HTTP OTP flow 20/20 |
| Flutter / phone | NOT TESTED this round (no Flutter change; on 23 Oct the debug build was installed and reached the API through `adb reverse`; the visible "cannot reach server" came from a dropped tunnel, fixed by `adb reverse tcp:8000 tcp:8000`) |
| Notifications | In-app: PASS (visitor for Villa 160 reached Villa 160's owner and the two admins only, none from another society). E-mail / SMS / WhatsApp: **blocked, not delivered** - fake transports in tests only |

## 7. Notification delivery states (accurate, not inferred from logs)

| Channel | State |
|---|---|
| In-app (bell / app list) | working; the app refreshes every 30 s on main screens and by pull-to-refresh |
| Push | **NOT IMPLEMENTED** (no FCM client in the app); do not describe notifications as instant |
| E-mail | code path tested with fake transports (reset mail now uses the society's SMTP settings); on the phone's API server it is **blocked** (messaging forced off); real delivery **UNVERIFIED** |
| SMS | no approved DLT template for visitor events; OTP / notice templates **unverified** with the provider |
| WhatsApp | no active SocietyFlow visitor template; BookMizo templates are refused by design - **BLOCKED** on the operator |

**Discrepancy to know about:** the request assumed `EXTERNAL_MESSAGING_ENABLED=false`. The dev `.env` has it **true** (with an allow-list), so the Apache dev site can send real mail to allow-listed addresses. It was not changed; every scratch / phone server in this round forced it off in the launching process.

## 8. Not done

No commit, no deployment, no real message, no change to `.env`, no password / role / status change, no restart or modification of the :3306 MariaDB, no `migrate:fresh`/wipe/rollback on the live database (the rollback was rehearsed on a copy), no change to `composer.lock`, no PHP switch.

## 9. Blockers to production (all still open)

1. PHP 8.2.35 not downloaded - and dependency advisories unchecked (offline).
2. Default-password accounts not rotated (operator decision).
3. Provider verification: Fast2SMS DLT templates, ZeptoMail sender, WhatsApp visitor template - no real delivery test.
4. No release commit / tag; production state unknown; MySQL 8 untested; push notifications absent.
5. Production `.env`: `APP_URL` https, `SESSION_SECURE_COOKIE=true`, `CORS_ALLOWED_ORIGINS`, `TRUSTED_PROXIES`, real queue worker + scheduler, `display_errors=Off` (today's dev `php.ini` has `On`), `gd` + `zip` enabled.
6. Laravel 10 / PHP 8.2 support horizons (framework upgrade planned).
7. Recommended web-server guard so an uploaded script file can never run: on CloudPanel (Nginx) a vhost rule `location ~* ^/storage/.*\.(php|phtml|phar|pht|phps)$ { return 403; }` above the PHP location (see [DEPLOYMENT_CLOUDPANEL.md](DEPLOYMENT_CLOUDPANEL.md) section 7); `.htaccess` only helps on Apache (the local XAMPP).
8. The CloudPanel deployment process is prepared but **not executed**: [DEPLOYMENT_CLOUDPANEL.md](DEPLOYMENT_CLOUDPANEL.md) (package script, server tools, dry run, rollback). The live server has not been inspected.
