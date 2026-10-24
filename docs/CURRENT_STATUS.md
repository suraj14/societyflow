# SocietyFlow — current status

Update this after every major task. Keep it short; link, don't paste.

**Last updated:** 2026-10-24 (Bitbucket pipelines and automatic-deployment tooling; both repositories on Bitbucket)

## 2026-10-24 - Bitbucket + automatic deployment (built, tested locally, NOT enabled, NOT run against the server)

Web `production-readiness` and mobile `main` are on Bitbucket (hashes verified). Full guide: [BITBUCKET_PIPELINES_SETUP.md](BITBUCKET_PIPELINES_SETUP.md); branches: [BITBUCKET_WORKFLOW.md](BITBUCKET_WORKFLOW.md); endpoint match: [API_COMPATIBILITY.md](API_COMPATIBILITY.md).
- **URGENT (TODO P0-21): the live login page still advertises demo credentials.** Seen from the public page source only; not tried. The live code is older than this branch. Also P0-20: a real `APP_KEY` was committed in `.env.mysql.template` (sanitised; history still holds it).
- **Pipelines:** every push runs tests + a package build; the production step is a manual button on `production-readiness`. Migrations are never run by the pipeline (a release with a pending migration stops with exit 3, changing nothing); a failed smoke check restores the previous code automatically; two deployments cannot overlap; record counts of 29 business tables are compared before and after.
- **Tested on a scratch copy of the app (not CloudPanel, not Bitbucket):** refusal on a pending migration (live tree byte-identical), a full automated deploy, automatic rollback after a failed smoke check (file restored bit-for-bit), live and stale locks, the record-loss detector, the package builders (identical 775-file lists), the mobile release guards. `DeploymentArtifactsTest` (9 tests, mutation-checked) pins the safety properties.
- **Live site facts (public GETs only, 24 Oct):** reachable behind Cloudflare; `/`, `/login` 200; `/owners` 302 to `/login`; `/api/v1/health` OK. Everything else about the server (PHP, database, queue, applied migrations, SSH) is UNVERIFIED.
- **Next (operator):** handle the live demo accounts; decide `TRUSTED_PROXIES`; create the Bitbucket SSH key / known host / `PROD_*` variables; run `inspect-server.sh`; do the supervised first deploy.

## 2026-10-23 - security hardening, OTP migration, PHP upgrade prep, CloudPanel deployment tooling (UNCOMMITTED)

Working tree on `production-readiness` (HEAD 3787ceb); nothing committed, deployed or sent; the live server was never contacted. Evidence and verdict: [RELEASE_READINESS_2026-10-23.md](RELEASE_READINESS_2026-10-23.md) (**NO-GO for production**). Items: [TODO.md](TODO.md) P1-132..P1-141.
- **Security fixed (with tests):** failed-password lockout on the app's login (it had none; now shared with the web login, 429 + `retry_after`); timing difference between unknown and known e-mails; stored XSS in the events calendar and the e-mail template pages; script-in-JSON hardening; rent receipt uploads no longer take the client's file extension; unit dropdown lookups no longer list other societies.
- **OTP migration `2026_10_22_000001` applied to the local dev SQLite database** after a verified backup (`C:\Users\USER\SocietyFlow-backups\database-pre-migrate-otp-account-20261023-180014.sqlite`) and an up/down/up rehearsal; account-bound reset OTP behaviour proven over real HTTP on a scratch DB (20/20).
- **PHP 8.2.35: NOT upgraded** - `G:\xampp\php-8.2.35` is empty. Pre-flight script, runbook and config backups are ready ([PHP_UPGRADE_RUNBOOK.md](PHP_UPGRADE_RUNBOOK.md)). Apache serves PHP 8.2.12.
- **Tests (final code):** SQLite 2,559 tests / 15,151 assertions / 0 failures / 0 errors / 6 skipped, 8/8 exit 0. MariaDB 10.4.32 (temporary instance :3307; the XAMPP MariaDB on :3306 is hung and was left untouched): 2,559 / 15,150 / 0 / 0 / 3 skipped, 8/8 exit 0.
- **Deployment tooling (CloudPanel):** `scripts/deploy/` + [DEPLOYMENT_CLOUDPANEL.md](DEPLOYMENT_CLOUDPANEL.md): validated local package builder, read-only server inspection, verified DB backup, dry-run-first apply, code rollback. Tested on a scratch copy including a mid-way failure and a rollback; **not run against CloudPanel**.
- **Next:** answer the questions in DEPLOYMENT_CLOUDPANEL.md section 11; download PHP 8.2.35 (or skip); decide on a release commit / tag; run `inspect-server.sh` on the server.

## 2026-10-22 - owner villa pickers, sign-in after a password change, reset mail, guard web layout (UNCOMMITTED)

Working tree on `production-readiness`; nothing committed, deployed or sent. Full suite on the final code: **SQLite 2537 tests / 14987 assertions / 0 failures / 0 errors / 6 skipped, 8 of 8 shards exit 0; MariaDB 10.4.32 (throwaway, :3307) 2537 / 14986 / 0 / 0 / 3 skipped, 8 of 8 exit 0.** Flutter code unchanged this round (not re-run). The XAMPP MariaDB on :3306 stopped answering during the run (accepts connections, no handshake) - left untouched; the runner's default port 3307 is the throwaway. Items: [TODO.md](TODO.md) P1-124..P1-131, P2-64..P2-67.
- Owner form's Villa Area / Villa No. pickers were empty on the Apache sub-folder install (root-relative URLs in 15 views) and saving the form wiped owner 19's villa. Fixed; owner 19 restored from the 21 Oct backup (villa area 7, villa 160). Unit lookups (owner form, Payments, Utility Bills) list only the user's own society.
- Sign-in, forgot / reset password and OTP reset match the e-mail whatever its letter case (an account stored in capitals could not sign in even after an admin set its password).
- A signed-out user's reset link goes through the account's society mail settings (it used the bare `.env` mailer, which Gmail refused); the kill switch checks the mailer actually used. **Real delivery not tested by the agent.**
- Guard pages: web layout on laptops (sidebar, top bar, three-column lists), phone layout unchanged.
- Test harness: switching users inside a test starts a fresh session - the cause of the 134 failures behind the earlier NO-GO. Real HTTP: 4 browsers x 8 rounds, 0 sign-outs.
- **Dev site blocker:** migration `2026_10_22_000001` (nullable `mobile_otps.user_id` + index, no data change) is not applied to the dev DB, so OTP sign-in / reset fail there (web and phone) until it is. Needs a backup and the owner's OK.

## 2026-10-21 - messaging made diagnosable and safe; public pages no longer publish logins (UNCOMMITTED)

Working tree on `production-readiness`; nothing committed, deployed or sent. Full Laravel suite: **2402 tests / 14266 assertions / 0 failures / 0 errors / 6 skipped, 8 of 8 shards exit 0**. Flutter 265 pass (analysis = the 2 known infos), debug APK builds.
- `php artisan messaging:status` (read-only, no secrets) explains why delivery is ON/OFF and where that comes from, the allow-list state, and each society's e-mail / SMS / WhatsApp configuration and mappings. See docs/LOCAL_MESSAGING_TESTING.md 2d.
- Allow-list fails closed and matches exactly, on every route (SMTP, Fast2SMS, WhatsApp, BulkSMSPlans, custom SMS API). Statuses: Blocked (switch off / not allow-listed), Simulated, Sent (accepted, not delivered), Failed.
- An SMS template carrying another notification's wording (society 6 "document" = bill text) is refused at save and at send.
- Login page and landing page no longer list demo logins; **the live site may still accept those accounts - rotate them** (`php artisan users:rotate-weak-passwords`, owner decision).
- Society 6 WhatsApp: every active mapping is a BookMizo template (blocked by design); the SocietyFlow templates are saved but switched off - an owner decision in Message Templates.
- Real delivery has NOT been tested: needs the owner's test e-mail + phone, then an explicit confirmation of recipient / channel / template / preview.
## 2026-10-16 - finance unification, rent-unit fix, festival module (UNCOMMITTED, full suite pending at time of writing)

Working tree on `production-readiness`; nothing committed or deployed. **NOT YET RELEASED.** Details: [FINANCE_AND_FESTIVALS.md](FINANCE_AND_FESTIVALS.md).
- Finance: one calculation layer (`FinancialReportService` + `Money`) now feeds the Financial Report/PDF, Maintenance report, Payments page, Admin + Accountant dashboards, mobile admin collections and Super Admin `monthly_revenue`. Facility income only from a recorded payment; payment/cancel race closed (guarded UPDATE in a transaction). `finance:reconcile` is read-only.
- Rent: bill 45 (Sept 2026, Rs 10,000) is on flat 123 = **Villa 160**, which has no active tenant; the tenant lives on flat 131 = **Villa 159**. The edit page used to omit a unit with no active tenant, so saving re-pointed the rent. Fixed + 9 tests. **The record itself is NOT changed** - it needs your authorised correction (see the doc).
- Festival & Celebrations module added (migration `2026_10_16_000001`, not applied to the dev DB).
- Migrations `2026_10_15_000003` and `2026_10_16_000001` are NOT applied to the dev DB; back up first.
## 2026-10-16 (later) - migrations applied to the DEV DB; messaging allow-list

- Applied after a verified backup (C:\Users\USER\SocietyFlow-backups\database-pre-migrate-facility-festival-20261016-143412.sqlite, sha256 b438b573...f47a): 2026_10_15_000003 then 2026_10_16_000001. Row counts unchanged except the 6 new empty festival tables; payments, bills, bill 45, expenses and facility bookings identical; integrity ok, 0 FK violations. The 4 unpaid bookings (Rs 6,000) remain a receivable; no payment was created.
- New: EXTERNAL_MESSAGING_ALLOWLIST (see docs/LOCAL_MESSAGING_TESTING.md 2a). Delivery is still OFF (.env unchanged).
## 2026-10-16 (messaging) - template "send test" no longer logs a send that the kill switch suppressed.
## 2026-10-15 - Gate visitor approval (MyGate-style) + event notifications

Committed on `production-readiness`. **READY FOR STAGING, NOT READY FOR PRODUCTION; REAL DELIVERY NOT TESTED.** Details: [SOCIETYFLOW_STABILIZATION.md](SOCIETYFLOW_STABILIZATION.md) Round 8; behaviour and event x channel table: [VISITOR_APPROVAL.md](VISITOR_APPROVAL.md).
- Built and tested: guard logs a visitor -> owner / tenant + admin told (bell, e-mail, WhatsApp when set up) -> allow / deny (atomic, 409 names the winner) -> guard checks in / out -> admin override with a reason; no-answer escalation never auto-admits; per-event channel outcomes in the visitor's history. Guard / Staff can no longer decide; a denied visitor cannot be checked in.
- The dev database was migrated (additive; backup kept). The new debug APK builds; the phone was not connected, so it is not installed.
- Operator: switch on the two visitor WhatsApp templates, verify, one approved real test; SMS has no approved visitor template.
## 2026-10-14 (latest) - WhatsApp success read as failure; the two excluded templates

Committed on `production-readiness`. **READY FOR STAGING, NOT READY FOR PRODUCTION; DELIVERY NOT CONFIRMED BY THE APP.** Details: [SOCIETYFLOW_STABILIZATION.md](SOCIETYFLOW_STABILIZATION.md) Round 7.
- Fixed with tests: Fast2SMS's real success answer (`return: true`, message as a list) was reported as "Test WhatsApp failed" and every notification send as failed; "Switch off" could be bypassed by an older setting; the settings alert now names the two excluded templates (BookMizo's, on Visitor approved and Gate pass updates) and the approved replacement.
- Operator: Make active "Society Visitor Approved"; switch off `bookmizo_staff_booking_reschedule` (Other templates). Phone Number ID already corrected.
## 2026-10-14 (later) - WhatsApp 404 / Phone Number ID

Committed on `production-readiness`. **READY FOR STAGING, NOT READY FOR PRODUCTION; REAL DELIVERY NOT TESTED.** Details: [SOCIETYFLOW_STABILIZATION.md](SOCIETYFLOW_STABILIZATION.md) Round 6.
- Cause: the key's account has two WhatsApp numbers (`1209719742234591` Bookmizo, `941982805667233` Sainiweb Solutions); society 6 saved `1209719722434591` (transposed digits) -> HTTP 404. Earlier it held the WABA ID -> `(#100)`.
- Fixed with tests: ownership check against the documented `type=number` list, templates send from their own number, every HTTP outcome handled, duplicate-test guard, settings validation, truthful status. SQLite 2188 tests, 8/8 exit 0.
- Operator: set society 6's Phone Number ID (decision needed), clear the legacy BookMizo settings, then one approved real test.
## 2026-10-14 - WhatsApp templates: listing, BookMizo exclusion, exact parameters, manual sync

Committed on `production-readiness`. **READY FOR STAGING, NOT READY FOR PRODUCTION.** Details: [SOCIETYFLOW_STABILIZATION.md](SOCIETYFLOW_STABILIZATION.md) Round 5.
- Fixed with tests: all SocietyFlow templates listed in five categories whatever their provider status; BookMizo ones kept out (not by prefix) but never deleted; pending / rejected / BookMizo templates never sent or switched on; **templates saved in the UI now actually trigger the real notifications**; each `{{n}}` is filled from what the approved wording asks for, using real event data; manual "Sync from Fast2SMS" (no webhook exists for template status, so no background sync).
- Evidence: SQLite 2141 tests / 12071 assertions / 0 failures / 6 skipped, 8 of 8 shard exit 0. MariaDB not re-run. No provider call was made.
- Found: society 6's old settings point resident notifications at BookMizo templates (blocked by the sender, data untouched); rows 20-35 of `whatsapp_templates` came from a sync not run in this session.
## 2026-10-13 - mobile UX, manual templates, OTP / e-mail / SMS / WhatsApp production readiness

Committed on `production-readiness`. **READY FOR STAGING, NOT READY FOR PRODUCTION.** Details: [SOCIETYFLOW_STABILIZATION.md](SOCIETYFLOW_STABILIZATION.md) Rounds 3-4; procedure for real delivery and deployment: [MESSAGING_STAGING_AND_DEPLOYMENT.md](MESSAGING_STAGING_AND_DEPLOYMENT.md).
- Fixed and tested: every Quick overview card opens its own list; Services is a direct Call / WhatsApp directory; Service Requests UI removed; admin guard duty board; manual WhatsApp templates (no sync); one-time codes hashed / one-use / cooldown / no enumeration / no code outside a dev server; temporary passwords no longer stored in the e-mail log; reset-link failure handling; SMS `env()` / master-switch defects; **MariaDB-only defect where a one-time code never expired once guessed against** (found by the MariaDB run).
- Evidence: SQLite 2112 tests / 11864 assertions / 0 failures / 6 skipped, 8 of 8 shard exit codes 0; MariaDB 10.4 2112 / 11863 / 0 / 3 skipped, 6 of 6 exit 0; Flutter 252 pass (exit 0), analyze = the 2 known infos; release APK builds (debug-signed); Chrome 124/124 + 97/97 + 44/44 at 390/360/320/1366.
- NOT done: any real SMS / WhatsApp / e-mail / OTP delivery (none was sent; receipt unconfirmed for every channel); the post-hardening OTP journey on a physical phone (unplugged); production deploy (not attempted).
- Operator: back up and migrate (`2026_10_13_000001..2026_10_14_000004`), rotate credentials, set `APP_URL`/`APP_DEBUG`/`LOG_LEVEL`, create the failed-jobs table, then follow part A of the staging document.
## 2026-10-12 (round 2) - production-readiness gaps closed where feasible

Committed on `production-readiness` (after 356c9d5). **READY FOR STAGING, NOT READY FOR PRODUCTION.** Detail: [SOCIETYFLOW_STABILIZATION.md](SOCIETYFLOW_STABILIZATION.md) (Round 2).
- Fixed: Accountant could verify/reject payments and a resident could rewrite or delete their own verified payment (`FinancePermissionMatrixTest`); guard-shift change notifications and the missing "office removed your booking" notice; message-template form JS error after a rejected save (found by the browser run); mobile tenant unit now follows the active tenancy like the web.
- Evidence: PHP SQLite 8 shards 2028 tests / 11266 assertions / 0 failures / 5 skipped, **all shard exit codes 0**; MariaDB 10.4 throwaway 6 shards 2028 / 11264 / 0 / 3 skipped, **all exit codes 0**; Flutter 217 tests exit 0, analyze = the 2 known infos (exit 1); browser 124/124 at 390/360/320/1366; migrations verified with data on SQLite and MariaDB, rollback refusal confirmed.
- NOT TESTED: Android device/emulator (none available), live SMS/WhatsApp/email delivery, Tenant dashboard in a browser. Credentials NOT rotated (checklist in CREDENTIAL_ROTATION.md).
- DB processes: PID 11764 is the user's XAMPP MariaDB (:3306, `G:/xampp/mysql/data`) - leave it; PID 22728 is the assistant's throwaway (:3307, scratchpad datadir).
## 2026-10-12 - stabilization, bug fix and web/mobile parity run

Committed on `production-readiness` (last commit: see `git log`). **READY FOR STAGING, NOT READY FOR PRODUCTION.** Details, root causes and per-defect tests: [SOCIETYFLOW_STABILIZATION.md](SOCIETYFLOW_STABILIZATION.md).
- Fixed: shift-assignment 422 (field-aware errors, conflict/past-date/unavailable-guard rules), tenant Quick Actions, rent vs maintenance bill index collision (cause of the tenant Rs 0), **residents marking their own bill paid** (now a pending claim), payer notification on verification, mobile bill type, one password policy (server + web + Flutter).
- Results after the last code change: **SQLite** 8 shards 1997 tests / 11058 assertions / 0 failures / 0 errors / 5 skipped; **MariaDB 10.4** (throwaway :3307, fresh DBs) 6 shards 1997 / 11056 / 0 / 0 / 3 skipped; **Flutter** 212 tests pass, `flutter analyze` only the 2 known info deprecations. Shard exit codes are not captured by the runner; figures are phpunit summaries.
- NOT TESTED: Android device/emulator, live Fast2SMS/WhatsApp/SMTP delivery, browser pass of the new password/SMS/template/roster screens, Society Expenses in depth. Missing: shift-change and booking-cancellation notifications.
- Operator: migrate (2 new migrations), rotate the pasted Fast2SMS key, Accountant cannot record payments (decision), SMS needs the master switch.
## 2026-10-12 - master implementation run (P1-99..101, P2-39, P2-40)

Uncommitted on `production-readiness`. **READY FOR STAGING, NOT READY FOR PRODUCTION.** No real SMS/OTP/email/WhatsApp was sent and no live provider endpoint was called (the Fast2SMS and Flutter tests use recording stubs / a fake HTTP layer; the two docs pages were read, which is not a provider call). EXTERNAL_MESSAGING_ENABLED stayed false; `.env`, credentials and databases untouched.
- Final results after the last code change: **SQLite** 8 shards 1673 tests / 7569 assertions / 0 failures / 0 errors / 5 skipped; **MariaDB 10.4** (throwaway :3307, scratchpad datadir, fresh databases) 6 shards 1673 tests / 7567 assertions / 0 failures / 0 errors / 3 skipped; **Flutter** `flutter test` exit 0, 187 tests; `flutter analyze` exit 1 only for the 2 known info-level deprecations (0 errors/warnings). PHP lint 34 files clean; `git diff --check` clean. Shard process exit codes are not captured by the shard runner; the figures are from each shard's phpunit summary.
- Chrome (isolated copy): sidebar 60/60, Lucide-blocked 5/5, phone drawer 30/30 (320/360/390 x Admin/Owner/Tenant/Staff/Accountant). Earlier this run: the Flutter booking 403 cause, the capabilities matrix and Events enforcement were verified by tests (see TODO).
- NOT TESTED: the Flutter screens on a real device or emulator (widget tests only), live Fast2SMS/SMTP/WhatsApp delivery, Android build for release. Whether the screenshot's booking account was an Admin was not verified.
- Credentials NOT ROTATED (see P2-40). Event SMS stays disabled until its own approved DLT template and setting exist.
## 2026-10-12 - compact grouped sidebar, follow-up verification (P2-38, P3-13, P2-39)

Uncommitted on `production-readiness`. READY FOR STAGING, NOT READY FOR PRODUCTION. Desktop sidebar is grouped and compact (`partials/sidebar-nav.blade.php`); the mobile drawer is unchanged.
- Staff -> Book Facilities resolved: the seeder gives Staff no booking permission and the controller 403s, so the link is hidden (mirrors the controller gate). The routes still 403 for Staff (tested for GET index, GET create, POST). Mutation check (gate forced open) fails 3 tests.
- Events: link hidden when the module is off; direct `/events` is 403 when off and 200 when on for Admin, Owner, Tenant, Staff and Accountant (tested). Only Events is backend-enforced among the Settings -> Features toggles.
- Multi-role users: tested that the deduplicated sidebar equals the union of each role's own links for Admin+Accountant, Owner+Tenant, Staff+Accountant, Apartment Owner+Villa Owner.
- Desktop vs mobile differences measured per role and flag (TODO P2-39); left unchanged pending a product decision.
- Results (final, after the last code change): SQLite full suite 8 shards: 1642 tests, 7293 assertions, 0 failures, 0 errors, 5 skipped (1 is the baseline-capture helper, 4 engine-specific); MariaDB (throwaway :3307, scratchpad data dir) navigation/authorization/booking/staff subset: 227 tests, 995 assertions, 0 failures, 1 skipped (the helper). Full MariaDB suite NOT run. Lint 21 PHP files clean; `git diff --check` clean.
- Browser (isolated copy, messaging off): 60/60 sidebar checks (256px width, 40/38px rows, active group opens, Enter/Space, focus ring, 520px-tall scroll with footer, 320/360/390px drawer); 5/5 with the Lucide CDN blocked (icons disappear, labels/layout/navigation unaffected - the same single-CDN dependency the app already has for Tailwind/Font Awesome; no new dependency added).

## 2026-10-11 (latest) - SMS result status contract (P1-98)

Uncommitted on `production-readiness` (last commit 20f568b; P1-97 changes are also still uncommitted). **READY FOR STAGING, NOT READY FOR PRODUCTION.**
- `SmsService` results now carry `status` + `sent`; `success`/`message` unchanged for old callers. A skip is never "sent"; the kill switch is `skipped_disabled`; a missing key or notification template is `skipped_not_configured` and sends nothing (no OTP-template fallback). OTP login, the Test SMS button and the Delivery Log/Logs screen now say "Not sent / Skipped".
- Results: lint 17 files clean; SMS result tests 11 OK; SQLite full 1631 tests / 7099 assertions / 0 failures / 4 skipped; MariaDB (throwaway :3307) SMS/settings/auth/log subset 100 tests / 0 failures. Full MariaDB suite not re-run.
- **Disclosure:** one test run made a real HTTPS call to Fast2SMS with a fake literal key (rejected at authentication, nothing sent, no real credential). Fixed with a transport guard in the test class. No other outbound call was made; EXTERNAL_MESSAGING_ENABLED stayed false; .env, credentials and databases were not touched.
- Remaining: WhatsApp still returns the legacy `dev_mode` (P2-36), credentials not rotated, live delivery and the `/dev/wallet` read-only/non-billable question are unverified, Android not tested.

## 2026-10-11 (later) - email/SMS settings audit and final verification (P1-97)

Uncommitted on `production-readiness` (last commit 20f568b). **READY FOR STAGING, NOT READY FOR PRODUCTION.** No real SMS/OTP/email/WhatsApp was sent and no provider endpoint was called; EXTERNAL_MESSAGING_ENABLED stayed false (suite config) and the browser server ran on an isolated credential-stripped copy.
- Fixes: notification SMS never reuses the OTP template (fails, sends nothing); email Save keeps the real password and stores "no encryption" as empty; Settings now shows validation errors; SMS page has a 4-step progress strip and a messaging-disabled banner.
- Results: lint 10 files clean; focused 21 tests OK; SQLite full 1620 tests / 7037 assertions / 0 failures / 4 skipped; MariaDB (throwaway :3307) SMS+settings subset 106 tests / 0 failures; Chrome SMS page 40/40 at 390, 360, 320, 1366px.
- Unverified: whether POST /dev/wallet is read-only/non-billable (no provider documentation available here). Not tested: live Connect & Verify, test SMS/email, delivery. Credentials still not rotated.
## 2026-10-11 — web audit completion and release sign-off (see [WEB_AUDIT_TRACKER.md](WEB_AUDIT_TRACKER.md))

Branch `production-readiness`. **Not pushed, not deployed.** Release decision: **NOT READY for production; READY FOR STAGING.**

- **D-31 mutation check: PASS.** Removing the status filter, and separately the show() guard, each made `NoticeDraftVisibilityTest` fail; both restored byte-identical. The same leak was then found on `/notices-pending`, `/notices-history` and the villa-owner portal (D-33) and fixed.
- **Modules:** A PASS (mail delivery NOT TESTED) · I PASS (Guard screens driven in Chrome at 390px; valid QR scan NOT TESTED) · L PASS (D-34 dues from the wrong table, D-35 Staff events on the UTC date) · N PASS at 320/360/390px (D-37 dialog overflow; D-38 missing reset/forgot confirmations) · booking advance window: web and API share `facilities.advance_booking_days` (default 30); the reported "defect" was a script artifact; D-36 (web used the server's date) fixed.
- **Tests (final, after the last code change):** SQLite 8 shards **1612 tests / 6990 assertions, 0 failures, 4 skipped**; MariaDB 10.4 (throwaway :3307, fresh databases) 6 shards **1612 / 6988, 0 failures, 2 skipped** (engine-specific skips). The MariaDB run caught the Staff-events test failing in the last hour of the UTC day (real bug D-35). Flutter: 162 tests pass, `flutter analyze` 2 known info-level deprecations. 365 changed PHP files lint clean.
- **Browser (real Chrome, isolated copy, messaging off):** `flows.js` 28/28 · `guard.js` 31/31 · `phone.js` 77 pages × 3 widths clean · `phoneflow.js` 29/30 (30th confirmed separately) · `auth.js` 6/6.
- **Credentials: NOT ROTATED.** `credentials:audit` fingerprints are unchanged from the 2026-10-10 baseline (exit 1). Operator task: [CREDENTIAL_ROTATION.md](CREDENTIAL_ROTATION.md).
- **NOT TESTED:** real email/SMS/WhatsApp/payment delivery, Android device, valid QR scan in a browser. Event SMS stays disabled until its own approved DLT template + setting exist.
- **Next task:** operator rotates credentials (P1-95), then staging delivery verification with approved recipients (P1-96).

## 2026-10-10 — audit, fixes and verification

Branch `production-readiness`, uncommitted. **Not deployed.** Release decision: **NOT READY for production; READY FOR STAGING** with the gaps below.

- **Done and verified (tests, both engines):** P1-76 N+1, P1-77 villa/apartment assignment (a 500 and a cross-society unassign hole fixed), P1-17/18/19 and P0-4 schema/money parity, mandatory profile-image editing for every role (web + `POST /api/v1/profile`), "Society Info" (Society Details / Committee / Documents) on web and Flutter, User Management drawer replaced by the standard edit page, Super Admin surface and role-permission editing locked down (`SuperAdminSurfaceTest`, `RolePermissionUpdateTest`).
- **Bugs found and fixed:** dashboards/tenant pages fabricating random flats/tenants/residents on read; cross-society name linking of tenants; `owner_id IS NULL` leak (unlinked "Owner" accounts owned every unassigned unit; `Flat::ownedBy()`); legacy `/my/*` villa portal; Staff dashboard 500 on MariaDB (`events.event_date` / `status=published` do not exist).
- **New migrations (4, unapplied on the dev DB):** `2026_10_09_000001`, `2026_10_10_000001`, `_000002`, `_000003`. Back up `database/database.sqlite`, then `php artisan migrate --force`.
- **Test evidence (superseded by "Final gate" below):** earlier run: **SQLite 1351 tests / 5679 assertions, 0 failures, 4 skipped; MariaDB 10.4 (throwaway, port 3307) 1351 tests / 5677 assertions, 0 failures, 2 skipped** (skips are engine-specific by design). The first MariaDB run failed once (Staff dashboard 500) — that was the real bug fixed above, not a test change. Flutter: 162 tests pass; `flutter analyze` 2 info-level deprecations (known, intentional).
- **NOT TESTED:** real device / APK run, browser automation, real email/WhatsApp/payment delivery, MariaDB on the original XAMPP data dir (it crashed on my start attempt; not touched since — a throwaway instance on port 3307 was used).
- **Remaining risks:** see P1-82..84 in [TODO.md](TODO.md).

### Staging hardening (same day, later)

- **Migrations applied to the local dev DB** (`database/database.sqlite`, SQLite, not production): verified target, verified backup (`C:\Users\USER\SocietyFlow-backups\database-pre-migrate-*.sqlite`, integrity ok), rehearsed migrate / rollback / re-migrate on a copy first. After: 0 pending, every table's row count unchanged, `tenants/flats/residents/owners/payments/maintenance_bills/visitors/notices` byte-identical, only `societies` (2 new columns) and 9 `users.avatar` values (mirrored from their Owner photo) changed. Rehearsal found two broken `down()` methods (SQLite, MariaDB) — fixed.
- **Logout:** was already POST + CSRF; GET is a redirect stub. Pinned by `LogoutTest`.
- **Piyus / Suraj:** root causes found and fixed in code; **the two data changes were not made.** Plan with exact rows and rollback SQL: [DATA_REPAIR_PLAN_2026-10-10.md](DATA_REPAIR_PLAN_2026-10-10.md).
- **Add User** is now the full page; **browser run** (real Chrome, isolated server on a copy DB with mail set to `log`): 16/16 checks.
- **Android:** `flutter build apk --debug` compiles. **No device or emulator exists on this machine: device testing NOT TESTED.**
- **External delivery NOT TESTED:** SMTP is configured but nothing was sent; no WhatsApp or payment keys exist.
- Throwaway MariaDB (port 3307) is separate from the XAMPP data directory, which was not touched.

## P0-10/P1-15: MariaDB user-delete cascade fix + 3 more bugs found testing it — 2026-10-09

Picked up P0-10 and P1-15 from `docs/SCHEMA_PARITY.md` (open since 2026-09-15):
deleting a user on MariaDB was `CASCADE`-deleting the resident and visitor
rows that pointed at them — a security/audit record disappearing because
an unrelated account was removed, never reproducible on the SQLite dev DB
because that engine already had the correct `SET NULL`. Fixed, then found
3 more real, independent bugs as a direct byproduct of regression-testing
the fix — each demonstrated before being fixed, none inferred.

- **The fix** (`database/migrations/2026_10_09_000001_align_user_delete_cascades_on_mysql.php`,
  MySQL/MariaDB only — SQLite was already correct, confirmed by reading
  every relevant migration rather than assumed): `residents.user_id` and
  `visitors.host_user_id` now `SET NULL` on delete, matching SQLite.
  `visitors.host_user_id` also had to become nullable first (MySQL refuses
  `ON DELETE SET NULL` on a `NOT NULL` column) — the second half of this,
  tracked separately as P1-15. **Demonstrated on real MariaDB 10.4.27**, not
  just asserted against the schema: deleting a user now leaves the
  resident/visitor row in place with the link cleared, where it previously
  deleted both rows outright. `down()` is deliberately a no-op (reversing
  would restore the exact data-loss bug this exists to close).
- **Found while regression-testing the fix, each independently real:**
  1. **`SendBillReminders` (the `bills:send-reminders` scheduled command)**
     queried `UtilityBill::where('payment_status', 'pending')` — that
     column does not exist on `utility_bills` (it belongs to a different
     table, `facility_bookings`). Threw outright on MySQL; on SQLite the
     unrecognised identifier was silently treated as a string literal
     rather than raising (CLAUDE.md §8's own warning, caught in the wild),
     so the query always matched zero rows. No utility bill reminder has
     ever sent, on either engine, since this command shipped. Also fixed
     `$bill->amount` (no such attribute) → `$bill->bill_amount`.
  2. **`User::ownedVilla()`/`ownedFlat()`/`ownedProperties()`** — the
     bigger one. Each branched on `if ($this->owner_id)`, returning the
     real relation only when true and an always-dead relation otherwise.
     That branch reads instance state that plainly does not exist whenever
     Eloquent builds the relation without a loaded row first —
     `whereHas('ownedVilla')`, `whereDoesntHave('ownedVilla')`, and
     `User::whereHas('ownedProperties', ...)` all call this method against
     a throwaway, unhydrated `User`, where `$this->owner_id` is always
     null. Every such call silently took the dead branch, unconditionally,
     forever — `$user->ownedVilla` on an already-loaded instance was never
     affected, which is exactly why dozens of existing call sites kept
     working while this went unnoticed. Affected 5 real call sites:
     `EmailNotificationService::getRecipients()`, `EmailTriggerService`,
     and 3 event listeners (`SendBillGeneratedEmail`,
     `SendPaymentOverdueEmail`, `SendTenantOwnerAddedEmail`) — an owner has
     never once been resolved as a notification recipient through this
     path — plus `Admin\UserVillaAssignmentController`'s villa-assignment
     screen, which was showing every owner as unassigned and nobody as
     assigned. Fixed to a single unconditional relation; fixing it exposed
     a second-order **ambiguous-column bug** at all 5 call sites
     (`where('id', ...)` inside the `whereHas` closure is ambiguous once
     joined through `owners` — fixed to `where('flats.id', ...)`).
  3. **3 `AuthorizationIsolationTest` facility-booking tests** created
     their resident test users with no role at all, so
     `FacilityApiController::canBook()` always returned false regardless
     of the scenario under test — a test bug, not an app bug (confirmed:
     the fix is 6 one-line `assignRole()` additions, and `git diff`
     confirms zero production files were touched for it).
- **Found, documented, not fixed — separate scope, a performance issue not
  a correctness bug:** `QueryPerformanceTest::test_facility_booking_list_does_not_scale_queries_with_rows`
  — `GET /api/v1/facility-bookings` scales 9→44 queries as booking rows
  grow (a real N+1 in `FacilityApiController::bookings()`). Confirmed
  pre-existing and unrelated: reproduces identically before and after every
  change above, on both engines. Tracked as P1-76.
- **Also found, not yet covered by its own test:** `Admin\UserVillaAssignmentController`'s
  villa-assignment screen has no HTTP-level feature test at all (confirmed
  by grep). Its queries are now correct (fix #2 above), but the
  controller/view itself remains unverified end to end. Tracked as P1-77.
- **Verification, both engines, full evidence:**
  - Targeted run (`--filter "SchemaParityTest|BillReminderCommandTest|UserOwnedPropertiesRelationTest|AuthorizationIsolationTest"`):
    **43/43 passing, 106 assertions** — identical on SQLite and real
    MariaDB 10.4.27 (`DB_CONNECTION=mysql DB_DATABASE=societyflow_test`,
    the project's own documented throwaway MySQL test DB — the real dev
    database `database/database.sqlite` was never touched, confirmed by
    its unchanged modification timestamp).
  - Full backend regression, both engines: **SQLite 1181 tests / 4459
    assertions / 1 failure; MariaDB 1181 tests / 4455 assertions / 1
    failure** — the one failure on each engine is the pre-existing P1-76
    N+1 above, nothing new.
  - No real notification was sent anywhere in this verification: the bill-
    reminder test uses `Mail::fake()`, and the code path it exercises has
    no SMS/WhatsApp/HTTP call site at all (confirmed by grep) — only
    `Mail::send()`.
  - Every changed file reviewed with `git diff` against the last commit:
    each edit is isolated to its stated fix (`User.php`'s 3-method rewrite;
    5 one-line `where('id', ...)` → `where('flats.id', ...)` fixes in
    `EmailNotificationService`/`EmailTriggerService`/3 listeners; the 2
    column-name fixes in `SendBillReminders`; 6 one-line `assignRole()`
    additions in the test file; `SchemaParityTest` is a pure addition, 61
    lines, 0 deletions). `app/Http/Controllers/Api/FacilityApiController.php`
    shows as modified in `git status` but confirmed untouched by this
    session — that diff belongs to earlier, unrelated uncommitted work.
- **Not attempted this round:** `P1-17` (`tenants.user_id`/`flat_id`
  cascade-rule mismatch, the next item in `docs/SCHEMA_PARITY.md`), `P1-18`
  (missing unique index on `flats(building_id, flat_number)` on MariaDB),
  `P1-19` (two NOT-NULL-without-default columns on MariaDB only) — each is
  its own scoped fix with its own evidence requirement, not a continuation
  of this one.

## Society Rules & Documents + notification pipeline fixes — 2026-10-08

Resumed from the Guard Shift state (nothing rebuilt). Built one new module
end to end and, by testing it in a real browser instead of trusting faked
tests, found that **no queued notification email had ever been delivered**.

**Fixed (each with a regression test that fails without the fix):**

- **P0-15 — every queued email was discarded.** `config/queue.php` never
  existed, so `queue.default` was null → `NullQueue`. Every
  `DispatchEventNotification` (welcome, payment, notice, ticket, visitor,
  bill, access pass, facility booking) vanished, whatever `QUEUE_CONNECTION`
  said. Earlier "notifications PASS" claims rested on `Bus::fake()` tests that
  only checked a job was dispatched. Added the standard config.
  `QueueConfigurationTest` (3, no fakes).
- **P0-16** — `access_pass_created`, `access_pass_revoked`,
  `facility_booking_status_updated` were missing from `EmailEvent`, so
  `DynamicEmailService` rejected them even with the queue fixed.
  `EmailEventWhitelistTest` runs the real send for every registered
  trigger_event email (26).
- **P0-17** — global `PreventBackHistory`/`PreventAllCaching` and web
  `SetCacheHeaders` (+ `RefreshCsrfToken`) called `->header()`, which file
  and streamed responses lack: every file download route 500'd on web and
  API. `CacheHeadersFileResponseTest` (6).
- **P1-75** — 11 views duplicated the layout's flash (every save message
  shown twice). `FlashMessageDuplicationTest` scans all views.

**Society Rules & Documents (P1-73)** — Settings → Rules & Documents (and a
sidebar entry). Draft → publish → new version (old one archived, never
overwritten) / unpublish / archive; only a never-published draft can be
deleted. PDFs on the private disk, served only through authorising routes;
byte-level `%PDF-`/`%%EOF` check on top of mime rules; 10 MB cap; stored
name is a UUID. Residents: `/society-rules` (PDF.js reader, zoom, search
list). Guards: `/guard/rules` + header button, guard-visible docs only.
Staff/Accountant: 403. Optional "I have read this" with admin list
(`n of m residents`). Optional announce via Email/SMS/WhatsApp, chosen per
publish, sent at most once per version (`announced_at`); WhatsApp uses a new
`document` purpose template in Settings → WhatsApp. API
`/api/v1/society-documents` (+ `/file`, `/acknowledge`). Flutter: Profile →
Society Rules & Documents, guard home → Gate Rules, native viewer (`pdfx`).

**Verified:** `SocietyDocumentTest` 28 · real-browser journey (headless
Chrome via CDP against a live server on a throwaway SQLite DB in the session
scratchpad — never the dev DB) **37/37**: admin upload/publish/announce/
new version, tenant (Villa 159) read + zoom + acknowledge, mobile 390px no
overflow, Society B tenant direct-id attempts → 404, guard sees only guard
docs, staff 403, real `email_logs` rows `sent` to exactly the two Society A
residents · Flutter analyze 2 pre-existing infos, `flutter test` 71/71,
`flutter build apk --release` built.

**USB dev (`scripts/run_android_usb.ps1`, already existed):** now
distinguishes unauthorized / offline / missing device, prints the exact
failing `adb reverse` command and output, detects a non-SocietyFlow process
holding the port (names it), and passes `API_BASE_URL` automatically for a
non-default `-Port`. Only the no-device path was exercised (no phone
attached); release builds still cannot use a local URL
(`ApiConfig.resolveBaseUrl`).

**Not done this session (stated, not skipped):** gate-level scan
authorization, patrol, vehicle registry/webhook, delivery, household help,
material pass, overstay, Security Center, SOS, child-exit, move-in/out,
OTP device E2E, broad UI redesign — see TODO P1-72. Flutter viewer not yet
run on a device (P1-74).

## Query-scoping bug sweep — 2026-10-08

Latest "do everything" mega-prompt (81 sections) asked for a global
query-scoping audit across the whole codebase. Full codebase audit of that
scale is not a one-pass task; instead swept every controller using the
established technique (grep every route-model-bound method signature,
verify each calls `authorizeSocietyAccess`/an equivalent Super-Admin-
exempted inline check). Found and fixed 2 real bugs; `ExpenseController`,
`ComplaintController`, `ServiceController`, `NoticeController`,
`EventController` were all already correctly scoped.

- **`FacilityBookingController::editStatus()`** had the identical bug
  already fixed once this session in its sibling `updateStatus()` — a bare
  `can('manage_facilities')` check with no model scoping at all, letting an
  Admin in any society view another society's booking details by id.
  Fixed by adding `authorizeSocietyAccess($facilityBooking)`.
- **`Admin\RentController`** (`edit()`, `update()`, `destroy()`, `receipt()`)
  compared `$rent->flat->society_id !== $this->getSocietyId()` with no
  Super Admin exemption. `getSocietyId()` returns `null` for Super Admin
  (no society of their own), so this *fails closed* — it blocked Super
  Admin from managing any rent record at all. Fixed by adding
  `!auth()->user()->hasRole('Super Admin') &&` to all 4 checks, matching
  the "scope by default, exempt Super Admin explicitly" rule in CLAUDE.md §3.
- New regression suite: `tests/Feature/QueryScopingBugSweepTest.php` (3
  tests) — covers both fixes plus confirms Admin is still blocked from a
  genuinely cross-society rent record.
- Also verified this round: the user's specifically-named "Villa 160 via
  Access Pass" claim does **not** currently reproduce — already covered by
  `AccessPassPropertyScopeTest`'s 13 passing tests.
- **Not attempted this round** (explicitly, not silently dropped): Context
  Engine, finance-domain refactor beyond confirming Expense is already
  correct, Patrol/Vehicle/Delivery/Household-Help/Material-Pass/Overstay/
  Security-Center/SOS/Child-Exit/Move-in-out subsystems, UI redesign,
  browser/E2E testing, `docs/FINAL_PRODUCTION_CERTIFICATION.md`,
  `docs/PRODUCT_COMPLETION_MATRIX.md`.

## Guard Shift & Multi-Gate Management (new module) — 2026-10-08

A "final product completion" brief asked for 12+ net-new subsystems
(Guard Shift, multi-gate, handover, patrol, vehicle registry + hardware
webhook, delivery, household help, material pass, overstay, Security
Center, emergency/SOS, child-exit, move-in/out) plus a full UI redesign in
one pass. Attempting a shallow version of all of it would itself have
been the "no fake completion" violation the brief explicitly warned
against. Committed instead to building **one** of them completely and
for real - Guard Shift & Multi-Gate Management, the most foundational and
most-requested piece across several rounds of this project's briefs - and
stated the rest as explicitly not attempted, not silently skipped.

- **New, additive module** (migration `2026_10_08_000001`): `gates`,
  `shifts`, `shift_assignments` (attendance lives directly on this row -
  one row per guard/shift/gate/date), `shift_handovers`. Deliberately
  **not** wired into the existing visitor/access-pass gate authorization
  path (`GuardController::scan()`, `AccessPassService`) - every society in
  this project has operated single-gate with zero `gate_id` concept
  anywhere in the schema, and forcing every already-tested guard action to
  require a gate selection is exactly the "rewrite a working module" this
  session's standing rules prohibit. This is a real, usable roster/
  attendance/handover system on its own; integrating it into scan-time
  authorization is separately-scoped future work.
- **Correct overnight-shift math**: `Shift::windowStartingOn()`/
  `coversMoment()` treat a shift's end time earlier than its start time as
  crossing midnight, anchoring the end to the *next* calendar day rather
  than a naive same-day comparison - the exact bug class this session's
  own Access Pass block/unblock fix was for. `currentAssignmentFor()`
  checks both today's and yesterday's date for exactly this reason (an
  overnight shift that started yesterday is still "now" after midnight).
- **Guard**: `/guard/shift` - current shift, start/end (on-time vs late by
  grace period, both computed server-side from the shift's own window, not
  trusted from the client), handover notes for the incoming guard.
- **Admin**: `/admin/gates`, `/admin/shifts`, `/admin/shift-assignments`
  (roster, filterable by date/gate/guard/status) - all society-scoped,
  Admin/Super Admin only. Guard replacement is audited via the existing
  `ActivityLog` model (who, original guard, replacement, reason, when) -
  reused, not a new audit mechanism. The original assignment is marked
  `replaced`, never mutated to pretend the original guard attended.
- **Overlap prevention is real, not just a same-tuple unique index**: two
  *different* shifts whose actual time windows overlap for the same guard
  (including an overnight shift's tail overlapping a morning shift the
  next day) are both rejected - verified by a dedicated test, not assumed
  from the code existing.
- **Three real bugs found and fixed while writing the tests, not by
  inspection** (all three would have shipped broken without the tests
  actually asserting on behavior, not just "the endpoint returns 200"):
  1. A relation method named `guard()` collided with Eloquent's own
     reserved `Model::guard()` (mass-assignment guarding) - a fatal error
     the moment the model loaded, not a subtle bug. Renamed to `guardUser()`.
  2. The `date` column, despite being declared `DATE` in the migration,
     stores a full `"YYYY-MM-DD 00:00:00"` value on SQLite - every plain
     string `where('date', ...)`/`whereIn('date', [...])` silently matched
     nothing, so the overlap-prevention check let every overlapping
     assignment through. Fixed by using `whereDate()` everywhere a date
     column is compared (portable across SQLite and MySQL, unlike a raw
     `DATE()` SQL function call).
  3. `Shift`/`GuardShiftService` type-hinted `Illuminate\Support\Carbon`,
     but `SocietyClock` (the project's own established timezone helper)
     returns the base `Carbon\Carbon` - a `TypeError` on every call until
     both files were changed to type-hint the base class, matching
     `SocietyClock`'s own convention.
- Tests: `GateManagementTest` (5), `ShiftManagementTest` (6),
  `GuardShiftAssignmentTest` (6), `GuardShiftAttendanceTest` (8),
  `GuardHandoverTest` (6), `GuardReplacementTest` (5) - **36 new tests, all
  exercising the real HTTP route or the service directly, not just unit
  calls to formatting helpers.**
- **Not attempted this round, stated honestly**: multi-gate *authorization*
  (a guard is not actually restricted to their assigned gate's scan/
  visitor actions yet - the data model supports it, the enforcement does
  not); patrol/checkpoints; vehicle registry + hardware webhook; delivery
  tracking; household help; material/gate pass; overstay detection;
  Security Center; emergency/SOS; child-exit protection; move-in/out; the
  full UI/UX "Rangoli" redesign; Flutter guard-shift screens. Each is its
  own scoped piece of work - see `docs/TODO.md`.

## Push notifications removed entirely — 2026-10-08

Push was explicitly ruled out of SocietyFlow's approved communication scope
(Email/SMS/WhatsApp only). Removed completely, not just hidden from the UI -
confirmed by a repository-wide reference search before and after.

- **Deleted outright** (zero remaining references after removal, confirmed
  by a final `grep -rli` sweep across `app/`, `routes/`, `resources/`,
  `database/`, `config/`, `tests/`, and `societyflowmob/`):
  `app/Services/{PushNotificationService,FirebaseService}.php`,
  `app/Http/Controllers/Admin/PushNotificationController.php`,
  `app/Http/Controllers/PushSubscriptionController.php`,
  `app/Jobs/SendPushNotificationJob.php`,
  `app/Console/Commands/{TestPushNotification,CleanupPushSubscriptions}.php`,
  `app/Models/{PushNotificationLog,PushNotificationSetting,PushNotificationTemplate,PushSubscription}.php`,
  `database/seeders/PushNotificationTemplateSeeder.php`,
  4 admin Blade views (`admin/push-notifications/*`), the super-admin
  settings `push` partial, `tests/Feature/PushNotificationTemplateTest.php`.
- **Removed from live code**: the two real push call sites
  (`GuardController::notifyOccupants()`, `VisitorApiController::notifyResident()`
  - both sent a "visitor at the gate" push with no other channel behind
  it, so the notification itself is simply gone now, not stubbed), every
  `push-notifications.*`/`push.*` route, the Settings push tab (both the
  per-society and Super Admin screens - the Super Admin one had a live
  nav entry pointing at a `route()` call that would otherwise now throw),
  `SettingsController`/`SuperAdmin\SettingsController`'s push
  settings/array entries, the `enablePushNotifications` Flutter flag
  (zero readers even before removal), and the push seed rows in
  `SystemSettingsSeeder`/`TwoAdminSampleDataSeeder`.
- **Updated**: `NotificationConfigIntegrityTest` and the Communication
  Center view no longer list `push` as a valid/offerable channel -
  `NotificationEvents::all()` never declared it on any event to begin
  with, so this closes the door rather than fixing a live defect.
- **Deliberately left alone**: the applied migration
  (`2026_02_05_create_push_notification_tables.php`) and its 4 tables
  (`push_subscriptions`, `push_notification_logs`,
  `push_notification_settings`, `push_notification_templates`) - per
  CLAUDE.md §12, an applied migration is not edited; the tables remain as
  harmless orphans, the same category as the already-documented
  `flats_old` table. No code anywhere reads or writes them anymore.
- **Untouched, confirmed working**: Email, SMS, WhatsApp - none of their
  code, routes, settings, or tests were touched by this removal.
- Full regression after the removal: see the run this entry was written
  alongside.

## Sidebar icon fix + a real Access Pass timezone bug — 2026-10-08

- **Sidebar**: "Property & Occupancy" rendered with no icon - `fa-people-roof`
  does not exist in the Font Awesome 6.0.0 build this app loads (added in a
  later FA6 point release), so it silently rendered nothing rather than a
  broken-icon glyph. Replaced with `fa-building` (present since FA5/6's
  first release); label shortened to **"Property"** per direct feedback.
- **Real bug, caught by its own regression test failing at the exact
  moment it manifests**: `AccessPass::blockToday()`/`unblockToday()` used
  the server's raw `now()` (`APP_TIMEZONE=UTC`), while `isBlockedToday()`
  (what the gate-scan path actually checks) converts to the society's
  local clock first (`SocietyClock::local()`, Asia/Kolkata by default).
  For roughly 5.5 hours every day - UTC evening, already past midnight in
  IST - the two disagree on what "today" is: an admin blocking a pass
  during that window wrote the wrong calendar date, and the block silently
  never took effect. `AccessPassViewRenderTest` failed on exactly this
  because the suite happened to run during that window - not a flake, a
  real timing-dependent defect. Fixed by routing both writes through the
  same society-local date `isBlockedToday()` already reads.
  `tests/Feature/AccessPassTest::test_scan_rejects_a_pass_on_a_day_it_is_not_allowed`
  had the identical bug in its own fixture (built "today" from the
  server's raw day-of-week instead of the society's) and was fixed the
  same way, with the same reasoning documented inline.
- **Found, not yet fixed** - the same unguarded `now()->toDateString()`
  pattern exists in 5 more places, confirmed by grep, each a candidate for
  the identical class of bug (silently wrong during the UTC/IST overlap
  window): `FacilityBooking::scopeUpcoming()`, `Staff::todaysAttendance()`-
  style lookups, `UtilityBill`'s overdue scope/accessor,
  `Visitor::scopeToday()`, and `AccessPassController::store()`'s
  `$validFrom` on create. None has a failing test proving the actual
  impact the way the two fixed instances did; flagged rather than
  blind-fixed.
- Full regression after all of the above: **921 tests, 3341 assertions,
  0 failures** - a single, fully-current run (no stale state), confirmed
  twice.

## Admin dashboard "Needs Attention" panel — 2026-10-08

A "final product excellence" brief asked for a sweeping design-system
overhaul, new Gate modules (vehicle registry, delivery, household help,
patrol, SOS), a Notification Center, Activity Timelines and Smart Search
in one pass. Scoped honestly: that is multi-week product and design work,
not achievable for real in one turn, and several of the named modules
(vehicle registry, delivery, household help, patrol, SOS, child-exit) do
not exist in this codebase and were explicitly declined pending product
sign-off in multiple earlier sessions - "complete" implied they already
existed. Building a fraction of any of this and presenting it as finished
would be exactly the kind of fake completion this project's own history
has repeatedly had to correct. Delivered instead the one concretely-specified,
achievable piece from the brief:

- **Admin dashboard, action-first panel** (`DashboardController::needsAttention()`):
  five live counts - pending visitor approvals, open complaints, pending
  service requests, overdue bills, pending facility bookings - each a
  single `count()` query (5 queries total, fixed regardless of data
  volume), each linking straight to the already-filtered list, not the
  module's unfiltered home page. Rendered above the existing 8 KPI cards
  (`admin/dashboard.blade.php`), inside the existing Tailwind idiom - not
  a new design system. A real empty state ("Nothing needs your attention
  right now") when every count is zero, not a row of cards reading "0".
  Society-scoped like everything else on that dashboard.
- Tests: `AdminNeedsAttentionPanelTest` (3: empty state, every module
  counted and linked, cross-society isolation) +
  `QueryPerformanceTest::test_admin_dashboard_needs_attention_panel_is_a_fixed_number_of_queries`
  (1). Existing `AdminDashboardKpiTest` (5) re-confirmed unaffected.
- **Not attempted, stated honestly**: a design-system overhaul of any
  screen; a Notification Center UI; Smart Search; Activity Timelines;
  Security Center; Guard/Resident quick-action redesign (the Guard
  dashboard already has SCAN/New Visitor as its two primary actions,
  matching the achievable subset of what the brief asked for - re-verified,
  not rebuilt); vehicle registry, delivery tracking, household help,
  patrol/checkpoints, SOS incidents, child-exit protection, multi-gate -
  none exist and building any of them is net-new feature engineering, not
  a "completion" of existing work.

## Final production certification pass — 2026-10-07

A "final certification" brief asked for a full-system inventory, security
sweep, notification-matrix validation, Flutter build, performance pass and
dead-code cleanup in one go. Scoped honestly: this was a targeted,
evidence-based audit on top of the existing 917-test suite and the two
prior P1-61/P0-12 rounds below, not a re-walk of every screen by hand.
Full report given to the user in the requested 12-section format; summary:

- **API IDOR sweep** (every parameterized route in `routes/api.php`):
  checked `VisitorApiController`, `AccessPassApiController`,
  `ComplaintApiController`, `NoticeApiController`, `FacilityApiController`,
  `ServiceApiController`, `EventApiController`, `PaymentApiController`
  against the full entity list the brief named. **Clean** - every route
  already scopes by society_id/user_id/host_user_id correctly, or (Access
  Pass) delegates to `AccessPassEditService::assertMayManage()`, which both
  web and API already share. No new IDOR found.
- **Fresh-install integrity, verified not assumed**: ran every migration +
  the default `db:seed` path against a brand-new throwaway SQLite file (not
  the dev database). Every one of the 15 registered notification templates
  existed and was active; 9 roles / 36 permissions seeded correctly.
  Confirms P0-12's registry work actually survives a clean install.
- **Performance - two real, measured fixes**:
  - `CommunicationCenterController::index()` (this session's own new code)
    called `NotificationRules::isEnabled()` in a nested loop over every
    registered event's channels - a query per channel per event. Fixed
    with `NotificationRules::allFor()` (one query); added
    `QueryPerformanceTest::test_communication_center_rules_tab_...`,
    calibrated against this app's own measured per-page baseline (~27
    queries for a comparable existing admin page), not an arbitrary number.
  - `Admin\RentController::index()` called `Schema::hasColumn('maintenance_bills',
    'bill_type')` on every page load - exactly the runtime schema
    introspection CLAUDE.md §9 rules out. The column has existed since
    migration `2026_02_02`; removed the check.
- **Found, documented, deliberately not fixed**: `PushNotificationService`'s
  Web Push (VAPID) path - `computeECDH()` is a placeholder
  (`hash('sha256', $privateKey . $publicKey, true)`), not real
  elliptic-curve Diffie-Hellman. `docs/PRODUCTION_CHECKLIST.md` previously
  (incorrectly) called Web Push "the only working channel" - corrected.
  Not rewritten this round: a spec-correct implementation (RFC 8291) can
  only be proven correct against a real subscribed browser, and no browser
  automation is available here - a blind rewrite would be exactly the
  "claim PASS from a mocked delivery" this brief explicitly rules out.
  Recommended path: `minishlink/web-push` (or equivalent maintained
  library) rather than hand-rolled crypto.
- **Flutter**: `flutter analyze` - 2 pre-existing info-level deprecations,
  0 new. `flutter test` - 69/69 passing. `flutter build apk --release` -
  succeeds (103.2MB). Still debug-signed, still `com.example.societyflow` -
  both pre-existing, documented operator decisions (P0-7/P0-8), correctly
  left untouched. No physical device or emulator connected in this
  environment (`flutter devices` lists only Windows desktop + browsers) -
  device-level E2E is **BLOCKED**, not claimed as passing.
- **Not reattempted, cited instead**: a full manual walkthrough of every
  business module (visitors, bills, complaints, facilities, events,
  notices, guard flows) - the existing 917-test suite already encodes this
  validation across many prior sessions; re-walking it by hand would
  duplicate, not add, coverage. Live SMS/WhatsApp/email provider delivery
  and browser-automation click-throughs remain BLOCKED - no live
  credentials or browser automation exist in this environment, consistent
  with every prior session's own finding.
- Full backend regression after all of the above: **917 tests, 3326
  assertions, 0 failures** (up from 916/3320 - the one new query-count
  test).

## P1-61: Communication Center + Access Pass / Bill / Facility Booking notifications — 2026-10-07

Built directly on the canonical registry below (not a rebuild): extended
`NotificationEvents` with 4 new real events, built the Communication Center
admin UI on top of it, added WhatsApp approved-template sync, and found 2
more real bugs (one cross-society IDOR, one validation crash) purely as a
byproduct of writing feature tests for the new wiring - the same pattern
that kept paying off throughout this registry work.

- **4 new registered events, each a real, previously-unwired business
  action** (confirmed by grep before writing any code - Access Pass and
  Facility Booking had *zero* notification call sites of any kind):
  - `access_pass.created` - `AccessPassController::store()` now dispatches
    `AccessPassCreated` -> confirmation email to whoever created the pass.
  - `access_pass.revoked` - `AccessPassController::revoke()` now dispatches
    `AccessPassRevoked` -> email + SMS + WhatsApp to the pass's creator.
    Security-group and three-channel because Admin/Super Admin can revoke a
    pass a *resident* created - the resident who lost their staff's gate
    access may not be the one who revoked it. First event in the registry
    proving all three channels fire off one real dispatch.
  - `bill.generated` - **reactivated**, not new: `BillGenerated`/
    `SendBillGeneratedEmail`/its template already existed (seeded by
    2026_10_07_000002) but nothing ever dispatched the event (documented in
    `knownDeadEventClasses()`'s own history). `Admin\RentController::store()`
    - the one real bill-creation call site in the codebase - now fires it.
  - `facility_booking.status_updated` - `FacilityBookingController::updateStatus()`
    (Admin-only; approve/reject/cancel/complete) now dispatches
    `FacilityBookingStatusUpdated` -> email to the resident who booked it.
  - Payment and Society Event were **not** duplicated - `payment.received`
    and `event.created`/`event.updated` (already registered) already cover
    those domains from a real dispatch site each.
- **2 real bugs found while writing the feature tests for the above, both
  fixed**:
  - **Cross-society IDOR**: `FacilityBookingController::updateStatus()`
    checked only `can('manage_facilities')` (a bare permission, no model) -
    unlike every sibling method in that controller, it never called
    `authorizeSocietyAccess()`. Any Admin, in any society, could approve/
    reject/cancel another society's booking by id. Fixed; regression in
    `FacilityBookingNotificationTest::test_an_admin_cannot_update_another_societys_booking`.
  - **Validation crash**: `admin_notes` is `nullable`, so Laravel's
    `validate()` omits the key entirely when the field is absent from the
    request rather than returning null for it - the exact same shape as the
    `EventController::update()` bug already fixed and documented in
    `docs/TODO.md` (P1-25). Approving/cancelling a booking with no notes
    threw an undefined-array-key error instead of saving. Fixed with
    `?? $facilityBooking->admin_notes`.
- **`NotificationRules`** (`app/Support/NotificationRules.php`): the on/off
  switch behind "Rules & Notifications", opt-out by design - every
  event/channel is enabled unless a row explicitly disables it, so
  introducing this table can never silently stop a notification that was
  already working. Enforced centrally in
  `DispatchEventNotification::handle()` for every trigger_event-based
  event (11 of 15 registered events); slug-based events are shown
  read-only in the UI - they go through `shouldReceiveEmail()`'s existing
  role-permission map instead, a different, pre-existing mechanism this
  round did not rewire.
- **Communication Center** (`/admin/communication-center`, linked in the
  sidebar for Admin/Super Admin): 4 tabs. Providers links to the existing
  Settings Email/SMS/WhatsApp tabs (no secret ever duplicated here). Rules
  & Notifications lists every registered event under exactly the 5
  requested business groups, with a per-channel checkbox, a Save, and a
  Test button that **only ever messages the acting admin's own email/
  phone** - never a resident, never "everyone in this group". Templates
  links to the existing Email Templates screen and adds the new WhatsApp
  template list. Delivery Logs links to the existing, already multi-channel
  Email Logs screen.
- **WhatsApp approved-template sync** (`WhatsAppTemplateSyncService`,
  `whatsapp_templates` table): calls the real, documented Fast2SMS endpoint
  (`GET /dev/whatsapp/{version}/{waba_id}/message_templates`, confirmed
  against docs.fast2sms.com/reference/get-all-templates, not guessed).
  Needs a new `whatsapp_waba_id` setting (added to Settings → WhatsApp,
  separate from the `whatsapp_phone_number_id` already used for sending).
  Friendly names are purely local - Meta's template object has no such
  field - defaulting to a humanised version of the technical name on first
  sync and never overwritten by a re-sync. The raw Meta/Fast2SMS id is
  never rendered in the Rules view. **Not verified against a live
  account** - no WhatsApp Business Account ID or API key is available in
  this environment; tested via the same `$transportOverride` seam
  `WhatsAppService`/`SmsService` already use.
- **WhatsApp delivery logging**: `WhatsAppService::sendNotification()` now
  writes an `email_logs` row (`channel=whatsapp`) the same way
  `SmsService::sendDltNotification()` already does - a WhatsApp send is
  now visible on the Delivery Logs screen, not just in the application log.
  Not logged when nothing was actually attempted (no template configured,
  or WhatsApp disabled) - same convention SMS already follows.
- Tests: `AccessPassNotificationTest` (4), `BillGeneratedNotificationTest`
  (3), `FacilityBookingNotificationTest` (4), `NotificationRulesTest` (7),
  `WhatsAppTemplateSyncTest` (6) - 24 new, all green, all exercising the
  real controller route, not just the service layer. Email-channel
  assertions use `Queue::fake()` + inspect the pushed
  `DispatchEventNotification` job rather than `email_logs` directly -
  `->afterCommit()` never fires inside `RefreshDatabase`'s own
  test-wrapping transaction (confirmed by hitting it again this round), the
  same gotcha `DispatchEventNotificationTest`/`NoticePublishedEmailE2ETest`
  already document.
- Full backend regression: see the run this entry was written alongside.
  Flutter untouched this round - no backend/API contract change required
  one.
- **Not attempted this round, stated honestly**:
  - **Push**: `PushNotificationService`/`FirebaseService` are untouched.
    Per CLAUDE.md §11, no Flutter client registers an FCM device token at
    all (`firebase_messaging` absent) - wiring a new event to push would
    call a real service that has literally no device to deliver to for any
    user, which is indistinguishable from faking it. Not wired until a real
    mobile push client exists.
  - Security/Important beyond `access_pass.revoked` (e.g. failed-login
    alerts, account lockout notices) - audited, found no existing real
    dispatch point to wire; inventing one with no business action behind
    it is exactly what this registry exists to prevent.
  - A generic rule-toggle for slug-based events (bill-due-reminder,
    service-request-*, ticket-created, event-updated, visitor-denied) -
    they stay controlled by the pre-existing role-permission map, shown
    read-only in the Rules UI rather than half-wired to a second mechanism.

## Canonical notification-config registry + 3 more missing-template bugs found — 2026-10-07

A "final communication architecture" brief asked for one canonical event
registry + an automated integrity test so a missing template/permission can
never again go silently undetected (this session already hit that exact bug
three times: Notice, `bill-due-reminder`, `service-request-*`), plus the
remaining business events wired, a full Communication Center UI, WhatsApp
template sync, and unified multi-channel Delivery Logs. Scoped honestly: the
registry + integrity test (the actual structural fix) and the Delivery Logs
unification were built for real; the admin UI, WhatsApp template sync, and
wiring further business domains (Access Pass/Bill/Payment/Facility/Society
Event/Security beyond what already existed) were **not** — each is its own
multi-day scope, not a continuation of this fix.

- **`app/Support/NotificationEvents.php`** — the canonical registry: every
  real, dispatched notification event in the codebase, keyed by a canonical
  name, each declaring its business group, which of the two existing lookup
  systems it goes through (`trigger_event` via `DynamicEmailService`, or
  `slug` via `EmailNotificationService::shouldReceiveEmail`), its
  template(s), channels, and real dispatch call site. 15 events registered:
  the 7 `DynamicEmailService` ones, now including `user.password_reset`
  (confirmed live — `AuthController::reset` really does dispatch
  `PasswordReset`, contradicting this file's own 2026-10-07 "root-cause"
  entry below which called it dead; corrected), plus 8 slug-based ones
  (`bill.due_reminder`, `complaint.created`, `event.updated`,
  `visitor.denied`, the 4 `service_request.*`). Also documents
  `knownOrphanTemplateKeys()` (active templates nothing reaches, with the
  reasoning per row) and `knownDeadEventClasses()` (`BillGenerated`,
  `PaymentOverdue`, `ReportGenerated` — listeners registered in
  `EventServiceProvider`, confirmed zero dispatch call sites anywhere).
- **`tests/Feature/NotificationConfigIntegrityTest.php`** (8 tests) —
  iterates the registry and asserts: no duplicate keys; every event has an
  active global template matching its own lookup column; every template
  declares variables; every slug-based event is reachable by at least one
  resident role in `shouldReceiveEmail()` (reflection — no public API
  exists for "would this role get this email", which is itself why this bug
  kept recurring); no active template is an unexplained orphan; no listener
  in `EventServiceProvider::$listen` is dead without being documented as
  such (and the inverse — a documented-dead class must still actually be
  undispatched). **Running this test immediately found 3 more real bugs**,
  all the same shape as the already-fixed Notice/bill-reminder ones —
  confirming the brief's premise that the registry approach surfaces more
  than ad-hoc patching:
  - `ticket-created`, `new-event` (slug, used by
    `EventController::update`'s "event updated" email — not the
    underscore `event_created` trigger_event row, a different row
    entirely), and `visitor-denied` each existed **only in this project's
    dev `database.sqlite`**, inserted by hand at some point with no
    migration behind them. A fresh install or the MySQL production
    database has never had any of the three rows, so "complaint
    submitted", "event updated", and "visitor denied" emails have been
    silently skipped there since each feature shipped. Fixed with three
    migrations (`2026_10_07_000007/8/9`) reusing the exact dev-DB content.
  - `visitor-denied` *and* `visitor-approved` were also missing from
    `shouldReceiveEmail()`'s resident-role allowlist entirely (added
    alongside the existing `visitor-entry-created`/`visitor-status-updated`
    entries for all 4 resident roles).
- **Delivery Logs are now genuinely multi-channel.** `email_logs` widened
  (SQLite table rebuild, 382 existing rows verified preserved byte-for-byte,
  row count checked before the swap) — `email_template_id`/`recipient_email`
  nullable, `recipient_phone` added. `SmsService::sendDltNotification` now
  writes a log row (`channel=sms`) alongside the SMS send; the admin
  `/admin/email-logs` screen shows a channel badge, phone fallback and a
  channel filter. `DeliveryLogsMultiChannelTest` (4).
- **`ServiceRequestNotificationTest`** (3) proves the `service-request-*`
  fix end-to-end, including through the real `POST .../assign` route — not
  just at `sendByTrigger()`.
- Full backend regression: **892 tests, 3235 assertions, 0 failures**
  (up from 877/3082 before this round). Flutter untouched this round
  (no Dart files edited) — not re-run, no reason to expect a change.
- **Not attempted this round** (stated honestly, not implied done):
  Communication Center admin UI (Providers/Rules & Notifications/
  Templates/Delivery Logs reorganisation with 5 fixed business groups),
  WhatsApp template friendly-name sync, per-event Email/SMS/WhatsApp/Push
  ON/OFF toggles with Test buttons, wiring Access Pass/Bill/Payment/
  Facility Booking/Society Event/Security notifications that don't already
  exist above. Each is separately scoped, multi-day work.

## Production audit round 4: Service Request lifecycle, Service Provider IDOR, OTP/Guard fixes — 2026-10-07

- **Security fix — real cross-society IDOR:** `ServiceProviderController`
  authorized edit/update/destroy/quickUpdate/toggleAvailability with
  `authorize('manage_services')`, a plain role permission with no model
  attached. Any Admin, in any society, could edit or delete another
  society's service provider by id. `ServiceProviderPolicy::update()`/
  `delete()` already had the correct same-society check; the controller
  never called it. Fixed by routing those five actions through
  `authorize('update'|'delete', $serviceProvider)`.
  `ServiceProviderCrossSocietyAccessTest` (6).
- **Gap closed — Service Request lifecycle.** `service_requests` could be
  created (`ServiceApiController::requestService`) but never listed,
  assigned, progressed or completed anywhere, web or mobile. Added:
  `ServiceRequest` model, a migration adding `assigned_provider_id`,
  `assigned_by`, `assigned_at`, `started_at`, `completed_at`, `cancelled_at`,
  `cancellation_reason`; `ServiceController::requests/assignRequest/
  updateRequestStatus` (web, `resources/views/services/requests.blade.php`);
  `ServiceApiController::myRequests/cancelRequest` (mobile, list + cancel
  only — assign/progress stays a web admin action for now). Status flow:
  pending → assigned → in_progress → completed, or cancelled from any open
  state. A resident may only cancel their own open request; assign/progress
  is Admin/Super Admin only (same boundary as `service-providers`, matching
  the existing "Staff can view services but not manage providers" rule).
  Email hook added (`EmailNotificationService::sendServiceRequestNotification`)
  — safely no-ops until an admin creates `service-request-*` templates via
  Settings; never claims a delivery that did not happen.
  `ServiceRequestLifecycleTest` (9).
- **Gap closed — untested Complaints lifecycle/isolation.** No test file had
  ever covered it. Read the controller: already correctly scoped (no bug
  found). `ComplaintLifecycleAndIsolationTest` (6) pins the full lifecycle
  and five isolation boundaries.
- **Security fix — OTP guessing.** `OtpAuthController::verifyOtp` allowed 5
  attempts per phone per 15 minutes, a window that resets on its own -
  roughly 480 guesses/day against one number, non-trivial against a 4-digit
  code over time. Added a 20-per-day ceiling per phone that failed guesses
  do not clear. `OtpDailyGuessLimitTest` (2).
- **Data-consistency bug fixed — web Guard "Waiting" tile.** Counted only
  today's pending visitors, while the list beneath it and the mobile
  "Pending Approval" figure counted every pending visitor regardless of
  day - the tile could read 3 while its own list showed 11.
  `GuardController::todaysStats()` waiting count now matches both.
  `GuardWaitingCountConsistencyTest` (2).
- **Mobile session tokens moved to secure storage.** `flutter_secure_storage`
  (Keystore/Keychain), replacing a SharedPreferences plaintext file.
  `api_service.dart` no longer keeps its own duplicate copy of the token key.
  `storage_service_test.dart` (6). `android:allowBackup="false"` added.
- **Release build hardened against a local-address footgun.** A release APK
  could previously be pointed at `127.0.0.1`/a LAN address via
  `--dart-define=API_BASE_URL=`, and `ServerDiscovery` bypassed the same
  check. `ApiConfig.resolveBaseUrl()` is now the one place this is decided;
  a release build never resolves to a local address regardless of what is
  passed. `api_config_test.dart` (19 incl. `ServerDiscovery` candidates).
  Verified against a real device: `societyflow.joboapps.com` reached
  directly with the USB tunnel removed; `127.0.0.1` refused.
- **Guard document scanner (Smart Visitor) implemented and device-verified**
  (see the section below this one) - was previously only designed, never
  built.
- **Fast2SMS OTP route implemented** against the real `/dev/otp/send`
  endpoint with the saved `fast2sms_otp_id` (see the section below).
- Backend regression after all of the above: **835 tests, 2967 assertions,
  0 failures** (full suite, exit 0).
- **Audited, not changed:** `FacilityController` already uses a dedicated
  society-aware authorization method (not the vulnerable pattern above).
  WhatsApp: confirmed **not implemented anywhere** in the codebase - nothing
  to configure or test. Email settings: masked-secret handling correct,
  password never logged.
- **Declined without product sign-off:** multi-gate, gate-wise guard
  assignment, patrol/checkpoints, SOS incidents, child exit protection,
  move-in/out, offline-safe gate operation, an automation rules engine,
  cross-gate visibility. Each is a new data model and a real UX decision,
  not a bug fix - building them unilaterally in an autonomous pass is
  exactly what CLAUDE.md's "do not replace working functionality with a new
  architecture unnecessarily" warns against.
## Fast2SMS DLT completion + WhatsApp (Fast2SMS WABA) — 2026-10-07

- **Fast2SMS**: added `fast2sms_entity_id` (DLT compliance record, plain -
  not sent to the API, Fast2SMS has no such parameter), three per-purpose
  toggles (`visitor_sms_enabled`, `access_pass_sms_enabled`,
  `notification_sms_enabled`), and independent "Test OTP"/"Test DLT SMS"
  buttons (`SmsService::sendOtp()` gained a `$forceRoute` param so a test
  can exercise either path regardless of the saved Route). New generic
  `SmsService::sendDltNotification($purpose, ...)`, gated by its toggle -
  **built and tested, not yet called from any visitor/access-pass/notice
  event**; the toggles exist but nothing fires them yet.
- **WhatsApp implemented** (`app/Services/WhatsAppService.php`) against
  Fast2SMS's own published API reference
  (docs.fast2sms.com/reference/send{authentication,text}template,
  sendsessionmessage) - not guessed. Template messages via
  `POST /dev/whatsapp/{version}/{phone_number_id}/messages`; session
  (free-text, 24h-window) messages via `POST /dev/whatsapp-session`.
  Settings tab: enable toggle, API key (encrypted/masked), API version,
  Phone Number ID, template language, and seven named templates (OTP +
  six notification purposes). Test button sends a real request against
  whichever template is configured. **Not wired into OTP login** - the
  capability exists (`WhatsAppService::sendOtp`) but `OtpAuthController`
  still only uses SMS, left untouched deliberately.
- Declined (see the prior round's entry and the chat record): the
  fifteen-subsystem MyGate-style gate buildout (multi-gate, delivery,
  household help, vehicle registry, overstay, gate pass, SOS, child-exit
  protection, patrol, guard comms, utility vehicles, move-in/out). Each
  needs a real data-model and UX decision, not an autonomous pass.
- Tests: `WhatsAppSettingsTest` (15), `Fast2SmsDltSeparationTest` (10).
  Full backend regression: **860 tests, 3050 assertions, 0 failures**.
  Flutter unaffected by this round (admin-web-only feature): analyze 2
  pre-existing notices, test 69 passing, unchanged.
## Root-cause fix: real events were not emailing anyone — 2026-10-07

Reported defect: "Test Email works, but a published Notice email does not
reach configured users." Verified true, root-caused, fixed with real
end-to-end test proof (not just code inspection).

- **The dispatch pipeline was already correct.** `NoticeController::store()`
  fires `NoticePublished`, `EventServiceProvider` maps it to
  `SendNoticePublishedEmail`, which calls
  `DynamicEmailService::sendByEvent('notice_published', ...)`. The same
  pattern already existed, wired, for 10 other events.
- **Root cause 1:** no *active* `EmailTemplate` row had `trigger_event =
  'notice_published'` (or any of the other 10 events' keys).
  `EmailTemplate::getTemplateForEvent()` requires an exact match on that
  column and silently returns null - `sendByEvent` then logs "No email
  template configured" and sends nothing. Settings → Test Email never hits
  this lookup at all (`Mail::raw()`, no template), which is exactly why it
  "worked" while real notices did not.
- A near-complete set of templates already existed in
  `AutoEmailTriggerTemplatesSeeder`, but (a) it is never called from
  `DatabaseSeeder` - CLAUDE.md's documented default `db:seed` path
  deliberately excludes email content - and (b) its rows never set
  `trigger_event`, only `slug`, so even running it would not have fixed the
  lookup. Fixed with a **migration**, not that seeder: migrations run
  exactly once in every environment including production, so this does not
  depend on anyone remembering an extra seed command.
  `database/migrations/2026_10_07_000002_seed_default_email_templates_for_wired_events.php`
  seeds one global (`society_id = null`) active template per event, with
  the correct `trigger_event`, reusing the orphaned seeder's content where
  the variable set matched. Idempotent - never touches a row that already
  exists, so a society's own customised template is never overwritten.
- **Root cause 2 (Notice only):** `SendNoticePublishedEmail`'s recipient
  resolution could leave `$users` unset and throw on an undefined variable
  for a `target_audience` array with no overlap in its allowed roles -
  caught and logged as a quiet failure. Now defaults to an empty collection.
- **Confirmed by real request → event → listener → Mail::send → EmailLog
  test**, not by inspection: `NoticePublishedEmailE2ETest` (4) - a
  published notice reaches Tenant/Owner/Admin, excludes Guard/Staff, a
  draft sends nothing, and another society's resident never appears in the
  logs.
- **Scope, stated honestly:** of the 11 events with this dispatch+listener
  pattern, only 7 are actually fired anywhere in the codebase
  (UserCreated, PaymentReceived, NoticePublished, EventCreated,
  TicketUpdated, TenantOwnerAdded, VisitorApproved) - the template fix
  unblocks all 7. Four more (`BillGenerated`, `PaymentOverdue`,
  `PasswordReset`, `ReportGenerated`) have a listener and now a template,
  but nothing in the codebase ever dispatches them - **not fixed this
  round**, left as found. `PasswordReset` in particular looks like dead
  code: the live forgot-password flow goes through a different path,
  already covered by `AuthSecurityTest`.
- **Not attempted this round:** the "Communication Center" admin UI
  (providers/rules/templates/delivery-logs reorganisation), per-event
  Email/SMS/WhatsApp/Push channel toggles, a central recipient-resolver/
  rule-resolver/queue framework, WhatsApp template sync, and the P0-P2
  gate/security feature list (multi-gate, delivery, household help,
  vehicle registry, overstay, gate pass, SOS, child-exit protection,
  patrol, utility vehicles, move-in/out). Each is substantial, separately
  scoped work - see the two prior entries' reasoning, which still applies.
- Tests: `NoticePublishedEmailE2ETest` (4). Full backend regression: see
  the run this entry was written alongside.
## Queued/idempotent notification pipeline + two more real bugs found and fixed — 2026-10-07

Built on the Notice root-cause fix above. Scope: make the 7 already-dispatched
events (not the full ~35-event/communication-center spec) queued, retried,
idempotent and after-commit, and investigate the 4 never-dispatched events.
The full admin "Communication Center" UI, event-channel matrix, and the
15-feature MyGate gate/security list are still not attempted - see the two
prior entries' reasoning.

- **`app/Jobs/DispatchEventNotification.php`** - `ShouldQueue` +
  `ShouldBeUnique` (keyed on a deterministic `notification_key`, e.g.
  `notice_published:42:owner@example.com`), `tries=3`, `backoff=[10,30,60]`.
  Checks `email_logs` for an already-`sent` row with the same key before
  sending (idempotent across retries); a per-recipient send failure (logged
  as a `failed` row by the existing `DynamicEmailService`) makes the job
  throw, so the queue retries it - a true `sent` is never retried.
  `QUEUE_CONNECTION` is still `sync` (not changed - no worker process runs
  in this environment, and switching without one would silently stop every
  notification). Under `sync`, `ShouldQueue` jobs still run, just inline;
  the retry/backoff/afterCommit semantics are real and need no code change
  the moment a worker environment is configured.
- All 7 listeners (`SendUserWelcomeEmail`, `SendPaymentConfirmationEmail`,
  `SendNoticePublishedEmail`, `SendEventCreatedEmail`,
  `SendTicketUpdatedEmail`, `SendTenantOwnerAddedEmail`,
  `SendVisitorApprovedEmail`) now `DispatchEventNotification::dispatch(...)
  ->afterCommit()` instead of calling `DynamicEmailService::sendByEvent()`
  directly. Their recipient-resolution and variable-building logic is
  untouched. `ticket_updated`'s key includes the complaint's current
  status, not just its id, so open→in_progress→resolved→closed still
  notifies at each step rather than being deduped into one.
- **`email_logs`** gained `channel`, `notification_key`, `provider_reference`
  - this table already *was* the delivery log (recipient, status, error,
    timestamp per attempt); widening it keeps one delivery log instead of
    a second parallel one. Standard `jobs`/`job_batches`/`failed_jobs`
    tables were also added, ready for the day `QUEUE_CONNECTION` changes.
- **Missing-dispatch investigation (the 4 events nothing ever fires):**
  - `PasswordReset` - dead. The real forgot-password flow uses Laravel's
    native `Password::sendResetLink()`, never this custom event. Left as
    found.
  - `ReportGenerated` - dead. Reports (`ReportController`) are synchronous
    PDF downloads in the same request; there is no async "your report is
    ready" use case for it to serve. Left as found.
  - `BillGenerated` / `PaymentOverdue` - dead as *events*, but investigating
    them surfaced that the **real** bill-reminder mechanism
    (`bills:send-reminders`, scheduled daily at 09:00 and 18:00) was
    **completely broken**, independently of anything above:
    1. It called `EmailNotificationService::send(...)` - a method that does
       not exist on that class. Every attempt threw an uncaught `Error`
       (not `Exception`, so the command's own `catch` never saw it) - no
       bill reminder has ever sent, for any society, since this command
       shipped. Fixed: calls `sendByTrigger()`, the method that actually
       exists, with variables matching the template's own placeholders.
    2. `shouldReceiveEmail()`'s per-role permission allowlist listed
       `bill-overdue` for every resident role but never `bill-due-reminder`
       - the trigger this command actually sends - so no recipient was ever
       permitted to receive it even after fix #1. Added the missing entry
       for Owner/Villa Owner/Apartment Owner/Tenant.
    3. No migration ever created the `bill-due-reminder` `EmailTemplate` row
       - it only existed in this project's dev database from some earlier,
       undocumented, non-migration seeding. A fresh install (or any test)
       had nothing to find. New migration
       (`2026_10_07_000004_seed_bill_due_reminder_email_template.php`)
       seeds it, content matching the dev-DB row, not newly invented copy.
    **Not audited:** `EmailNotificationService`'s other slug-keyed call
    sites (`welcome-user`, `reset-password`, `new-bill-generated`,
    `payment-received`, `ticket-*`, `new-notice`, `new-event`,
    `visitor-*`) may have the same missing-template gap. Flagged, not
    checked.
- **Notice is now two independent channels.** `SendNoticePublishedSms` (new,
  separate listener, not a branch inside the email one - Laravel stops
  calling an event's remaining listeners if an earlier one throws, so two
  decoupled listeners is what actually makes "email failing doesn't stop
  SMS" true, not a shared try/catch) sends via the already-built
  `SmsService::sendDltNotification('notification', ...)`, gated by the
  existing `notification_sms_enabled` Settings toggle. Off by default.
- **Delivery Logs: already existed.** `Admin\EmailLogController` +
  `/email-logs` (index with stats/filters/pagination, show, retry) was
  already a complete, routed, society-scoped admin screen - nothing built
  here. It currently shows email only: making SMS/WhatsApp sends also write
  to `email_logs` (now that it has a `channel` column) needs `recipient_email`
  and `email_template_id` to become nullable, which SQLite cannot do without
  a full table rebuild (CLAUDE.md §5's documented pattern for this). Not
  attempted this pass - judged too risky to rush against real dev data at
  the end of an already large change set. SMS sends remain logged via
  `Log::info`/`Log::warning` with the phone masked, just not in this
  queryable table yet.
- Tests: `DispatchEventNotificationTest` (7, calls `handle()` directly -
  `afterCommit()` jobs never fire inside `RefreshDatabase`'s own
  test-wrapping transaction, which rolls back rather than truly commits),
  `NoticePublishedEmailE2ETest` (4, rewritten to assert at the dispatch
  boundary via `Queue::fake()` for the same reason), `BillReminderCommandTest`
  (2), `NoticeMultiChannelTest` (3). Full regression: see the run this
  entry was written alongside.
## Production APK (royalprime) — BLOCKED, 2026-10-05

- **`https://royalprime.joboapps.com` is not a working API.** `/api/v1/health`
  and `/` both answer `301` to the same URL, so the redirect never ends (curl
  stops with error 47 after 5 redirects). Cloudflare terminates TLS
  (`server: cloudflare`); the pattern fits an origin that forces HTTPS behind
  Cloudflare "Flexible" SSL. Not confirmed on the server.
- `https://societyflow.joboapps.com/api/v1/health` answers 200 with
  `application: societyflow`. It is the current `ApiConfig.prodUrl` and was
  left unchanged. Switching the release to royalprime waits on the redirect fix.
- **Not built, installed or tested against a live login.** No live test
  account is available in this session. Per the release rules, no APK is
  claimed production-ready.
- Release-safety fixes made: a release build never uses a local address
  (`ApiConfig.resolveBaseUrl`, tests in `test/api_config_test.dart`); discovery
  goes through the same rule; logged request lines carry only the path, never
  the query string; `android:allowBackup="false"` keeps stored sessions out of
  Android backups (manifest change not yet compiled).
- Still open: the session token sits in SharedPreferences inside app storage.
  `flutter_secure_storage` is the fix, but it adds a dependency and needs approval.

## Guard document scanner (Smart Visitor) — 2026-10-05

The feature was **not implemented before this session** — a search for
`document_scan`, `mlkit`, `ocr`, `aadhaar` or `smart_visitor` across
`societyflowmob/lib` and `test` returned zero files. Earlier sessions had
designed it in `docs/` only. It is now built:

- **Guard UI location:** Visitors tab → `Add Visitor` FAB → the
  `_AddVisitorSheet` in `lib/features/visitors/screens/visitors_screen.dart`.
  It now opens with **`Scan ID / Document`** and **`Enter Manually`**
  (staff/guard only — a resident inviting their own guest sees neither).
- **Scanner:** `google_mlkit_document_scanner` (Play-services document
  scanner activity: edge detection, crop, retake) in
  `lib/core/services/document_scan_service.dart`. **Android only** — there is
  no iOS implementation, so `isSupported` gates every call and the UI falls
  through to manual entry. This is separate from `mobile_scanner`, which is
  untouched and still reads access-pass QR codes.
- **OCR:** `google_mlkit_text_recognition`, on-device, over the captured JPEG.
- **Review before save:** `document_scan_screen.dart` — document type
  (Aadhaar / Driving Licence / PAN / Passport / Other), the captured page,
  editable fields, and `Continue` / `Retake` / `Enter Manually`. When OCR
  reads nothing it says so and offers empty fields; it never invents data.
- **Aadhaar:** only the last four digits are ever kept
  (`XXXX XXXX 1234`). Redacted at parse time in `id_document.dart`, again if
  the guard types a full number, and **again server-side** in
  `app/Support/IdProof.php` — a client is not a security boundary. Raw OCR
  text is never a model field, never logged, never uploaded. The captured
  image stays in the app cache and is deleted when the flow ends.
- **Stored fields:** `visitors.visitor_id_proof` and `visitor_id_number`
  (both pre-existing columns). The address read off a card is shown to the
  guard for their check and deliberately **not** transmitted — a visitor
  record has no address column.
- **Duplicate prevention:** `POST /api/v1/visitors/lookup (JSON body `phone`)` (gate staff
  only, society-scoped, never returns a pass token) → the sheet shows
  "Existing visitor found" with `Use Existing` / `Update Details` /
  `Check In`.
- **Property context:** the guard never picks a society, building or owner;
  only the visit destination, which the backend re-validates against the
  guard's own society.
- Tests: `test/id_document_parser_test.dart` (17, incl. the Aadhaar-redaction
  and no-invented-data cases) and `tests/Feature/VisitorIdProofTest.php` (14).
- **Verified on a real device** (Realme RMX3660, Android 15 / SDK 35, Google
  Play services present; release APK built with
  `--dart-define=API_BASE_URL=http://127.0.0.1:8000/api/v1` over
  `adb reverse`, guard account society 6):
  Guard Panel → Visitors → `+ New Entry` → **`Scan ID / Document`** →
  document-type chips → `Open Scanner` → **the real Play-services scanner
  opened with a live camera UI** → captured a page → crop editor (`Apply`) →
  `Next` → **back into the app's `Check the details` review screen** with
  `Continue` / `Retake` / `Enter Manually`. OCR ran and, on a non-ID target,
  said so: "Nothing could be read off this document." Typing a known number
  showed **"Existing visitor found — Suraj Suman • guest • last visited 158"**
  with `Use Existing` / `Update Details` / `Check In`, and `Check In` moved
  visitor 44 to `entry_status=entered`, `actual_entry_time=13:41:20`.
- **Not verified on-device:** a successful field extraction from a genuine
  Aadhaar/DL/PAN card — no physical ID was available to photograph, so only
  the "OCR read nothing" path was exercised live. The extraction and
  redaction rules are covered by the 17 unit tests instead.
- `flutter build apk --release` succeeds (102.1MB, up from 75.6MB: ML Kit's
  bundled OCR model). Pre-existing Gradle/AGP/Kotlin deprecation warnings
  are unchanged.
## SMS (Fast2SMS) and Guard containment — 2026-10-05

- **Fast2SMS OTP route** now calls `POST https://www.fast2sms.com/dev/otp/send`
  with `mobile`, `otp_id`, `otp`, `otp_expiry` and the saved key in the
  `authorization` header. The approved OTP ID is the plain setting
  `fast2sms_otp_id` (not encrypted, shown as "OTP ID" on the form). Its
  default in the test helper is `6fd92db0ae`.
- **Fast2SMS DLT route** is unchanged in behaviour and keeps its own request
  (`/dev/bulkV2`, `route=dlt`, `sender_id`, `message`). It never uses the OTP ID.
- The API key save path is unchanged: encrypted at rest, masked, preserved on
  re-save. Provider errors are sanitised, and logs carry no key, header, OTP
  or full phone.
- **Not verified live.** No real Fast2SMS call has been made from this
  environment: a live Test SMS needs a mobile number to send to, and the
  key and OTP ID need confirming on the provider side.
- Guard containment: `RestrictGuardAccess` returns 403 for Guard on
  `/notices*`. `NoticeIsolationTest` now asserts that, rather than 200.
- Regression: `tests/Feature/Fast2SmsSettingsTest.php` (23),
  `NoticeIsolationTest`, `GuardRoleContainmentTest`, `SettingSecretMaskingTest` green.

## Branch and commit

- Branch: `production-readiness` (`main` untouched at `2f65516`)
- Latest verified commit: `7926002` (this session's work is uncommitted in
  the working tree as of this update — see "Follow-up task (2026-09-18)")
- Working tree: modified (session 12 financial/expense work, not yet
  committed — `git` is not on PATH in this environment, so nothing was
  committed; commit from a shell that has it)

## Backend

- Laravel 10.50 / PHP 8.2; SQLite dev DB, 70 migrations applied, 0 pending
- `composer validate` clean; `route:list` 400 routes
- 31 accounts still use `password`/`password123` (rotation deliberately not run)
- Dev server for verification: `php artisan serve --port=8123`
- MariaDB 10.4.32 is bundled with this XAMPP install
  (`D:\xampp\mysql\bin\mysqld.exe`) but its `data\my.ini` hardcodes a stale
  `c:/xampp` path — start it with
  `--defaults-file=D:\xampp\mysql\bin\my.ini` or it fails to find its data
  directory.

## Web

- All 8 roles log in and land on the correct dashboard
- Guards now land on `/guard`, not the admin dashboard
- Browser-verified console-clean: Super Admin, Admin, Villa Owner, Tenant,
  Apartment Owner, Guard — see [PRODUCTION_QA_REPORT.md](PRODUCTION_QA_REPORT.md) §3
- **No open browser bugs** (the two long-standing ones were fixed in `6aa8512`)

## Flutter

- `flutter analyze`: 2 informational (intentional deprecations); `test` 24/24
- **Android dev API over USB works end to end** (2026-10-05). Run
  [`scripts/run_android_usb.ps1`](../scripts/run_android_usb.ps1): it checks
  adb, finds the device, sets `adb reverse tcp:8000 tcp:8000`, starts
  `php artisan serve --host=127.0.0.1 --port=8000` if nothing healthy is on
  the port, probes `/api/v1/health` from the phone, then launches the app
  with `--dart-define=API_BASE_URL=http://127.0.0.1:8000/api/v1`.
  The PC's LAN IP is never used, so no file is edited per machine.
  Login verified on a real device as `admin@societyflow.com`
  (Urban Heights) — dashboard, notices and complaints all loaded.
  `START-DEV.bat` / `start-server.ps1` still rewrite `devFallbackUrl` with
  the LAN IP; the USB script deliberately bypasses that path.
- Apache on `:80` serves the **web** app only. The **mobile API** is served
  by `artisan serve` on `:8000`; the phone reaches it through the USB
  tunnel, not over WiFi.
- **Guard QR scan verified on the real device** (2026-10-05): guard login →
  Guard Panel → SCAN QR → camera permission → scanner → token submitted →
  `ENTRY ALLOWED` → visitor row created → repeat scan `ALREADY INSIDE`.
  Optically scanning a QR off a screen is the one step that needs a human
  holding the phone; everything either side of the lens is covered.

## Gate / access-pass integrity

- **Duplicate check-ins are now impossible by construction.** `verify()`
  and `checkIn()` used to be two unsynchronised statements in both gates,
  so a double-tap or retry could insert two `entered` rows for one pass.
  `AccessPassService::verifyAndCheckIn()` / `checkInGuest()` now hold the
  pass (or guest) row with `lockForUpdate()` inside a transaction and
  re-read the open-visit state under that lock. Both
  `GuardController::scan` and `AccessPassApiController::scan` call them.
  Regression: `tests/Feature/AccessPassDuplicateEntryTest.php`.
- **Passes can now be edited and extended** (web + API + Flutter), which
  did not exist before: `AccessPassController` had only
  index/create/store/show/block/revoke. All three clients go through
  `app/Services/AccessPassEditService.php`, so the rules exist once.
  The **token is never reissued** on an edit or extend - a printed QR keeps
  working and the gate honours the new window on the very next scan.
  A **revoked pass is terminal**: every write path refuses it and the UI
  offers a new pass instead. An **expired** pass *is* editable - repairing
  it is the point. Changes are audited via `ActivityLog::recordFor()`
  (who/when/old/new, never the token).
  Regression: `tests/Feature/AccessPassEditExtendTest.php`,
  `AccessPassViewRenderTest.php`.
- **Property scoping / IDOR verified, not just reviewed.**
  `tests/Feature/AccessPassPropertyScopeTest.php` (13 tests) changes ids in
  URLs and request bodies across web *and* API and asserts the server
  refuses — list scoping, read, edit, extend, block, unblock, revoke,
  create-with-forged-`flat_id`, cross-society, and society-wide passes a
  resident must not manage. The existing implementation was already
  correct; it is now pinned.
- **Fixed: Super Admin saw an empty pass list.** `getSocietyId()` returns
  null for a Super Admin, so `bySociety(null)` matched `society_id IS NULL`
  — which no pass is. Scoping now exempts Super Admin explicitly
  (CLAUDE.md §3) on both the web and API list. Fails-closed bug, not a leak.
- **Fixed: saved provider credentials were rendered into the settings
  page.** They are encrypted at rest, but `SettingsController::index`
  decrypted them into `value="..."`; `type="password"` hides a value on
  screen only, and `instamojo_api_key` was `type="text"`. `app/Support/SettingSecrets.php`
  now masks them, and posting the mask back means "unchanged" so pressing
  Save without edits no longer overwrites live gateway secrets with
  asterisks. Regression: `tests/Feature/SettingsSecretMaskingTest.php`.
- The gate dashboard's **Inside counter** was bounded to today by
  `expected_entry_time` while the *Visitors Inside list* was not, so an
  overnight visitor was listed but not counted. The counter now matches
  the list. Regression: `tests/Feature/GuardDashboardCountersTest.php`.
- Release **APK 52.3 MB** / **AAB 42.3 MB** last built 2026-09-09; builds are
  **currently blocked** by an incomplete Android JDK (P0-9)
- **Not publishable**: `com.example.*` applicationId, debug-signed (P0-7, P0-8)

## Tests

- Backend: **442 passed, 1191 assertions** (2026-09-18, SQLite) — **442
  passed, 1187 assertions on MariaDB 10.4.32**, same date, same working tree
- Flutter: no `flutter` executable found in this environment (not the JDK
  from P0-9 — the SDK itself is absent), so `pub get`/`analyze`/`test`/
  `build` could not be executed. Last known-good: 21/21 (2026-09-09).

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

## Follow-up task (2026-09-16, session 2)

**The repository itself was broken on arrival.**
`.git/refs/heads/production-readiness` was 41 NUL bytes — a Windows crash
mid-ref-write. Git could not resolve HEAD, so `git log`/`git status` failed
and all 856 tracked files appeared staged. The commit objects were intact;
the ref was restored from the reflog to `7926002` and the working tree then
matched HEAD exactly, with nothing lost. `git fsck --connectivity-only`
passes (dangling blobs only). If this recurs, the tip is in
`.git/logs/refs/heads/<branch>`.

A 10-section brief asked for property-type UX, a property-context resolver,
Flutter parity, a security audit and a full QA pass in one go. Audited first:
the property-type **foundation already exists** (`PropertyType` /
`SocietyPropertyType` enums, `Society` helpers, `ResolvesSocietyPropertyType`,
and `partials/property-type-field.blade.php`, which already hides a
one-option dropdown exactly as the brief describes). §7's
`ComprehensiveEmailTemplateSeeder` is already gone with zero remaining
references — nothing to do. Scoped, with the user's agreement, to the
security defects that audit turned up, done completely and proven:

- **Cross-society IDOR on payment writes (real, exploited in a test).**
  `PaymentController::store()` and `update()` gated the society check behind
  `if ($user->hasRole('Admin') && ...)`. Every role the condition does not
  name skipped it. Proven before fixing: a resident of society A posting
  society B's `flat_id` **wrote a payment row into society B**. Both now call
  `authorizeSocietyAccess($flat)`. A `HttpException` re-throw was added to
  both methods because the surrounding `catch (\Exception)` would otherwise
  swallow the 403 into a generic "an error occurred" redirect.
- **Payment list and totals leaked to Guard and Accountant.**
  `index()`'s role chain named Admin/Tenant/Villa Owner/Apartment Owner and
  fell through to "Super Admin sees all", so Guard and Accountant listed every
  payment in every society. `RestrictStaffAccess` masked this for Staff only —
  its first guard returns early for every role that is not Staff. Verified by
  probe (Guard in society A saw society B's ₹9,999.00), then scoped by default
  on both the list query and the statistics query.
- **`NoticeController::show()` leaked every notice to every role.** Unlike
  edit/update/destroy/approve/reject, `show()` has no
  `authorize('manage_notices')` gate, so the same `&&` let Tenant, Apartment
  Owner, Villa Owner, Guard and Accountant read any notice in any society by
  id. All five confirmed returning 200 before the fix. The other five sites in
  that controller were hardened too — not exploitable today, but they depend
  on the permission map never granting `manage_notices` to another role.
- **Owners holding both a villa and an apartment could not record a payment
  at all.** `PaymentController::create()` set `$propertyType = 'mixed'`, a
  display label that is not a `PropertyType`; the view submitted it as a
  *hidden* field and `store()` validated `in:apartment,villa`, so the form
  failed with no field the owner could correct. The label stays for display,
  the bogus value is no longer submitted, and the selected flat is now the
  authority (brief §6: never trust a browser-submitted property type).

Each fix was proven by reverting it and watching the new test fail — the
resident-IDOR test fails on `assertDatabaseMissing`, not merely on a status
code, so it distinguishes "blocked with a 302" from "row written".

Tests: 17 new — `PaymentCrossSocietyWriteTest` (6),
`PaymentListingScopeTest` (4), `NoticeShowIsolationTest` (7). Full suite:
**413 passing / 1119 assertions** (SQLite), up from 396 with no regressions.
`php -l` clean across all tracked PHP, `view:cache` compiles every Blade
template, `route:list` resolves 399 routes.

**Not run, not claimed:** MySQL (no server in this environment), Flutter
(`analyze`/`test`/`build` — Android JDK still broken, P0-9), and browser
click-through (Chromium crashes here, as in the previous session).

## Follow-up task (2026-09-18)

A "property context & UX simplification" brief asked to stop asking for
Property Type when the society already determines it, close the backend
trust gaps a hidden dropdown doesn't fix, redesign Rent/Payment to be
tenant-/bill-first, consolidate the four assignment screens into one entry
point, and check Flutter parity. Audited first (per this file's own
"Next recommended task" from 2026-09-16) rather than assumed:

- **All six forms converted** to `partials/property-type-field.blade.php`,
  which already implements "hide the select and show a read-only line for a
  single-type society" (`payments/create`, `admin/rents/create`,
  `admin/tenants/create`, `facility-bookings/create`, `residents/create`,
  `utility-bills/index`'s drawer). Each view's unit-loading JS was rewired to
  fire from the component's own `sfOnChange` hook instead of a `change` event
  that never fires on a hidden field.
- **`RentController::getUnitsOnRent`** used to hard-reject a request with no
  `property_type` — exactly what a single-type society's page now sends. It
  resolves the society's one supported type server-side instead.
- **Two real IDORs found and fixed while closing the "hidden dropdown isn't
  enough" gap**: `FacilityBookingController::store()` and
  `PaymentController::store()` checked the submitted `flat_id` belonged to
  the *society* but not to the *requester* — a same-society resident could
  name a neighbour's `flat_id` and book a facility or record a payment
  against it. Both now also check `UserFlatResolver::userHasFlat()` unless
  the actor holds `manage_facilities` / the `Admin`/`Super Admin` role.
- **Payment is now bill-first where a bill exists.** New
  `GET /api/payments/outstanding-bills` lists a flat's unpaid/partial
  `maintenance_bills`; picking one fills bill type, amount and due date.
  `store()` resolves `bill_type`/`due_date` from the bill server-side (never
  from the submitted fields) and rejects a bill that doesn't belong to the
  flat being paid against. Reuses the existing `maintenance_bills` table and
  `payments.maintenance_bill_id` column — no new migration. An ad-hoc payment
  with no matching bill still falls back to manual entry.
- **Rent was already close to tenant-first** (`getUnitsOnRent` already
  returns `tenant_id`/`tenant_name`/`monthly_rent` per unit and the view
  auto-fills them on selection) — the only gap was the property-type
  question itself, fixed above.
- **New `Property & Occupancy` hub** (`admin.property-occupancy.index`) is
  now the sidebar's one entry point for assign/transfer/move-in/move-out,
  gated to the sections the society's property type actually supports. The
  three underlying screens (`apartment-assignments`, `villa-assignments`,
  `tenant-assignments`) were **not** rewritten or removed — they're already
  tested, correct, and independently reachable; PropertyOccupancyController's
  docblock explains why a rebuild was rejected.
- **Flutter already matches the brief's principle.** `SocietyConfig`/
  `SocietyPropertyType` (property-context resolver) and `propertyNoun` exist
  and are consumed; facility booking is already "Facility → Date → Time →
  Purpose → Book" with no property-type step; complaints/services have no
  property-type dropdown either. No Dart changes were needed.
- Found but **not fixed** (out of scope for this pass, flagged for the next
  one): `residents/create.blade.php` tells the admin a login will be created
  with a default password, but `ResidentController::store()` never creates a
  `User` — that controller only writes a `Resident` row (its own docblock
  documents the screen as orphaned/legacy). The copy is stale, not something
  this pass's changes touched.
- Cleaned up two pre-existing `Log::info` per-request dumps found while
  editing their surrounding code (`RentController::getUnitsOnRent`,
  `TenantController::store` — the latter had been logging the plaintext
  submitted password), per CLAUDE.md §7.

Tests: 18 new (`PropertyContextUxTest`). Full suite: **437 passing / 1178
assertions** (SQLite), up from 413 with no regressions. `php -l` clean on
every file touched, `view:cache` compiles every Blade template, `route:list`
resolves 401 routes (+2: `admin.property-occupancy.index`,
`api.payments.outstanding-bills`).

**Not run, not claimed:** MySQL (no server in this environment), Flutter
`analyze`/`test` (no `flutter` executable in this environment at all, not
only the JDK), Flutter build (still blocked, P0-9), and browser click-through
(no browser automation set up in this environment).

## Follow-up task (2026-09-18, session 2)

A final cleanup pass before calling the property-context work production-
ready, scoped explicitly to fixing what's listed (no new features):

- **Resident creation — a real bug, not just misleading copy.**
  `residents/create.blade.php` promised "A user account will be created
  automatically with the default password: password", but
  `ResidentController::store()` only ever writes a `Resident` row. Auditing
  *why* found something worse than stale text: `Route::redirect('/residents',
  '/owners')` registers with `any()`, matching every HTTP verb, and it was
  intercepting **POST /residents too** — every Add Resident submission
  silently redirected to `/owners` without saving anything. The form has
  never been able to create a resident. Fixed by excluding `index` from the
  resident resource and giving the redirect its own GET-only route named
  `residents.index` (so `route('residents.index')` still resolves, and the
  documented "GET /residents redirects to /owners" behaviour is unchanged
  while POST/PUT/DELETE reach the controller again). The misleading login
  copy was replaced with an accurate note pointing at Add Owner/Add Tenant,
  which do create both the record and the login together. No new
  authentication path was added, per the brief's explicit instruction.
- **Property assignment navigation de-duplicated.** The three "Owner/Tenant
  Assignments" links nested inside the Apartments/Villas/Tenant sidebar
  groups were removed - they had become a second path to the exact screens
  the Property & Occupancy hub (added the prior session) already links to.
  The underlying routes/controllers are untouched and still directly
  reachable; only the duplicate sidebar entries are gone.
  `PropertyTypeNavigationTest`'s assignment-link assertions were rewritten
  (not deleted) to check the new, intended state - the old assertions
  encoded the pre-hub design the brief asked to retire.
- **Property Type final audit**, every occurrence in `resources/views`
  checked individually: the six converted views plus the hub stay correct;
  `owners/create`/`owners/edit` keep their own deliberately-separate
  single-type-hiding logic (Super Admin's live society switch, and a
  since-unsupported stored value needing to stay visible - both predate this
  session and are already tested); `auth/register.blade.php` and
  `super-admin/societies/*` set a **society's** type and are the legitimate
  source of that decision, not a per-form question; every other hit is
  read-only display logic (an icon or label keyed off an already-known
  unit's type). No further changes were needed.
- **MySQL/MariaDB actually run this time.** The XAMPP install bundles
  MariaDB 10.4.32, unstarted; its `data\my.ini` hardcodes a stale `c:/xampp`
  path, which is why it wouldn't start on the default invocation (documented
  above). Started with `--defaults-file`, full suite run against it: **442
  passing, 1187 assertions** - one test's own fixture (`tenants.move_in_date`,
  the pre-existing P1-19 SQLite/MariaDB nullability gap) needed a value
  supplied to pass on both engines; the application code needed no change.
- **Flutter: searched properly, not just re-asserted from memory.** No
  `flutter`/`dart` executable anywhere on `PATH`, in env vars, or found by a
  depth-4 search of `C:\` and `D:\` - the SDK itself is not installed in this
  environment, so `pub get`/`analyze`/`test`/`build apk --debug` could not be
  run. This is a harder blocker than P0-9's broken JDK. Not claimed as
  verified.
- **Browser automation: also actually checked, not assumed absent.** No
  `chromium-cli`, no `npx`/Node, no Playwright cache in this environment -
  confirmed unavailable, not just "known to crash" as prior sessions found.
  In its place: the six workflows across all three society configurations
  (Apartment-only via `voltforce.io@gmail.com` / Mahalaxmi Enclave,
  Villa-only via `bookmizoapps@gmail.com` / Royal Eden, Mixed via
  `admin@societyflow.com` / Urban Heights Residency) were driven live against
  the running dev server (`php artisan serve --port=8123`) with real login,
  real session cookies and the real dev database - confirmed 200 responses
  and the expected hidden-dropdown/read-only-line or dropdown-shown behaviour
  in every case. That is real HTTP-level functional verification against a
  live server, not a code review - but it is not visual/interactive browser
  testing, and this file does not claim it is.

Tests: 4 new (`test_residents_create_does_not_promise_a_login_account`,
`test_resident_store_creates_no_user_account`,
`test_residents_index_still_redirects_to_owners`,
`test_property_occupancy_hub_is_linked_from_the_sidebar` was already added
last session); one existing test rewritten
(`PropertyTypeNavigationTest::test_assignment_links_are_reachable_only_via_the_occupancy_hub`,
formerly `test_assignment_links_follow_the_same_property_type_gate`). Full
suite: **442 passing / 1191 assertions (SQLite)**, **442 passing / 1187
assertions (MariaDB 10.4.32)**. `php -l` clean, `view:cache` compiles,
`route:list` resolves 400 routes.

## Follow-up task (2026-09-18, session 3 — "final master production pass")

A whole-application audit request ("stop giving separate prompts for UX/
roles/guard/billing/etc, audit everything in one pass"). Given the real size
of that ask versus one session, triaged explicitly rather than attempting
everything shallowly — see the chat transcript for the full 41-section
report. What was actually audited **and fixed**, verified, and tested:

- **Critical: Super Admin privilege escalation, closed.**
  `UserController::create()`/`edit()` rendered `Role::all()` unfiltered
  regardless of viewer, and `store()`/`update()` validated the submitted
  `role` only against `exists:roles,name` — so an Admin (society-level) could
  both see "Super Admin" in the dropdown and successfully `POST`/`PUT`
  `role=Super Admin`, for a new account or an existing user in their own
  society, granting platform-wide authority. Fixed with one
  `assignableRoles()` helper (Super Admin unrestricted, everyone else never
  offered or able to submit Super Admin), applied to both the create and
  edit forms and both write endpoints, including the two inline modals on
  `users/index.blade.php` that post to the same routes. No API surface
  duplicates this — confirmed no `assignRole`/`syncRoles` call exists under
  `Api/`. 5 new tests (`UserManagementTest`).
- **Safe project-size cleanup, ~115 KB removed.** Ten files with zero
  references anywhere in the repo (checked by full-repo grep before
  deleting): two dead sidebar partials (`sidebar-old-cached.blade.php`,
  `sidebar.blade.php.old`) and seven JS files plus their own verification
  command (`instant-crud-handler.js`, `universal-instant-refresh.js`,
  `instant-table-refresh.js`, `instant-visitor-refresh.js`,
  `users-crud.js`, `ajax-form-handler.js`, `form-submission-handler.js`,
  `VerifyInstantCrudSetup.php`) — an abandoned earlier attempt at the same
  "instant update" feature a prior session found and left alone; now
  confirmed genuinely dead and removed. `route:list` (400 routes, unchanged),
  `view:cache` and `composer dump-autoload -o` all still clean afterward.
  Left alone deliberately: `InstallController`/`Admin/FirebaseSettingsController`/
  `SuperAdmin/SuperAdminController`, already flagged in `TODO.md` (P2-9) as
  needing the owner's confirmation before removal — that flag predates this
  session and this pass didn't override it; root `.md`/`.txt` status files
  and `composer.phar`, for the same reason (P3-4).
- **Regression-confirmed** (not re-fixed, since prior sessions' fixes still
  hold): property-context UX, resident creation, guard gate scoping
  (`GuardGateTest`), API content visibility, auth security, and query
  performance (`QueryPerformanceTest` — the N+1 audit this pass would have
  redone already exists and passes). Full suite: **446 passing, 1205
  assertions** (SQLite) — MariaDB not re-run this pass since nothing
  schema-adjacent changed; the prior session's MariaDB pass (442/1187) still
  applies to that surface.
- **Explicitly NOT attempted this pass — audited and documented instead**,
  each because it is a genuine "major architectural decision not supported
  by existing code" (the brief's own exception clause), not because it was
  skipped for convenience:
  - **Villa Owner / Apartment Owner → canonical "Owner" role.** 16
    controllers and 12 views reference these two role names for real
    authorization decisions, not just display. A safe merge needs a
    migration plan (add "Owner" + a property-type relationship, backfill,
    update every `hasRole()`/`hasAnyRole()` call site, update Flutter,
    re-test authorization end to end) that is its own project, not a
    same-pass addition to everything else here. Recommendation: keep the
    two DB roles, but stop *displaying* "Villa Owner"/"Apartment Owner" as
    if they were the product's mental model anywhere new — this pass
    introduced no new UI following that pattern, but did not retrofit the
    ~12 existing views either.
  - **Recurring-staff QR passes, temporary service-provider passes, patrol
    checkpoints, offline guard sync.** None of these have any existing data
    model to extend — `ServiceProvider` is a society-level service
    marketplace listing with its own attendance clock-in/out, not a
    gate-access pass tied to a specific flat with allowed days/times. Each
    of the four is a genuine net-new subsystem (new tables, new guard UI,
    new Flutter screens, revocable-token security review) that deserves its
    own scoped pass with real time for security review, not a rushed
    addition inside a "final cleanup." The existing guard/visitor flow
    (`GuardController`, `GuardGateTest`) was re-verified working, not
    rebuilt.
  - **Full dependency audit / asset optimization / `Route::redirect` sweep
    elsewhere.** The one `Route::redirect()` bug found (residents, session
    2) was found by tracing a specific reported symptom; a systematic sweep
    for the same `any()`-matches-every-verb pattern elsewhere in
    `routes/web.php` was not performed this pass and is a reasonable next
    step given what it turned up once.

Tests: 5 new (`UserManagementTest`). Full suite: **446 passing / 1205
assertions (SQLite)**. `php -l` clean, `view:cache` compiles, `route:list`
resolves 400 routes (unchanged — this pass added no routes), `composer
dump-autoload -o` clean.

## Follow-up task (2026-09-18, session 4 — "guard QR / access pass")

Built the recurring-staff / temporary-service-provider gate QR system the
prior session's report scoped out as a major architectural decision, per an
explicit follow-up request with a concrete minimal schema
(`access_passes`). Reused rather than duplicated wherever the existing
architecture allowed it:

- **New `access_passes` table** (recurring-staff and temporary-service
  passes) — `AccessPass` model, `AccessPassService` (server-side scan
  verification: society/revoked/validity/blocked-today/day/time/duplicate-
  entry, all re-resolved from the DB, nothing trusted from the QR itself),
  `AccessPassController` (resident/Admin create, list, show with QR,
  Block Today, Revoke).
- **Scanning reuses the existing `visitors` table**, not a parallel one —
  a valid property-scoped scan creates an ordinary visitor row
  (`access_pass_id` FK added), so check-in, "Visitors Inside", check-out
  and history all work unmodified. New `guard.scan`/`guard.scan.submit`
  routes; `GuardController::scan()` is one endpoint for both an AccessPass
  token and a pre-approved guest's token (see next point).
- **Guest QR reuses the existing visitor-invitation/approval flow** exactly
  as asked ("do not build a second pass system for this") — a `qr_token` is
  generated automatically the moment any visitor's `approval_status`
  becomes `allowed` (one model event on `Visitor`, covers every existing
  approval path: the villa/apartment-owner self-registration flow, the
  general `allow()` action, and the mobile API's `allow()`/`deny()`), not a
  new approval mechanism.
- **Society-wide staff (Part 7/8 of the brief) added as a distinct
  `access_scope`.** A common-area cleaner or the society's own electrician
  has no single flat, and `visitors.flat_id` is `NOT NULL` and read
  unguarded (`$visitor->flat->building->name`, no `?->`) by enough views,
  an email template and the mobile API formatter that widening it safely
  would have meant auditing and null-guarding all of them - judged too
  large and risky a change for this pass. Society-wide check-in/out is
  tracked directly on the pass instead (`currently_inside`,
  `last_entry_at`/`last_exit_at`); the same QR scan toggles them in then
  out, so no "Visitors Inside" browse is needed for this case. Documented
  as a deliberate, bounded design choice, not a shortcut taken silently.
  Only Admin/Super Admin can create a society-wide pass (a resident
  authorising their own household help has no business granting
  society-wide access) — enforced server-side, not just hidden in the form.
- **Found and fixed a real, previously-undiscovered bug while wiring
  `addLog()` for the new scan flow**: `visitor_logs.action` was created as
  `enum('created','approved','rejected','entry','exit','updated')`, but
  `Visitor::allow()`/`deny()` (used by the mobile API's approve/deny
  endpoints) log `'allowed'`/`'denied'` - neither ever accepted by the
  column. SQLite stores enum as TEXT with no real constraint so this never
  surfaced there; on MySQL/MariaDB, approving or denying a visitor from the
  mobile app would fail outright. Widened via the same rebuild-vs-MODIFY
  pattern as the existing `repair_visitors_table` migration, existing rows
  normalised (`approved`→`allowed`, `rejected`→`denied`), confirmed on
  MariaDB.
- **Found and fixed a second real, pre-existing bug**:
  `visitors/show.blade.php`'s Check-In button checked
  `approval_status === 'approved'`, a value that column has never held
  (the model and every controller use `'allowed'`) - the button has never
  once appeared. Two matching dead color-map keys in the same file fixed
  alongside it.
- **Guard UI**: "Scan QR" is now the primary action on the guard dashboard
  and bottom nav (was "Register Visitor"); the scan page attempts a real
  camera scan via a CDN-hosted `jsQR` decoder with a manual-code-entry
  field always available underneath (works with a physical USB/Bluetooth
  QR scanner too, which just "types" into that field). **Camera scanning
  could not be verified in this environment** - no browser/device
  available - the server-side verification it calls is fully tested
  (26 `AccessPassTest` cases), the client-side decode loop is not.
- **Not attempted, documented instead**: a native mobile API endpoint for
  guard scanning (Flutter currently has no guard experience at all — the
  web `layouts/guard.blade.php` is already mobile-first and is what guards
  actually use), staff-attendance auto-creation from a scan (the existing
  `ServiceAttendance`/`ServiceProvider` model is a society-hired-provider
  marketplace concept, not a household-staff gate-access one — wiring them
  together would misuse that data model), patrol-checkpoint QR, offline
  guard operation, and a scan-triggered notification (no "visitor arrived"
  event/listener exists yet to hook into — only `VisitorApproved` does).

**A real bug survived all 26 tests and was only found by an actual live
click-through against the running dev server**: `AccessPassService::checkIn()`
passed a null `person_phone` straight through to `visitors.visitor_phone`,
which is `NOT NULL` - every test fixture in `AccessPassTest` happened to
supply a phone number, so this never failed until a pass was created through
the real form, where the phone field is (correctly) optional. Fixed with a
`?? ''` fallback, and a 27th test now pins a phone-less pass specifically.
Recorded here deliberately, not glossed over: automated tests passing is not
the same claim as "verified working" - see CLAUDE.md's own testing
philosophy - and this is the concrete case that proves why the live
click-through mattered.

Live-verified end to end against the running server (not just PHPUnit):
logged in as Admin, created a real pass through `/access-passes/create`
with no phone number; logged in as a real Guard account
(`guard@societyflow.com`), scanned the actual generated token via
`POST /guard/scan` - entry allowed, "Inside" counter incremented, the
person appeared on the guard dashboard; scanned the same token again -
correctly rejected as "Already checked in"; checked out through the
existing `/guard/visitors/{id}/check-out` endpoint - the person disappeared
from "Inside". This is the full guard journey Part 51 of the brief names,
actually driven end to end, not code-reviewed.

Tests: 27 new (`AccessPassTest`), 1 existing test's assertion updated
(`GuardGateTest` — the dashboard's primary button text changed from
"Register Visitor" to "Scan QR" intentionally). Full suite: **472 passing /
1265 assertions (SQLite)**, **472 passing / 1261 assertions (MariaDB
10.4.32)** — the enum-widening/table-rebuild migrations (`access_scope`,
nullable-`flat_id`-then-not, `visitor_logs.action`) proven on both engines,
not just SQLite.

## Follow-up task (2026-09-19, session 5 — "guard mobile completion")

Closed the specific gap the prior session's report named: Flutter had no way
to scan an Access Pass QR. **Correction to that report first**: it claimed
"Flutter has zero Guard screens today" — false. `lib/features/guard/
screens/guard_home_screen.dart` already exists, already gets routed to
Guard/Staff by `main_shell.dart`, and already has a stats grid, quick
actions and an emergency-contacts sheet. What was actually missing was
narrower: a QR scanner. Audited the real architecture before writing
anything (Riverpod, `ApiService`/`HttpService`, `main_shell.dart`'s
role branch, `visitors_provider.dart`'s API conventions) and reused all of
it — no second auth system, no second HTTP client, no second visitor
system.

- **New mobile API endpoint**: `POST /api/v1/access-passes/scan`
  (`Api/AccessPassApiController`), gated to `Guard|Admin|Super Admin`,
  delegating entirely to the same `AccessPassService` the web gate uses —
  one verification implementation behind both. Reused the guest-QR lookup
  (`visitors.qr_token`) and the society-wide toggle-checkout logic from
  `GuardController::scan()` rather than re-deriving them.
- **New Flutter screen**: `GuardScanScreen` — camera scan via `mobile_scanner`
  (the one new dependency added; camera permission already existed in
  `AndroidManifest.xml` from `image_picker`, `NSCameraUsageDescription`
  added to `Info.plist` since nothing had ever requested it there) with a
  manual code-entry field always available underneath, same fallback
  pattern as the web gate's `guard/scan.blade.php`. Wired in as a large,
  dedicated "SCAN QR" button on `GuardHomeScreen`, above the existing quick
  actions rather than replacing them.
- **Not touched**: `AccessPassService`, `AccessPassController`, property
  context, billing, payments, visitor approval — no bug surfaced in any of
  them by this integration, so none were modified, per this session's own
  explicit instruction.

**Live-verified end to end against the running dev server** (not just
PHPUnit): logged into the mobile API as the real Guard account, created a
real pass via the admin web UI, scanned its actual token through
`POST /api/v1/access-passes/scan` with a real Sanctum bearer token — entry
allowed; scanned the same token again — correctly rejected as "Already
checked in - check out first." Both engines: **482 passing / 1287
assertions (SQLite)**; the 36 AccessPass-related tests re-run and passing
on **MariaDB 10.4.32** too (full suite not re-run there since nothing
schema-related changed this session — the prior session's full 472/1261
MariaDB run still covers the rest).

**Flutter: NOT VERIFIED.** No `flutter`/`dart` executable in this
environment (checked again) — `pub get`, `analyze`, `test`, and both APK
builds could not be run. `GuardScanScreen`, the new provider, and the
`models_test.dart` additions were written carefully and reviewed by hand
(no unmatched braces, `ApiResult`/`ScanOutcome` parsing traced by hand
against the API controller's actual JSON shape), but **this is not a
substitute for the compiler** — the `mobile_scanner` `errorBuilder`
callback signature in particular varies across that package's own
versions and was not checked against the actual installed version.
**The first action in any environment with the Flutter SDK must be
`flutter pub get && flutter analyze`**, before trusting this screen
compiles. No physical Android device was available either — **PHYSICAL
DEVICE = NOT VERIFIED.**

Tests: 9 new (`AccessPassApiTest`) + 3 new (`ScanOutcome` parsing cases in
`models_test.dart`, not run — see above). Backend full suite: 482 passing,
up from 473, zero regressions.

## Follow-up task (2026-09-19, session 6 — "Flutter/Android verification only")

Flutter SDK confirmed absent again (PATH, env vars, and a depth-5 filesystem
search of C:\ and D:\ for `flutter.bat`/`dart.exe` — none found), so
`pub get`/`analyze`/`test`/APK builds still could not be run directly. Given
that, verified the one thing the prior session explicitly flagged as
unchecked — the `mobile_scanner` API surface — against the package's real
published source instead of guessing, using `WebFetch` (newly available
this session) against pub.dev and the package's GitHub repo at the exact
`v5.2.3` tag.

- **Found and fixed a real compile error.** `MobileScannerErrorBuilder` in
  5.2.3 is `Widget Function(BuildContext, MobileScannerException, Widget?)`
  - three positional parameters - confirmed against the installed version's
    own API docs. `guard_scan_screen.dart`'s `errorBuilder` was written with
  two (`(context, error) => ...`), which would not have compiled. Fixed to
  three params (`(context, error, child) => ...`).
- **Confirmed correct, not just assumed**: `onDetect: void Function
  (BarcodeCapture)?` (present and required-shape in 5.2.3, despite being
  removed in an intermediate 5.0.0-beta and restored in 5.1.0 - version-
  specific, checked precisely rather than trusting a general summary),
  `Barcode.rawValue` → `String?`, `BarcodeCapture.barcodes` → `List<Barcode>`,
  `MobileScannerController.toggleTorch()`/`.dispose()`, and
  `DetectionSpeed.noDuplicates` — all match what `guard_scan_screen.dart`
  already called.
- **Found and fixed a real dependency-constraint gap.** `mobile_scanner`
  5.2.3's own `pubspec.yaml` requires Dart `>=3.4.0` and Flutter `>=3.22.0`;
  this project declared only `>=3.0.0` with no Flutter floor at all. Raised
  both in `societyflowmob/pubspec.yaml` to the true requirement - leaving
  the old floor would have produced a confusing version-solving failure on
  an older toolchain instead of a clear one.
- Manually re-traced every other new/changed Dart file
  (`guard_home_screen.dart`'s new button, `access_pass_provider.dart`'s
  `ApiResult`/`ScanOutcome` parsing, the `models_test.dart` additions)
  against the actual `ApiService`/`HttpService` code already in the repo -
  no further issues found. No unused imports, no debug prints, no dummy
  methods, no hardcoded responses.

**None of this replaces an actual `flutter pub get && flutter analyze`.**
It is real verification against the real published package source - not
a guess, and two genuine bugs came out of it - but it is not a compiler
run, and is reported as such.

Backend: untouched this session (no PHP files changed) - full suite re-run
anyway per the explicit instruction: **482 passing / 1287 assertions**,
identical to the prior session, confirming no regression.

## Follow-up task (2026-09-20, session 7 — "Accountant navigation/dashboard")

A whole-application "final master audit" brief was received; given the scale
(39 sections covering every role/module in one prompt) and that six prior
sessions already worked through nearly all of it (see the sessions above and
`docs/TODO.md`), this pass scoped to the one concrete, still-open defect the
brief itself called out by name: the Accountant role's sidebar showed
nothing but "Dashboard." Verified first, not assumed:

- **Confirmed the root cause**: `layouts/app.blade.php` defined
  `$isAdmin`/`$isOwner`/`$isTenant`/`$isStaff` but no `$isAccountant` at
  all — every nav item gated on those four booleans, so Accountant matched
  none of them. `DashboardController::index()` had no `hasRole('Accountant')`
  branch either, so it silently fell through to the full Admin dashboard
  (building/resident/ticket counts that aren't an Accountant's business),
  while `partials/mobile-menu.blade.php` (Flutter's web-mobile menu) turned
  out to *already* have `$isAccountant` wired for Bills, but not Reports or
  Rent — a partial, inconsistent fix from some earlier point.
- **New `accountantDashboard()`** (`DashboardController.php`) + new
  `accountant/dashboard.blade.php` view: monthly collection, pending dues,
  bill counts, active rent-tenant count, recent payments, pending bills —
  all scoped to the Accountant's own `society_id`, reusing the same
  `Payment`/`MaintenanceBill` queries `adminDashboard()` already uses (no
  new query patterns invented). Empty states say "No payments recorded yet"
  rather than showing zero from a wrong query.
- **Sidebar + mobile menu**: added Bills & Payments, Utility Bills, Rent
  (read-only), and Finance Reports for Accountant in both
  `layouts/app.blade.php` and `partials/mobile-menu.blade.php` (the latter
  also gained Owner/Accountant to the Reports section it was missing).
- **Closed the route/controller gaps a nav link alone doesn't fix** (menu
  items must be usable, not just visible, per this session's own brief):
  - `admin.rents.index`/`.receipt` were bundled with Admin-only rent
    *writes* under one `role:Super Admin|Admin` group — split into a
    separate `role:...|Accountant` group for the two read routes only;
    `RentController::index()`/`receipt()` had their own internal
    `authorizeRole('Super Admin','Admin')` call that needed the same
    addition, or the route-level grant would have been moot.
    `admin/rents/index.blade.php`'s Create/Edit/Delete/Add-Payment
    actions are now hidden for Accountant (they'd otherwise link to routes
    that still correctly 403 them).
  - `UtilityBillController::index()` scoped non-Admin/Super-Admin users to
    *their own flats* via `UserFlatResolver` — correct for a resident, wrong
    for Accountant (who has no flat and would see zero bills, not the
    society's). Added to the same "sees all society bills" branch as Admin.
- **Found and fixed a real, independent bug while auditing the Reports
  routes for Accountant access**: both the `reports.*` route middleware
  (`role:Super Admin|Admin|Owner|Tenant`) and
  `ReportController`'s own internal checks used `hasRole('Owner')` /
  `role:...|Owner|...` — **'Owner' is not a role this application has**
  (CLAUDE.md §2 names this exact anti-pattern). Every real Villa
  Owner/Apartment Owner was silently blocked from Financial and Maintenance
  Reports before this session, and the same dead check gated
  `getFinancialData()`/`getMaintenanceData()`'s per-owner scoping, so any
  owner who somehow reached those methods would have seen the whole
  society's figures, not just their own unit. Fixed in both the route
  middleware and the controller (3 call sites) to
  `hasAnyRole(['Villa Owner', 'Apartment Owner'])`; Accountant added
  alongside it for the financial report only (a dedicated middleware group,
  since Accountant has no property to see a *maintenance* report about).
- **Not touched**: nothing else in the 39-section brief was modified this
  session — the other sections' items were already covered by prior
  sessions (see `docs/TODO.md`'s `done` rows) or remain correctly scoped out
  as open/blocked (Flutter SDK absence, Android JDK, payment gateways).
  Re-litigating already-verified work was deliberately avoided per this
  file's own accumulated audit trail.

Tests: 7 new (`AccountantAccessTest`), covering the dashboard view, nav
visibility, route access, negative authorization (users/settings/super-admin
403, rent create 403), society-scoped dashboard data, and the Owner-role
regression. Full suite run to completion after this session's changes:
**489 passed, 1306 assertions (SQLite)** — up from 482/1287, zero failures,
zero regressions. (MySQL/MariaDB not re-run this session since nothing
schema-related changed; the prior session's MariaDB pass still covers that
surface.)

## Follow-up task (2026-09-22, session 8 — "Role permission matrix + Settings consolidation")

Two related UI/UX passes, explicitly scoped to **no role-architecture
changes** (the user rejected an earlier proposal to merge Villa
Owner/Apartment Owner into a canonical "Owner" role after seeing the real
blast radius - 31+ `app/` call sites, not the 16 previously documented -
and asked instead to make the product fully work under the roles that
already exist). Both passes are UI/data-storage work only; no role was
added, renamed or reassigned, and no user/property/financial record was
touched.

**Role & Module Permissions matrix** (`settings/index.blade.php`'s
Permissions tab):
- Replaced the one-giant-card-per-module layout with a compact, categorized
  matrix (Property & People / Operations / Finance / Reports / Settings),
  Alpine-driven: search, enabled/disabled filter, per-role filter,
  expand/collapse per category, a compact role guide, a legend, and a
  sticky unsaved-changes bar with Discard/Save and a `beforeunload` guard.
  Saves via fetch to the existing `settings.permissions.update` route (now
  also returns JSON when asked) instead of a full-page POST.
- Kept the exact 7 existing roles (Admin, Villa Owner, Apartment Owner,
  Tenant, Staff, Accountant, Guard) - Accountant and Guard were missing
  from the role list entirely before this, so their modules had no
  configurable row at all.
- **Found and fixed two real, pre-existing bugs**: (1) the Blade read
  `$settings['permissions']` directly, but `SystemSetting::set()` stores
  under a nested `module_permissions` key - a saved toggle could never
  re-render as checked, for any role, ever. (2) The JS seeding logic used
  `!!value`, and classic form POSTs store the literal string `"1"` (Laravel's
  `boolean` validation rule accepts it without casting) - `!!"0"` is `true`
  in JavaScript, a latent mis-read risk. Both fixed and tested.
- **Data-safety incident, disclosed rather than hidden**: this session's own
  persistence testing (partial JSON payloads against the full-replace
  `SystemSetting::set()`) overwrote the previously-saved `module_permissions`
  row for society 1. Checked the oldest available backup
  (`database.sqlite.pre-property-type`, 2026-09-09) for a recoverable prior
  value - no such row existed there either, so nothing verifiably "real" was
  lost. Reset to a clean empty state rather than fabricate a guessed
  configuration or restore a 2-week-stale backup (which would have cost far
  more than it recovered). This setting has zero functional effect on the
  running app either way (confirmed zero callers of
  `PermissionHelper::canAccessModule()` anywhere in the codebase) - flagged
  as a genuine finding for whoever owns this data, not glossed over.
- Real enforcement of these toggles (wiring them into the sidebar, routes,
  controllers, API and Flutter) was explicitly **not** attempted - the user
  scoped this out after being told it's currently 100% cosmetic, given the
  size of that work relative to everything else. The page now says so
  honestly instead of implying it does something it doesn't.

**Settings navigation consolidation** (same file): 12 flat top-level tabs
(Society Info, App Settings, Theme, Currency & Features, Role Permissions,
Complaint Categories, Email Settings, Email Templates, Payment Gateway,
Push Notifications, SMS/OTP, Security) forced horizontal scrolling to find
anything. Consolidated into 6 sections - General, Appearance, Modules &
Access, Communication, Payments, Security - each showing a second-row
sub-tab bar only when it holds more than one item. Every original
`activeTab` key, its content block, its `<form>` and its POST route are
**unchanged** - this was a navigation-only restructuring, verified by
requesting all 11 real sub-tabs directly (`?tab=...`) and confirming 200 on
each, and that a bookmarked sub-tab link still opens on the correct parent
section.
- **Push Notifications removed from the menu, backend left untouched, verified
  before removing anything**: `SettingsController::updatePush()` writes to a
  `SystemSetting` group (`push`) that has zero readers anywhere in the
  codebase - it's a dead duplicate of the *real* push configuration, which
  lives in the `PushNotificationSetting` model/table and is read by
  `PushNotificationService`/`PushSubscriptionController`, configured through
  the separate, already-existing `Admin\PushNotificationController` pages
  (push templates, `/admin/push-notifications/*`). Only the Settings tab
  button was removed; the route, controller method and content block still
  exist untouched (no 404 for a stale bookmark), and the real push
  infrastructure was never touched.
- Email Templates: converted from a card grid to a compact searchable table
  (name/subject/status/type/Edit), same routes
  (`admin.email-templates.*`), same "Create Template"/"Initialize Defaults"
  actions.
- Email Settings: visually grouped into Mail Configuration / Sender
  Information / Testing with section headers - same fields, same
  `settings.email.update` endpoint, no validation change.
- **One disclosed deviation from the requested target tree**: "Currency &
  Features" stayed as a single sub-section under General rather than being
  split across General (Currency) and Modules & Access (Feature
  Management), because it is one `<form>` posting to one endpoint
  (`updateCurrencyAndFeatures()`) whose `enable_*` feature checkboxes are
  read with `$request->boolean(...)`, which returns `false` when a field is
  absent - splitting the visual form into two independent submissions would
  have silently disabled every feature on a currency-only save. This is a
  real, currently-*enforced* setting (read by `partials/mobile-menu.blade.php`
  for real navigation gating), unlike the cosmetic permissions matrix above,
  so the extra risk was not worth taking for a purely visual reorganization.

Tests: `SettingsPageIntegrityTest` grew from 3 to 10 cases (role-list
coverage, save-then-persist, JSON save response). Full suite run to
completion: **499 passed, 1348 assertions (SQLite)**, up from 489/1306,
zero failures. `php -l` clean, `view:cache` compiles every Blade template.
All 11 real settings sub-tabs (`society`, `app`, `currency-features`,
`theme`, `complaint-categories`, `permissions`, `email`, `email-templates`,
`sms`, `payment`, `security`) plus the orphaned `push` bookmark path
live-verified returning 200 against the running Apache server.

**Not attempted**: real backend enforcement of the permission matrix
(explicitly scoped out by the user), MySQL/MariaDB re-run (no schema
changes this session), Flutter (`flutter`/`dart` still absent from this
environment, reconfirmed), and browser-driven visual/responsive testing (no
browser automation available - verification here is server-rendered-HTML
and live HTTP checks, not click-through).

## Follow-up task (2026-09-22, session 9 — "Owner role normalization")

The prior session explicitly stopped a proposed Villa Owner/Apartment Owner
→ Owner merge after finding the real scope (31+ `app/` call sites, not the
16 previously documented) and the user said not to risk existing user data.
This session's brief reversed that decision explicitly and asked for the
full merge; confirmed once more via an explicit yes/no question before
starting (the reversal itself, not just the task, was worth confirming
given the size and the very recent "stop" instruction). Delivered completely
this time, done incrementally with live verification at each stage rather
than as one blind edit:

- **New canonical `Owner` role** (`RolesAndPermissionsSeeder::createOwner()`),
  permissions copied exactly from Villa Owner/Apartment Owner (they were
  already identical - confirmed by reading the seeder before touching it).
  Legacy roles kept registered (not deleted) so any not-yet-migrated account
  keeps working exactly as before.
- **New idempotent migration command**, `php artisan users:migrate-to-owner-role`
  (report-only by default, `--force` to write; same UX convention as the
  existing `users:rotate-weak-passwords`). Report mode confirmed exact counts
  before any write (4 Villa Owner + 9 Apartment Owner holders, 1 overlap =
  12 unique users); `--force` moved all 12 to `Owner` in one DB transaction,
  final count matched the predicted sum exactly, verified again in the DB
  directly afterward (`Villa Owner: 0`, `Apartment Owner: 0`, `Owner: 12`,
  same ids/names/emails as before - nothing recreated, nothing lost).
- **~25 files updated** across controllers, routes, a model, a policy, two
  services, and Blade views so 'Owner' is recognized everywhere the legacy
  roles were previously checked - `hasRole('Villa Owner')` patterns became
  `hasAnyRole(['Owner', 'Villa Owner', ...])`, and villa/apartment-scoped
  data queries (Payments, Facility Bookings, Reports, Utility Bills,
  Complaints) gained a combined "every property this Owner owns, regardless
  of type" branch instead of needing two role-specific ones. Full list in
  git history; the highest-risk files (`PaymentController`,
  `FacilityBookingController`, `ReportController`, `DashboardController`,
  `OwnerController`, `VillaController`) were read in full before editing,
  not pattern-matched blind.
- **Two real, independent bugs found and fixed while doing this**:
  (1) `VillaController::store()` still had the exact `User::find($villa->owner_id)`
  users.id/owners.id confusion bug that `update()` in the same file had
  already documented fixing (commit `4e35ffa`) - the create path was never
  actually fixed, only the edit path. (2) `OwnerController::update()` called
  `$ownerUser->syncRoles([])` then reassigned Villa Owner/Apartment Owner by
  property type on *every edit* - left alone, this would have silently
  stripped `Owner` back to a legacy role the next time anyone edited an
  already-migrated owner. Both fixed as part of this pass, not deferred.
- **New-user creation now assigns `Owner` directly**
  (`OwnerController::store()`), and `UserController::assignableRoles()`
  excludes Villa Owner/Apartment Owner from every role dropdown/filter that
  reuses it (create, edit, index filter, inline edit modals - confirmed
  they all share this one method already, so one change covered all four).
- **Settings → Role & Module Permissions matrix** (this session's own prior
  redesign) updated to show `Owner` instead of two separate legacy columns,
  per the brief's explicit instruction - superseding an earlier instruction
  in this same conversation to keep them separate "for now," which this
  session's brief explicitly and knowingly reversed.
- **Flutter**: no role-name string comparisons existed for Villa
  Owner/Apartment Owner anywhere in `societyflowmob/lib` (checked - zero
  matches) - the mobile app already keyed its Owner-specific UI off a
  server-computed `is_owner` boolean (`AuthApiController`/`ProfileApiController`,
  both already calling the same `User::isOwner()`/`isVillaOwner()` helpers
  this session updated), so no Flutter change was needed for the role merge
  itself. Separately, fixed the previously-flagged real defect where Staff
  was routed to Guard's QR-scanner home screen
  (`main_shell.dart`'s `_isGuard` matched `'Guard' || 'Staff'`) - Staff now
  gets the same generic `HomeScreen` as other non-Guard roles. This is a
  one-line, mechanical removal (not a rewrite) so it's safe without a
  compiler, but per this file's standing policy it is **not claimed as
  compiled or device-tested** - `flutter`/`dart` remain absent from this
  environment.
- **Live-verified after migration, not just unit-tested**: both real
  migrated accounts (`villaowner@societyflow.com`, formerly holding both
  legacy roles; `owner@societyflow.com`, formerly Apartment Owner only)
  logged in live against the running Apache server post-migration and
  correctly reached `/my-villa` or `/my-apartment`, `/payments`,
  `/reports/financial`, `/facility-bookings`, `/complaints/create`, and were
  still correctly blocked from `/users` (403) - the exact same behavior as
  before migration, now under the new role.
- **Not attempted / explicitly out of scope**: rewriting the ~15 remaining
  occurrences that are display-only text labels (e.g. "Villa Owner
  {$society} Villa - {$name}" strings, the `admin/villa-assignments` page's
  "Villa Owner" field label, `landing.blade.php`'s public marketing copy) -
  these are cosmetic and reached only through legacy branches or plain
  English descriptions, not authorization logic; changing them further
  risked unnecessary scope creep for zero functional benefit. A dedicated
  `StaffHomeScreen` for mobile was also not built - out of scope per the
  brief's own "do not invent an unrelated Staff product" instruction; Staff
  now safely falls back to the generic screen instead of Guard's.

Tests: 10 new (`OwnerRoleNormalizationTest`) - migration report/force/
idempotency, dashboard routing for villa-only vs apartment-only vs combined
Owner accounts, `/my-villa`/`/my-apartment`/payments/reports access,
negative test (`/users` still 403), a not-yet-migrated Villa Owner user
still works unchanged, new-owner creation assigns the canonical role, and
the role dropdown no longer offers the legacy names. All 10 passing.

**Full suite run to completion after all changes: pending at the time this
entry was written - see the next entry or re-run `php artisan test` before
treating this as fully verified.** Targeted runs
(`OwnerRoleNormalizationTest`, plus every controller file's own `php -l`)
are clean.

## Follow-up task (2026-09-28, session 10 — "visitor management + XAMPP exposure")

Two related fixes, one requested and one found while acting on the other.

**Visitor management dead-end, fixed at the root.** Admin's Add Visitor form
required a Villa Area and showed "No villa areas available" even in
villa-only societies with real villas configured (reported case: Royal Eden,
villas 158/159/160 all correctly linked to `villa_areas` row 7). Root cause:
`resources/views/visitors/create.blade.php`'s JS fired `loadVillaAreas()` on
`DOMContentLoaded`, before the async `fetch('/api/flats')` it depended on had
resolved - the villa list was always built from an empty array, and nothing
ever re-ran the loader once the real data arrived. Not a data problem, a
client-side race.

Fixed by removing Villa Area as a visitor field entirely (the destination
property already identifies where the visitor is going) and making the form
property-aware and role-aware, per the brief:
- **New `app/Support/VisitorPropertyScope.php`** - one place for "which
  properties can this user manage visitors for," reused by every entry
  point: `VisitorController` (web), `GuardController::storeVisitor` (gate
  registration + resident notification), and the index/create/show views.
  Admin/Staff/Guard/Super Admin act society-wide; tenants and owners are
  resolved server-side from their tenancy/ownership (`UserFlatResolver`,
  extended with a new `tenantFlatIds()` so the property-scope helper and the
  original resolver share one tenancy query instead of two).
- **Admin**: "Visiting Property" is a plain select of the society's real
  units with owner names (`Villa 159 — Lokesh Suman`, `Villa 160 — Not
  Assigned`) - no Villa Area step, never blocked by missing villa areas.
- **Tenant/owner with one property**: the create form shows no dropdowns at
  all - a read-only "Visiting: Villa 159" line - and the invite is
  pre-approved (reuses the existing gate QR token issued on
  `approval_status = allowed`, not a new pass system).
- **Approve/Deny** is now available to the resident of the property, not
  only Admin/Staff, enforced server-side (`VisitorPropertyScope::canManageFlat/
  canManageVisitor`) - a submitted `flat_id` from any role is checked against
  this, never trusted, closing the request-manipulation case the brief asked
  for explicitly.
- Guard-registered ("unexpected") visitors now notify the property's tenant
  (or owner, if not rented) via the existing `PushNotificationService` -
  reusing `VisitorApiController`'s existing `notifyResident` pattern rather
  than adding a second one.
- Renting a unit does not touch `Owner`/`owner_id` - verified by test, not
  just assumed.

Tests: 15 new (`tests/Feature/VisitorPropertyFlowTest.php`), covering every
case in the brief's own list (admin create without villa areas, admin create
for any villa, admin blocked cross-society, tenant create/blocked-elsewhere/
request-manipulation, tenant approve/deny own vs. blocked for a neighbour
and for a foreign society, guard cannot approve on a resident's behalf,
owner keeps ownership through a tenancy, guard-register → tenant-approve →
check-in/out, and the existing guest-QR scan path working with a
tenant-issued invite). Full suite: **574 passing, 1689 assertions (SQLite)**,
up from the previously-recorded 489 (session gap - not all sessions in
between updated this file; the last hard confirmed number in this doc was
489/1306).

**Found separately, while setting up "open XAMPP and the app just works":**
a real, exploitable information-disclosure bug, not part of the brief but
fixed immediately rather than left for later. Apache's document root for
this app under XAMPP is `htdocs/SocietyFlow` (the project root), not
Laravel's real document root `htdocs/SocietyFlow/public` - with no root
`.htaccess`, that meant `.env` (DB credentials, `APP_KEY`, mail secrets),
`database/database.sqlite` (the dev DB, real resident PII - see §1's own
warning) and `storage/logs/laravel.log` were all directly downloadable over
plain HTTP, with directory listing on for the entire project tree. Confirmed
by fetching each one before touching anything, not assumed.

Fixed with a new root-level `.htaccess` (`C:\xampp\htdocs\SocietyFlow\.htaccess`,
outside `resources/views` and not one of the "do not modify" paths in §12):
blocks `.env`/`.git`/`*.sqlite*`/`*.log`/`*.lock`/`*.key`/`*.pem` outright
regardless of `mod_rewrite`, turns off directory indexes, and 302-redirects
`http://localhost/SocietyFlow/*` into `.../public/*` so starting Apache from
the XAMPP control panel is enough - no `php artisan serve` needed for local
browsing. (The redirect has to be external, not an internal rewrite: Apache
leaves `REQUEST_URI` as the client's original path even when the file lookup
is rewritten elsewhere, and Symfony's base-path stripping needs
`REQUEST_URI` and `SCRIPT_NAME` to agree on the `/public` segment or every
route 404s despite PHP executing correctly - traced with a throwaway
diagnostic script, deleted immediately after.) Verified after the fix:
`.env`/sqlite/log → 403, `composer.json` → 404 (no longer served), the
redirected root/`/login`/`/dashboard` all render the real app.

Not touched: `public/.htaccess` (Laravel's own, already correct), any
Laravel route or controller (this is purely an Apache-layer fix), and the
owner's existing `START-DEV.bat`/`start-server.ps1` (still the right way to
run the mobile-facing `php artisan serve --host=0.0.0.0` server for phone
testing - the new `.htaccess` only changes how XAMPP's own Apache, already
running, exposes the project).

## Follow-up task (2026-09-29, session 11 — "final product QA master pass")

A 37-section "complete product readiness" brief (auth through cleanup through
mobile parity through a fixed final-report format) was received. Given the
real size of that ask against nearly a dozen prior sessions already covering
most of it (see this file's own history and `docs/TODO.md`'s `done` rows),
triaged to what was still genuinely open plus what a fast, targeted sweep of
the remaining known-bug classes (fails-closed Super Admin checks, the
`Route::redirect`-swallows-POST pattern, dead flash-message markup) actually
turned up - fixed and verified rather than only reported:

- **`EventController`: Super Admin locked out of every event, in every
  society.** `index`/`calendar`/`getCalendarEvents` built `Event::where(
  'society_id', $societyId)` unconditionally; Super Admin's `society_id` is
  null, so those queries silently returned zero rows (not an error - just an
  empty events list forever). `show`/`edit`/`update`/`destroy` compared
  `$event->society_id !== $user->society_id` with no exemption at all, so
  Super Admin got a 403 on every single event. The separate `create`/`store`/
  `edit`/`update`/`destroy` role gates (`hasRole(['Admin','Manager'])`) had
  the identical gap. Fixed to the same `!hasRole('Super Admin')` exemption
  pattern CLAUDE.md documents for the rest of the codebase.
- **Found independently while testing that fix**: `EventController::store()`/
  `update()` read `$validated['visible_roles']`/`['visible_users']` directly.
  Both are `nullable`, and Laravel's `validate()` omits an absent field
  entirely rather than returning it as null - a perfectly ordinary
  non-role-based event update with no `visible_users` array in the request
  threw "Undefined array key" instead of saving. Fixed with `?? null`.
- **Closed a real test gap, not just a report item**: `VisitorApiController`'s
  allow/deny/check-in/check-out endpoints (the mobile app's entire visitor-
  approval surface) had zero test coverage before this - P1-34 fixed the
  underlying `visitor_logs.action` enum blind to whether the endpoints
  themselves actually worked. Now confirmed end to end: a resident can
  approve/deny their own visitor, cannot touch a neighbour's (404 - the query
  is scoped, not just gated), a guard can check a visitor in and back out
  with the log rows written, check-in before approval is rejected, and a
  guard from another society gets a 404 rather than another society's
  visitor.
- **Verified, not re-fixed**: grepped every controller for the two known bug
  classes that have bitten this codebase before (`hasRole('Admin') &&`
  society-scope gating, `hasRole('Owner')` as the old nonexistent-role bug)
  - zero live occurrences of either; every match was a comment documenting a
  past fix, or (for `Owner`) the real role that now legitimately exists since
  the P2-14 migration. Also re-swept `routes/web.php` for another
  `Route::redirect(...)`-matches-every-verb-and-swallows-POST bug like the
  one found in the residents route (session 2) - none found; that remains
  the only instance.
- **Reviewed, deliberately not changed**: `FacilityBookingController`'s two
  `$flat->society_id !== $user->society_id` checks (lines ~486, ~768) have
  the same missing-Super-Admin-exemption shape as the `EventController` bug,
  but booking a facility for oneself isn't a workflow Super Admin actually
  has (no property, no personal booking need) - unlike being locked out of
  *managing* every event, this isn't a real product gap, so it was left
  alone rather than patched for symmetry alone.
- **Dead markup removed**: `layouts/app.blade.php` rendered three
  `display:none` divs (`data-success-message`, `data-error-message`,
  `data-validation-error`) on every single page load; a repo-wide grep found
  zero JS anywhere that reads any of the three attributes - confirmed dead,
  not just old-looking, before removing. The visible flash-message banner
  above it (the only thing that actually renders) was and remains singular -
  audited for the brief's "duplicate alert" concern specifically and found
  no live duplication bug, just this one inert leftover.
- **Production config (local dev box only)**: `APP_ENV=production`,
  `APP_DEBUG=false` (correct), `APP_URL` is a plain-HTTP LAN address,
  `SESSION_SECURE_COOKIE=false`, `CORS_ALLOWED_ORIGINS` unset (defaults to
  `*`) - all three already tracked as P0-5's operator decision, confirmed
  unchanged, not re-litigated. **The live deployment at
  societyflow.joboapps.com was not inspected or tested** - this session had
  no access to that server; only this local XAMPP dev box was audited.

**Explicitly not attempted this pass** (each is either already covered by a
prior session per this file's own history, or is a multi-day scope on its
own that a 37-section brief cannot compress into one pass honestly):
full per-page UI click-through of all ~90 controllers, a fresh IDOR sweep of
every listed endpoint (the ones named in the brief - `/users`, `/owners`,
`/flats`, `/bills`, `/facilities`, etc. - already have dedicated passing
regression suites from sessions 2-9: `AuthorizationIsolationTest`,
`CrossSocietyListingTest`, `CrossSocietyAuthorizationGapsTest`,
`PaymentCrossSocietyWriteTest`, `PaymentListingScopeTest`,
`NoticeShowIsolationTest` - re-run as part of the full suite below, not
re-derived from scratch), a full N+1/performance pass (existing
`QueryPerformanceTest` re-run clean, no new instrumentation added), and any
Flutter work - **the Flutter SDK remains absent from this environment**
(`flutter`/`dart` not on PATH, checked again). The brief's paths
(`D:\xampp\htdocs\SocietyFlow\societyflow`) don't match this environment's
actual layout (`C:\xampp\htdocs\SocietyFlow\societyflowmob`) - flagged
rather than silently assumed to be the same tree.

Tests: 12 new (`EventSuperAdminAccessTest` 5, `VisitorApiApprovalTest` 7).
Full suite: **586 passing, 1737 assertions (SQLite)**, up from 574/1689,
zero regressions. `php -l` clean on every touched file, `view:clear`
recompiles without error.

## Follow-up task (2026-10-02, session 12 — "financial report + society expense management")

A focused brief (explicitly *not* another whole-app audit): make the
Financial Report useful to a real society admin, add society expense
management, and keep one property's own expenses out of society totals.
Inspected first: `expenses`/`expense_categories` **already existed** (original
schema, models, `manage_expenses` permission) with zero controllers, routes,
views or tests — extended, not duplicated (CLAUDE.md §10 idiom). Delivered,
each item tested and then driven live against the running dev server:

- **Society expenses** — one additive migration (`scope`, `flat_id`,
  `paid_by`), `ExpenseController` + 5 views, categories seeded per society on
  first use (24 defaults, Admin can add/hide), receipts, filters (date /
  category / scope / method / vendor / search), Admin+Accountant write,
  Admin-only delete, audit rows (`ActivityLog::recordFor`, new). The form asks
  "society/common or one property?" first; a common expense never shows a
  property picker and is forced `paid_by = society` server-side; a property
  expense requires a flat in the same society (verified, never trusted).
- **Financial Report rebuilt** on `FinancialReportService` — the page and the
  PDF render the *same* array (pinned by a test that captures the PDF view
  data and compares it to the page's view data). Sections: Overview, Income
  by source, Society Expenses by category with %, Maintenance Billing,
  Collections & Dues, Property-wise maintenance, and Property Expenses in a
  visibly separate, never-summed section. Rent is a memo line, not income.
  Owners/tenants get an own-property view with no society expense section.
  Four pre-existing defects in the old `getFinancialData()` went with it
  (`status='paid'` never matched; integer compared to a month *name*; N+1
  over every flat; no expenses at all).
- **Period bug fixed at the root** (`ReportPeriod`): invalid month/year is a
  validation error, never a silent fallback to today; the on-screen label
  tracks the dropdowns live, and the PDF posts the same selection.
- **Maintenance Report corrected**: the brief's "₹36,800 / 3 transactions for
  2026" was 2 successful rent payments + 1 *pending* rent payment; real
  maintenance collected is ₹20,300 / 3 (confirmed in the DB and live).
  Tenants previously saw the whole society's maintenance — now scoped.
- **Dashboard**: Admin and Accountant each gained one "Society Expenses This
  Month" card with the net underneath, linking to the expense list.
- **Two visitor regressions** (`VisitorManagementTest`, predating this
  session, surfaced by the full suite) fixed in code: villa area shown in a
  tenant's fixed "Visiting:" label; no-property resident gets 403 on submit.

**Live-verified** (real login, real CSRF, real dev DB): Royal Eden admin
recorded Villa 160 personal electricity ₹3,500 (property) + road lighting
₹18,000 + security ₹1,20,000 + gardening ₹20,000 + water tanker ₹15,000
(society) through the real form endpoint; June 2025 report shows exactly
Security ₹120,000 / Common Electricity ₹18,000 / Gardening ₹20,000 / Common
Water ₹15,000 = **₹173,000**, the Villa 160 row in the separate section, no
"October 2026" anywhere, June 2021 → empty state, month=13 → rejected, PDF
downloads as `Financial-Report-June-2025.pdf`. **Those five expense rows were
left in the dev DB** (society 6) so the owner can see the scenario; delete
them from `/expenses` if unwanted.

**Data-integrity findings (reported, not changed)**: 22 `maintenance_bills`
rows have `paid_amount` > 0 with no matching `payments` rows (marked paid
manually, mostly rent); flat `1A` in society 1 is `occupied` with no active
tenant/resident/owner; `maintenance_bills` is unique on
(society, flat, month, year) so a unit cannot hold a rent bill *and* a
maintenance bill for the same month — a schema limitation worth a P2.

Tests: 29 new (`SocietyExpenseTest` 14, `FinancialReportTest` 15). Full
suite before the visitor fixes: 624 tests / 1990 assertions / 2 failures
(the two visitor cases above). Final full run: **see the line below.**
`php -l` clean on every touched file, `view:cache` compiles every template,
`route:list` resolves 424 routes. **Not run:** MySQL/MariaDB (not started
this session — the new migration is plain `addColumn`, no enum/rebuild),
Flutter (`flutter`/`dart` absent again; no mobile surface was changed),
browser click-through (verification was live HTTP against the server, not a
browser).

### Correction the same day: Society Admin manages society finances only

Showing "Property Expenses (not society expense)" to the Admin implied they
should also track every villa's private bills. Removed from the Society
Expenses page, the Financial Report (web and PDF) and the resident view:

- `ExpenseController` is society-only: the list, totals and filters ignore
  property-scoped rows; the form has no scope, property or paid-by field; the
  server sets `scope=society`, `flat_id=null`, `paid_by=society` and never
  reads them from the request. A legacy property row returns 404 on
  show/edit/update so an edit cannot silently convert it.
- `FinancialReportService` no longer builds or returns property expenses.
  Individual property bills and payments stay in billing/collections and can
  never count as society expense (tested).
- Wording: "No society/common expenses recorded for this period."
- The `scope`/`flat_id`/`paid_by` columns were kept (additive, harmless, and
  the exclusion guard relies on `scope`); no new migration.
- Removed the one Villa 160 demo expense I had added to the dev DB (society
  6); the four society expenses (total ₹1,73,000) remain.

Tests: `SocietyExpenseTest` 17 and `FinancialReportTest` 17 (rewritten around
the new rule, not weakened; added individual-bill/payment and empty-state
cases). **Final full suite: 627 passed, 2015 assertions, 0 failures
(SQLite).**

## Follow-up task (2026-10-02, session 12b — "one source of truth for occupancy")

A whole-product readiness brief used one observed bug as a *pattern*:
Property & Occupancy and the Villas page disagreed about the same villas.
Scoped to that pattern done completely, not the whole 37-phase list (see
"Not done" below). Findings and fixes:

- **Root cause:** occupancy was decided in four different ways. Fixed with
  `App\Support\PropertyOccupancy` (active tenant → Tenant Occupied; else
  active owner → Owner Occupied; else Vacant) and every reader moved onto it:
  hub rows + counters, Dashboard, Villas, Flats (list, filter, detail),
  Buildings, Villa areas, all owner/villa-owner/apartment-owner screens, and
  the owner-level counts. The stored `flats.status` is now only a cache kept
  in sync *from* the rule (`Flat::updateStatus()`), no longer a thing to read.
- **Three writers were wrong and are fixed:** `Flat::updateStatus()` (called
  on every Resident change) overwrote `on_rent`; tenant move-out and delete
  wrote `vacant` even when the owner lives in the unit; a tenant re-assigned
  after move-out kept a stale `move_out_date` while `active` (now cleared at
  the model).
- **Duplicate logic removed:** owner-by-address matching existed twice
  (`Flat::resolvedOwner`, `VisitorPropertyScope::labelledOptions`); both now
  call `PropertyOccupancy::ownersFor`.
- **Security bug found on the way:** `FlatController` search leaked other
  societies' flats (ungrouped `orWhereHas`). Fixed + tested.
- **Dev data:** live Royal Eden now reads 158/160 Owner Occupied, 159 Tenant
  Occupied, 165/72 Vacant on the hub, Villas page and dashboard alike. No
  rows were changed. The tenant on Villa 159 had `active` + a stale move-out
  date from a re-assignment; reported, not edited.
- **Existing tests:** four asserted the old stored-status behaviour. One was
  an app change (card now "Tenant Occupied (On Rent)", test untouched); three
  were rewritten because their *premise* was the bug (fixtures that set only
  the stored column, or a "single grouped query" implementation detail now
  replaced by a constant-query-count assertion). Reasons are in each test.

**Not done in this pass (honest scope):** Flutter (SDK absent; mobile/API
show no occupancy, so nothing to align), double-submit idempotency audit
across all create flows, timezone audit (**`APP_TIMEZONE=UTC` — month
boundaries and "today" are 5½ h off for an Indian society; operator
decision**), dead-code sweep, per-module N+1 audit beyond the new queries,
MySQL run, browser E2E. The edit forms' manual status dropdowns still write
the stored column.

Tests: 9 new (`PropertyOccupancyConsistencyTest`). **Final full suite: 636
passed, 2088 assertions, 0 failures (SQLite).** `php -l` clean on every
touched file, `view:clear` recompiles.

## Follow-up task (2026-10-03, session 13 — release blockers + Android + 8-card dashboard)

Three briefs in one day, in order: the "final release blocker" pass, the
Android bring-up, and the 8-card Admin dashboard. The mobile side is written
up in [MOBILE_PRODUCTION_READINESS.md](MOBILE_PRODUCTION_READINESS.md).

**Tooling that was "unavailable" was installable or merely off PATH:** git
2.56 was already at `G:\Git\cmd` (machine PATH, not the shell's); MariaDB
10.4.32 was already listening on 3306 (`societyflow_test`, root, no
password); the Flutter SDK, JDK 17, Android SDK and an API 35 emulator were
installed from official sources (mobile doc §1); the owner's own phone was
plugged in during the pass and used for the device test.

**Data integrity (dev DB repaired; backup first in
`storage/backups/database.sqlite.pre-integrity-repair-*`).**
`App\Support\DataIntegrityRepair` + migration `2026_10_03_000001` +
`php artisan data:integrity-audit [--fix]`. On the dev DB: 1 active tenancy
with a stale move-out date cleared (Villa 159); 10 flats whose stored status
disagreed with the occupancy rule resynced (incl. 1A in society 1); 3
`RENT-…` bills relabelled rent; 22 bills marked paid with no payment behind
them reconciled with one clearly labelled `payment_method = unrecorded`
ledger row each (₹6,53,400; date = the bill's paid date or the moment "paid"
was recorded; method/reference were never recorded and the row's `notes`
says so). Every change is in `activity_logs`; the audit now reports "All
invariants hold".

**The payments ledger is the source of truth for bills.**
`MaintenanceBill::syncFromLedger()` runs on every Payment save/delete;
`paid_amount`/`balance_amount`/`status`/`paid_date` are never written by
hand any more. Rent "paid at creation" requires payment date + method and
writes the ledger row; the rent edit form has no status dropdown;
tenant-submitted payments stay `pending` (they used to count as paid at
once) until an Admin/Accountant verifies them (`payments.approve` /
`payments.reject`, on the payments list). Add-payment endpoints take an
idempotency key. `TenantRentRecordsSeeder` writes ledger rows. MySQL
`payment_method` enum widened (`bank_transfer`, `unrecorded`); the invalid
`status = 'completed'` is no longer written.

**Occupancy can no longer be set by hand.** `Flat::saving` replaces any
occupied/vacant/on_rent write with the derived value; only
maintenance/under_maintenance is a choice (the four flat/villa forms now
offer "Available / Under Maintenance" and show the derived occupancy).
`Owner` saves/deletes resync their flats. **Regression found and fixed:**
Admin → Tenants refused to rent out any owner-occupied unit (stored status
not vacant, or `owner_id` set); Villa 159 only ever worked because the
cache was stale. The only thing that blocks a tenancy now is another active
tenancy (`Admin\TenantController`, `UserTenantAssignmentController`).

**Timezone.** `societies.timezone` (default Asia/Kolkata; Settings → App
Settings → Time Zone now writes it), `App\Support\SocietyClock` for
today / month / day bounds / overdue / access-pass hours, scheduler reminder
jobs pinned to IST, `config('app.society_timezone')`. Storage stays in
`APP_TIMEZONE` - see PRODUCTION_CHECKLIST "Timezone".

**Idempotency.** `PreventDuplicateFormSubmission` middleware + a one-shot
`_idempotency` token (`partials/idempotency`) in 26 create forms, a global
double-submit JS guard in both layouts, idempotency keys on both
rent-payment modals. Facility booking was already locked; duplicate
access-pass entry was already refused.

**Production config.** `TRUSTED_PROXIES` (config + `TrustProxies`),
`SecurityHeaders` middleware (HSTS only on HTTPS + production), OTP no
longer written to the log, MySQL parity migration for
`tenants.move_in_date` / `notices.publish_date` (P1-19).

**Complaints page was a 500** in the inherited working tree
(`Complaint::STATUSES` / `scopeOpenTickets` / `syncResolutionTimestamp`
used by the controller but absent from the model) - added.

**Mobile API bugs found by the end-to-end test.** A tenant created from
Admin → Tenants had no unit in `/auth/me` and `/profile`; `/payments` and
`/payments/history` hid payments the admin recorded for a tenant's bill.
Both fixed. Dio now sends `Connection: close` (PHP dev server keep-alive
made every request look like "Cannot reach server").

**Admin dashboard locked to 8 KPI cards** (`data-kpi` tagged): Total
Properties, Occupied, Vacant, Residents (active owners + tenants, one person
once), Visitors Today (society-local day), Open Tickets (open + in_progress),
Pending Dues (outstanding bill balances - it summed pending *payments*),
Monthly Revenue (success payments in the society-local month). Buildings /
Apartments / Villas and society expenses moved to a compact overview line.

**Dead code removed (zero references, verified):** `InstallController`,
`SuperAdmin\SuperAdminController`, `Admin\FirebaseSettingsController`,
`email-test`, `layouts/navigation`, `partials/sidebar`,
`partials/sidebar-embedded`, `super-admin/settings/partials/{app,theme}`.

**Tests added:** `OccupancyStatusDerivationTest` 9, `BillLedgerConsistencyTest`
12, `SocietyClockTest` 5, `DuplicateFormSubmissionTest` 3,
`ReportCalculationBoundaryTest` 7, `EndToEndScenarioTest` 1 (108 assertions:
society → villa → owner → tenant → rent → payment → visitor → pass → scan →
complaint → booking → expense → report; DB + web + API + dashboard checked
at every step), `AdminDashboardKpiTest` 5. Three existing assertions were
corrected because their premise was the removed behaviour (each says why).

**Suite results (both re-run after the notices-parity correction below):**
**687 passed, 2511 assertions (SQLite, 2026-10-03)**; **687 passed,
2507 assertions (MariaDB 10.4.32, 2026-10-03)**. Flutter: `pub get` ok,
`analyze` 4 intentional infos, `test` 24/24, debug APK built, installed and
logged in on a real phone (mobile doc §6).
## Next recommended task

1. **Run `flutter pub get && flutter analyze && flutter test`** the moment
   a Flutter SDK is available - this is still the first real compiler pass
   this code will have seen. The `errorBuilder` fix and the raised SDK
   floor should mean a clean `pub get`, but that is what needs confirming,
   not assuming.
2. **Decide whether Guard and Accountant should reach `/payments` at all.**
   They are now correctly scoped to their own society, but they still see the
   payment register. `RestrictStaffAccess` is a Staff-only URL denylist; if
   these roles should be excluded, that middleware needs to cover them rather
   than each controller re-deriving it.
2. **Flutter SDK needs to be installed in a verification environment** before
   any Flutter claim in this file can move past "code inspection only" - see
   `docs/TODO.md` for what that inspection already found (nothing needed
   changing, but nothing has actually been run since 2026-09-09 either).
3. Install the Flutter/Android toolchain and set up browser automation
   (Playwright or similar) in whatever environment does release
   verification - neither exists in this one, and CLAUDE.md's own testing
   requirements (§8) assume both are available somewhere in the pipeline.

Also still open from before: Razorpay/Cashfree (need sandbox credentials),
the remaining 13 of 15 instant-update actions, and the seven orphaned
`instant-*` JS files in `public/js/`. See `docs/SCHEMA_PARITY.md` for the
constraint-parity follow-ups (P1-15, P1-17, P1-18, P1-19).

### Correction the same day: mobile Notices showing "No notices" for a published notice

Reported directly against Royal Eden: the web Notice Board showed "Ganesh
Chaturthi Celebration" as Published, mobile showed "No notices". Traced the
full chain (Flutter screen → provider → `GET /api/v1/notices` →
`Api\NoticeApiController` → `Notice::scopePublished()`). Two real causes:

- `NoticesScreen` folded an API failure into the same "No notices" empty
  state as a genuinely empty list, so a dead request was indistinguishable
  from nothing posted. Now shows `ErrorView` + Retry when `state.error` is
  set and the list is empty; a successful empty response is unaffected.
- `Notice::scopePublished()` additionally required `expiry_date` to be null
  or in the future. The web Notice Board (`NoticeController::index`, used
  by every role) never filters by expiry_date — a published notice stays
  listed indefinitely. The named notice's `expiry_date` (2026-09-30) had
  passed, so it stayed on web and vanished from mobile. First pass here
  left that as "working as designed"; told directly that web and mobile
  must show the same data, so the scope was corrected to match web - status
  and publish_date still gate visibility (drafts/pending/future-dated
  notices stay hidden on mobile, same as before), but expiry_date no longer
  does. Both the mobile `/notices` list/show endpoints and the dashboard's
  `active_notices` count use the same scope, so all three now agree with
  web.

Live-verified against the real Royal Eden tenant account and the real
notice (id 29): `GET /api/v1/notices` now returns it, dashboard
`active_notices` = 1, matching the web board.

Tests: `NoticeApiVisibilityTest` 8 (society-wide published visible to
owner/tenant, draft/pending excluded, future-dated excluded, cross-society
excluded, API field shape matches the Flutter model, the expired-but-
published case proven against both endpoints and cross-checked against the
rendered web page). Full suite re-run clean afterward (see below).

### Earlier gate (2026-10-10, superseded by "Master finalization" below)

| Check | Result |
|---|---|
| Laravel, SQLite, 8 shards, 144 classes | **1391 tests, 5914 assertions, 0 failures, 0 errors, 4 skipped** (engine-specific by design) |
| Laravel, MariaDB 10.4.32 (throwaway, port 3307), 6 shards | **1391 tests, 5912 assertions, 0 failures, 0 errors, 2 skipped** (engine-specific) |
| Reconciled with `phpunit --list-tests` | 1391 = 1391 on both engines |
| Flutter | `flutter test` 162 passed; `flutter analyze` 2 info (known `value:` deprecations); `flutter build apk --debug` compiles |
| Browser (Playwright-core + system Chrome, isolated copy DB, mail=log) | 16/16 checks |
| PHP lint of every changed file | 0 failures |

The SQLite run preceded one MySQL-only migration `down()` fix; the full MariaDB suite was re-run after it. A first MariaDB run had exactly that one error.

**Release status: READY FOR STAGING, NOT READY FOR PRODUCTION.** Blockers: operator decisions on the Piyus repair and Suraj's link; real-device Android run; real email/WhatsApp/payment delivery (not authorised/no keys); MariaDB on the original XAMPP data directory (deliberately not touched).

## Master finalization — test-data reset, Royal Eden seeding, notification fixes (2026-10-10, evening)

Full record of what was modified, with ids, row counts, backups and rollback values:
**[TEST_DATA_2026-10-10.md](TEST_DATA_2026-10-10.md)**. Target: the local dev SQLite DB only.

- **Data:** Piyus residency row closed; Suraj Suman linked to villa 160 (flat 123); one mismatched user/owner photo aligned; four junk "T" societies (#8-#11) and their 8 dependent rows removed; Royal Eden demo dataset seeded (idempotent seeder); migrations 000001-000004 applied. Urban Heights (#1) and the other named societies were **not** deleted (no conflicts found; they hold real-looking accounts).
- **Safety:** this DB stores real provider credentials, and `MAIL_MAILER=log` does not stop a real send (per-society SMTP overrides it). One earlier browser run attempted a welcome email to an `example.test` address via Royal Eden's stored SMTP login; it failed authentication, nothing was delivered. Fixed with `EXTERNAL_MESSAGING_ENABLED` (see CLAUDE.md §7). **Rotate the stored credentials.**
- **Defects found and fixed this round:** tenant rent page/pay/receipt and mobile complaints used the stale legacy residency (wrong unit shown/payable); web booking form notified nobody; events announced when cancelled and re-sent "new event" on every save; `/my/bookings` printed raw datetimes; demo document with an invalid category was hidden from Society Info; two broken migration rollbacks (earlier).
- **Final gate, latest code:**

| Check | Result |
|---|---|
| Laravel, SQLite, 8 shards, 149 classes | **PASS** 1445 tests / 6193 assertions, 0 failures, 4 skipped (engine-specific) |
| Laravel, MariaDB 10.4.32 (throwaway, :3307), 6 shards | **PASS** 1445 tests / 6191 assertions, 0 failures, 2 skipped (engine-specific) |
| `phpunit --list-tests` | 1445 = 1445 on both engines |
| Flutter | **PASS** `flutter test` 162; `flutter analyze` 2 info (known); no Flutter file changed this round |
| Browser (real Chrome, isolated copy DB, credentials blanked, kill switch on) | **PASS** 39/39, run twice |
| Android debug APK | compiled earlier today; Flutter source unchanged since. **Device run: NOT TESTED** (no device/emulator) |
| Real email / SMS / WhatsApp / payment delivery | **NOT TESTED** by design |
| Original XAMPP MariaDB data dir | untouched |

**Release status: READY FOR STAGING, NOT READY FOR PRODUCTION.** Blockers: rotate the credentials in this DB; real-device Android run; real delivery checks with approved recipients; MariaDB on the original XAMPP data dir; operator decisions in TODO (P2-31, P2-32).

## Credential security, booking conflicts, event WhatsApp (2026-10-10, night)

- **Kill switch verified and hardened.** `EXTERNAL_MESSAGING_ENABLED=false` now also cancels any outgoing mail whose transport is not `log`/`array` at `MessageSending` (so a society's SMTP override, or any future path that sets its own mailer, cannot send); the test-email buttons say "Not sent" instead of claiming success; `phpunit.xml` runs the whole suite with it off. All mail paths were enumerated: they funnel through `applyMailConfiguration` (guarded) or the default mailer. SMS/Fast2SMS/WhatsApp have exactly three curl sites, all guarded.
- **Credentials: NOT ROTATED (BLOCKED on provider dashboards).** All 10 stored secrets are encrypted; none plaintext; `.env` is untracked. No stored secret value appears in the application log. The society-1 Fast2SMS key is duplicated in `.env`; WhatsApp runs through Fast2SMS so SMS and WhatsApp share one key by design. Checklist + baseline fingerprints: [CREDENTIAL_ROTATION.md](CREDENTIAL_ROTATION.md); verify later with `php artisan credentials:audit` (read-only, never prints a secret). Scratch DB copies holding credentials were deleted; the 7 backup snapshots still contain the old encrypted values.
- **Booking conflicts.** Dev bookings 23/30 (and 22/28) are February pending requests overlapping approved ones, filed before create/edit conflict checks existed. They are kept as history. The *approval* step had no check at all: web and API now refuse to approve or reopen a booking onto a held slot (`FacilityBookingService::transitionConflict`).
- **Event WhatsApp** is wired through the existing `WhatsAppPurposes` registry (new `event` purpose), sent only when a manager has attached an approved template and WhatsApp is on. **Event SMS was deliberately not implemented** (generic DLT template + existing toggles would start texting owners about every event). Live delivery NOT TESTED.
- **Test-data state re-verified** against the live dev DB (30 checks, ids/counts only): Piyus -> villa 159 only, villa 160 -> Suraj, #8-#11 absent, other societies' row counts identical to the original inventory, no duplicate identities, orphans, invalid categories or cross-society links.
- **A flaky test found and fixed:** `SocietyDocumentTest` listed every file on a fake disk whose directory is shared by all PHPUnit processes, so sharded runs could see another shard's file. Scoped to its own society folder.

### Final gate (latest code)

| Check | Result |
|---|---|
| Laravel, SQLite, 8 shards | **PASS** 1473 tests / 6317 assertions, 0 failures, 4 skipped (engine-specific) |
| Laravel, MariaDB 10.4.32 (throwaway :3307), 6 shards | **PASS** 1473 tests / 6315 assertions, 0 failures, 2 skipped (engine-specific) |
| `phpunit --list-tests` | 1473 = 1473 on both engines |
| Flutter | **PASS** 162 tests; analyze 2 info; no Flutter file changed this session |
| Android debug APK | not rebuilt (no Flutter change since the last successful build). **Device run: NOT TESTED** |
| Real email / SMS / WhatsApp delivery | **NOT TESTED** (no rotated credentials, no approved recipients) |
| Original XAMPP MariaDB data dir | untouched |

**Release status: READY FOR STAGING, NOT READY FOR PRODUCTION.** Critical blockers: credential rotation (P1-89/P2-33), real-device Android run, delivery checks with approved recipients, MariaDB on the original XAMPP data dir.


## 2026-10-21 evening - notice notification root causes (see docs/NOTIFICATION_MATRIX.md "Notice publication")
- Verified: full suite 2425 tests / 14382 assertions / 0 failures / 0 errors / 6 skipped, 8/8 shards exit 0; Flutter 265 pass, analyze 2 info.
- Fixed: stale pre-2026-10-17 "sent" e-mail rows no longer suppress a notice e-mail; SMS/WhatsApp test seams no longer bypass the allow-list. Test: `NoticeOwnerNotificationTest`.
- Open: notice send-email/send-SMS checkboxes are not read by listeners (product decision); no active SocietyFlow WhatsApp `notice` template; SMS Message IDs 228126/228199 unverified (mobile forgot-password/login OTP depend on 228126).
- Local `.env` now has EXTERNAL_MESSAGING_ENABLED=true with a 2-recipient allow-list (approved by the user); nothing has been sent.

## 2026-10-21 night - pre-deployment audit round
- Verified: full suite SQLite 2444 tests / 14478 assertions / 0 failures / 0 errors / 6 skipped (8/8 shards exit 0); MariaDB 10.4 (isolated `societyflow_test_N`) 2444 / 14477 / 0 / 0 / 3 skipped (8/8 exit 0). Flutter 265 pass, analyze 2 info. Android release APK builds (debug-signed, `com.example.societyflow`).
- Notices: Send Email / Send SMS boxes honoured (default on for new, edit keeps saved, edit of a published notice sends nothing); admin "Send this notice again" (channels, confirm, token, audit log). WhatsApp unchanged (blocked).
- Found on MariaDB: `email_logs.status` was still an ENUM -> new migration `2026_10_21_000001_widen_email_logs_status_on_mysql.php` (NOT applied to any dev/prod DB - awaiting approval; tested on a scratch MariaDB: rows identical, indexes kept).
- Route closures replaced (`Api\HealthController`, `Route::view`, `RedirectController`): `route:cache` now succeeds (558 routes cached, cleared again).
- Accounts on default passwords: docs/DEMO_ACCOUNTS.md (32; nothing changed).

## 2026-10-21 late - dev migration applied, HTTP walkthrough
- Dev SQLite: migration `2026_10_21_000001` applied (no-op on SQLite; only the `migrations` table gained a row). Verified backup first: `storage/backups/pre-migration-20261021-000001-20261021-204152.sqlite`, SHA-256 3FFA42224B3B73AE8694C6A617F24B0B769E8CE459D3E6FFD3D870DEDA650438, integrity ok, identical to live. Production NOT migrated.
- Walkthrough (HTTP, on a throwaway copy of the dev DB, messaging off): 8 roles x 124 pages 0 server errors, 190 cross-society record probes 0 leaks, API 5 roles 0 server errors; notice publish/edit/resend verified. No browser DOM, no device.
- Fast2SMS 228126/228199 still unverified: the app only has read-only lookups for WhatsApp, none for SMS DLT templates - needs the DLT panel text.

## 2026-10-22 - defect-fix round
- Full suites after all fixes: SQLite 2472/14633/0/0/6, MariaDB 10.4 2472/14632/0/0/3 (8/8 shards exit 0 each). Detail, defect register and blockers: docs/RELEASE_READINESS_2026-10-21.md. Verdict NOT READY (providers, device, MySQL 8, production config unverified).
