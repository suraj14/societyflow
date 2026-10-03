# SocietyFlow — current status

Update this after every major task. Keep it short; link, don't paste.

**Last updated:** 2026-10-03 (session 13 — release blockers, timezone, Android bring-up, 8-card dashboard)

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

- `flutter analyze`: 4 informational (intentional deprecations); `test` 21/21
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
