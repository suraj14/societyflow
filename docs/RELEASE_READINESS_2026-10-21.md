# SocietyFlow release-readiness record (2026-10-21)

Status words: **PASS** (exercised, evidence), **FAIL**, **BLOCKED** (needs provider/credential/device/decision), **PARTIAL**, **NOT TESTED**.
Nothing here was deployed, committed, or sent to a real recipient. Fake transports prove application behaviour, not provider delivery.

**Verdict: NOT READY for production onboarding.** Release-critical items below are BLOCKED or NOT TESTED (providers, device, MySQL 8, production config).

## 1. Test evidence

| Run | Command | Result |
|---|---|---|
| SQLite, full | `scripts/run-test-shards.ps1 -Engine sqlite -Shards 8` | 2,472 tests, 14,633 assertions, 0 failures, 0 errors, 6 skipped (8/8 shards exit 0) |
| MariaDB 10.4, full (isolated `societyflow_test_0..7`) | same script, port 3306 copy | 2,472 tests, 14,632 assertions, 0 failures, 0 errors, 3 skipped (8/8 shards exit 0) |
| Flutter | `flutter test` / `flutter analyze` | 265 passed / 2 info (`DropdownButtonFormField value:` deprecation) - run earlier today, no Flutter code changed since |
| Android | `flutter build apk --release` | built (104.9 MB), **debug-signed**, `com.example.societyflow` - not installed on a device |
| Routes | `route:list`, `route:cache` | 558 routes, 0 closures, cache builds and clears |
| MySQL 8 | - | **NOT TESTED** (only MariaDB 10.4 available) |

## 2. Defect register (fixed this round, each with a regression test)

| # | Sev | Defect | Root cause | Fix | Test |
|---|---|---|---|---|---|
| D1 | High | A second society with the same name could not register; visitor saw `SQLSTATE... UNIQUE constraint failed` | slug/subdomain = `Str::slug(name)` with unique indexes; catch printed `$e->getMessage()` | `Society::uniqueSlug`, applied in the model `creating` hook and rename paths; generic message + log; retry of a lost race | `SocietyOnboardingTest` |
| D2 | Med | Registration email differing only by case accepted on case-sensitive engines | unique rule compares stored text | lower-case at registration | `SocietyOnboardingTest` |
| D3 | High | New society could not file a complaint on the web (no categories, category required) | defaults only came from a manual seeder | `ComplaintCategory::ensureDefaults` (lock, only when the society has none) on first use (web + API) | `SocietyOnboardingTest` |
| D4 | High | `email_logs.status` ENUM on MySQL/MariaDB rejected `blocked`/`simulated` (e-mail jobs failed) | SQLite got a rebuilt string column, MySQL kept ENUM | migration `2026_10_21_000001` (VARCHAR(20)); **applied to the dev SQLite only (no-op), not to any MySQL** | `DeploymentCacheAndLogSchemaTest` (both engines) |
| D5 | High | `php artisan route:cache` would fail (5 closure routes) | closures | controllers / `Route::view` / `RedirectController` | `DeploymentCacheAndLogSchemaTest` |
| D6 | High | Notice e-mail silenced forever by a pre-2026-10-17 "sent" row that only meant "logged" | dedupe counted any `sent` row | `EmailLog::deliveredFor` (honest-status cut-over) | `NoticeOwnerNotificationTest` |
| D7 | High | SMS/WhatsApp test seams bypassed the allow-list | seam called before the guard | allow-list checked first | `NoticeOwnerNotificationTest` |
| D8 | Med | Notice "Send Email/SMS" boxes ignored | listeners never read them | honoured; default on; edits send nothing; audited resend | `NoticeChannelChoiceAndResendTest` |
| D9 | High | Settings -> Features switches only hid menus; pages and API stayed reachable | no server enforcement | `EnsureModuleEnabled` (web + API, per society, Super Admin exempt, unset = on) | `ModuleSwitchEnforcementTest` |
| D10 | Med | "Login attempt limit" setting ignored (fixed 5) | not read | read per society (3-10), default 5 | `SettingsHaveEffectTest` |
| D11 | Med | "Enable e-mail notifications" master switch ignored | not read | gates society mail (account mail exempt) | `SettingsHaveEffectTest` |
| D12 | Low | Web status change wrote no history row (API did) | missing `addUpdate` | same row as API; reopen decision recorded | `ComplaintReopenTest` |

**Decision recorded - complaint reopen:** a resolved/closed complaint may be reopened by the society's Admin (web edit, API admin status); the model already clears `resolved_at`. Residents cannot change status; other societies get 404.

## 3. Open / not fixed

| Item | Sev | Why not fixed |
|---|---|---|
| Settings saved but never read: 2FA, force-HTTPS, session timeout, auto-approve residents, "society requires approval", currency decimals/default currency, enabled languages, payment-gateway toggles | Med | features that do not exist (no 2FA, no gateway); not hidden to avoid unapproved UI change. Needs a product decision: implement or remove from the screen |
| Document numbers can be reissued after deleting the newest record (P1-10) | Med | needs a counter table + migration |
| Web sessions are not revoked on a password change (`auth.session` middleware unused); Super Admin "reset admin password" screen does not revoke API tokens; web login has no per-user `status` check; OTP keyed by phone not account (shared-phone cross-reset) | High | see `docs/DEMO_ACCOUNTS.md` audit; changes to auth need approval |
| 32 default-password accounts + account 81 | High | separate approved remediation plan (command as documented would lock accounts out) |
| No push client (`firebase_messaging` absent) | Med | feature missing; mobile refresh = pull-to-refresh + 30 s auto-refresh while a screen is open |
| Production hardening P0-5..8 (HTTPS URL, secure cookies, queue worker, scheduler, signing key, application id) | Critical | operator decisions / production |

## 4. Provider blockers (nothing sent; nothing invented)

| Channel | Status | Evidence needed |
|---|---|---|
| Fast2SMS DLT 228126 (login OTP), 228199 (notice) | BLOCKED | registered text, header, variable count, approval status from the DLT panel (saved wording has 2 and 3 variables; unverified) |
| ZeptoMail SMTP + `info@bookmizo.com` | BLOCKED | an authorised test send; last login failure 2026-10-11 |
| WhatsApp | BLOCKED | no active SocietyFlow `notice` template; visitor-arrival purpose wrong; BookMizo mappings active (refused by the code) |

## 5. Settings effectiveness (society level; verified = a test flips it both ways)

| Setting | Effect proven |
|---|---|
| SMS `sms_enabled`, `notification_sms_enabled` | PASS (`ChannelSwitchEffectTest`) |
| WhatsApp `whatsapp_enabled`, template active | PASS (`ChannelSwitchEffectTest`) |
| E-mail master `enable_email_notifications` | PASS (`SettingsHaveEffectTest`) |
| Features: complaints, visitors, facilities, services, notices | PASS (`ModuleSwitchEnforcementTest`); events enforced by `EventController` |
| Features: bills, reports, parking, tenant/villa modules | NOT ENFORCED (menu/capability only) |
| Security: login attempt limit | PASS; others listed in section 3 |
| Credentials never echoed to pages or API | PASS (`ProviderSecretsNotExposedTest`) |

## 6. Web / API / mobile consistency (isolated e2e DB, mobile = the same REST API the app calls)

PASS for: facility booking request -> approve -> pay -> refund (web admin <-> owner API, amounts and statuses identical, report and admin API both ₹300), complaint create/update, visitor create/approve/check-in/out (idempotent `client_request_id`, 409/422 on bad transitions), notice publish -> in-app/API list. Cross-society: 190 URL probes + create/update/delete/ID-manipulation attempts, 0 leaks. **NOT TESTED:** the Flutter UI itself (no device/emulator, adb absent), browser DOM. Refresh contract: pull-to-refresh on 15 screens + 30 s auto-refresh timer on the main ones; no push/realtime.

## 7. Production checklist

See `docs/PRODUCTION_CHECKLIST.md` (migration order incl. `2026_10_21_000001`, HTTPS `APP_URL`, secure cookies, queue worker + supervisor, `schedule:run` cron, backups, `route:cache` now safe, keystore, application id, rotate default accounts first).
