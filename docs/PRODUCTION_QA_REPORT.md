# SocietyFlow — production QA report

Property-type architecture and guard experience.
**Date:** 2026-09-12 · **Branch:** `production-readiness` · **Head:** `6aa8512`

What follows is what the environment actually proved. Anything the
environment could not exercise is marked **NOT VERIFIED** and listed in §11
and §12 rather than assumed to work.

---

## 1. Environment

| | |
|---|---|
| PHP | 8.2.12 |
| Laravel | 10.50 |
| Database under test | SQLite (in-memory for the suite, file for browser QA) |
| Flutter / Dart | 3.41 / 3.11 |
| Browser | Chromium via Playwright, desktop and 390×844 phone viewport |
| Server | `php artisan serve --port=8123` |

**MySQL was never exercised.** No server is available in this environment.
See §11.

## 2. Automated tests

| Suite | Result |
|---|---|
| Backend (`./vendor/bin/phpunit`) | **260 passed, 674 assertions, 0 failures** (11m35s) |
| Flutter (`flutter test`) | **21 passed** |
| `flutter analyze` | 4 informational, unchanged from baseline |
| `composer validate` | valid |
| `php artisan route:list` | 400 routes |
| `migrate:status` | 70 applied, **0 pending** |
| `php -l` on every changed file | clean |

Tests added during this work: **23**.

| File | Tests | Covers |
|---|---|---|
| `PropertyTypeEnforcementTest` | 7 | §2, §16 — backend rejects unsupported types |
| `GuardGateTest` | 10 | §8, §16, §19 — gate behaviour and isolation |
| `OwnerPropertyViewTest` | 3 | §5, §7 — no meaningless cards |
| `SettingsPageIntegrityTest` | 3 | §23 — markup integrity |

Existing property-type suites (`SocietyPropertyTypeTest` 18,
`ApiSocietyConfigTest` 4, `PropertyTypeNavigationTest` 4,
`PropertyTypeDashboardTest` 5) all still pass.

## 3. Browser QA (§23)

Chromium, real sessions, console watched throughout.

| Role | Pages | Result |
|---|---|---|
| Super Admin | dashboard, settings (all tabs), visitors, complaints, payments, facilities, events | 200, console clean |
| Admin | dashboard, owners, owners/create, visitors, guard, complaints, payments, facilities, events, reports/financial, settings | 200, console clean |
| Villa Owner | dashboard, visitors, complaints, payments, facilities, events | 200, console clean |
| Tenant | dashboard, visitors, complaints, payments, facilities, events | 200, console clean |
| Apartment Owner | dashboard | 200, console clean |
| Guard | gate, visitor registration, history | 200, console clean |

**No blank pages, no 500s, no fatal JS errors** on any page above.

### Guard authorization, checked in the browser

A logged-in Guard requesting admin surfaces directly:

| Path | Result |
|---|---|
| `/owners` · `/users` · `/settings` · `/reports/financial` · `/super-admin/dashboard` · `/societies` | **403 Forbidden** (all six) |

### Guard gate lifecycle, end to end

Registered → approved → checked in → checked out, on a 390×844 viewport:

```
counters: 1 Waiting → 1 Expected → 1 Inside → 1 Departed
flash:    "… checked in."  /  "… checked out."
history:  Departed
```

No horizontal overflow at 390px. No console errors.

### Unified property search (§9)

One search box in a mixed society returned both, correctly labelled:

```
Tower A / Flat 101          (apartment)
Villa V10 - Green Valley Villas   (villa)
```

The guard is never asked which kind of property they are looking at.

## 4. Defects found and fixed during QA

| # | Defect | Severity | Where |
|---|---|---|---|
| 1 | Guard fell through to **"Default: Admin dashboard"** — gate staff saw monthly revenue, pending dues, occupancy, resident counts | High | `DashboardController::index` |
| 2 | `RentController` / `TenantController` validated `in:apartment,villa` regardless of society, so an apartment-only society accepted a villa tenant from any client bypassing the form | High | both controllers |
| 3 | Stray unclosed `<script>` swallowed the **Language, Storage and Currency** settings tabs — they never rendered, and the page threw `Unexpected token '<'` | Medium | `settings/index.blade.php` |
| 4 | Complaint form selected by `action*="complaints.store"` (a route *name*; the attribute holds a URL) — validation and double-submit protection had never run | Medium | `complaints/create.blade.php` |
| 5 | `owners/edit` offered both property types unconditionally | Medium | `owners/edit.blade.php` |
| 6 | Seven forms offered both types regardless of society | Medium | 7 views |
| 7 | Owner portfolio card showed "Apartments: 0" in a villa township | Low | `owner/dashboard.blade.php` |
| 8 | Flutter visitor form asked for "Flat / Villa" in every society | Low | `visitors_screen.dart` |

Defect 1 is the one worth noting: `Guard` existed as a role with **no
controller, no route and no view anywhere in the codebase**, so it matched
no branch and inherited the administrator's dashboard by default.

### Mistakes made and corrected during the work

- `GuardController` was first written against `approval_status = 'approved'`.
  The schema was repaired at some point to `pending|allowed|denied`; the
  original migration said `approved|rejected`. The test caught it via a
  CHECK-constraint violation. This is the trap CLAUDE.md §5 documents.
- A browser probe reported a submit failure that turned out to be the
  harness clicking the layout's logout button — `button[type="submit"]`
  matched it first. Harness bug, not an app bug.

## 5. Security verification (§16)

| Control | How it was proved |
|---|---|
| Property type cannot be forged past the form | `PropertyTypeEnforcementTest` POSTs `villa` to an apartment-only society directly — rejected |
| `society_id` is never taken from the request | Forged `society_id` naming a permissive society still rejected |
| Guard cannot read another society's gate | Search returns only own society; foreign visitor check-in → 403 |
| Guard cannot register against a foreign property | → 403, nothing written |
| Route-model-bound visitors are authorized | `authorizeGateAccess()` on every mutation |
| Guard cannot reach administration | Six admin paths → 403 in the browser |
| Residents cannot open the gate console | Tenant → 403 |

Every guard query is pinned to `auth()->user()->society_id` with **no Super
Admin widening** — a deliberate departure from the admin controllers.

## 6. Performance (§19)

| Check | Result |
|---|---|
| Gate dashboard counters | one grouped query, not four counts |
| Gate dashboard lists | eager-loaded; asserted **< 25 queries with 8 visitors** present |
| Admin dashboard property counts | one grouped query (asserted) |
| Property search | capped at 15 rows, narrow `select()`, debounced client-side with in-flight abort |
| Schema introspection in requests | none added |
| `Cache::flush()` in requests | none added |

## 7. Data safety (§14)

No table was dropped, renamed or rewritten. `flats` already held both
property kinds discriminated by `property_type`, so the existing schema was
sufficient — no migration of resident data was required.

Backfill on the dev database (evidence-based, ambiguity preserved):

| Society | Data | Assigned |
|---|---|---|
| Urban Heights | 77 apartments, 15 villas | `both` |
| Sahgun Villa Estate | 6 apartments, 0 villas | `apartment` |
| Royal Prime Township | 21 apartments, 0 villas, 1 empty villa area | `both` |
| Royal suncity | ambiguous | `both` |

⚠️ **Two of these need an administrator's eye**, and the system deliberately
did not guess:

- **Royal Prime Township** was described in the brief as a villa society.
  Its data says 21 apartments and zero villas. Setting it to `villa` would
  have hidden all 21. It is `both` until someone who knows the estate
  decides.
- **Sahgun Villa Estate** is named for villas but contains only apartments.

Both are one click to correct on the society configuration screen. Nothing
is deleted when a society is narrowed — records outside the new type are
hidden, and the owner edit form keeps such a value visible and flagged
rather than silently rewriting it.

## 8. What was not implemented

Scoped out deliberately, not attempted and abandoned:

- **§8 capabilities 4–6, 10** — notify resident, request approval,
  approve/deny from the gate, and visitor photo capture. Approval today is
  the resident's action through the existing visitor module; the gate shows
  the queue and admits, but does not itself decide. Photo capture requires a
  device camera this environment cannot test.
- **§8 capability 16** — blacklist / suspicious-visitor reporting. There is
  no blacklist table; adding one is a schema change beyond the property-type
  brief.
- **§10 property abstraction** — not undertaken. `flats` *is* already the
  common Property concept the brief describes. Introducing a polymorphic
  layer would have risked production data for no behavioural gain, which
  §10 explicitly warns against.
- **§12 Flutter guard screens** — the mobile app has a guard visitor log;
  a dedicated Flutter gate dashboard mirroring the web one was not built.

## 9. Production cleanup audit (§24)

| Check | Finding |
|---|---|
| `dd()` / `dump()` / `var_dump()` in `app/` or `routes/` | **none** |
| Debug or demo routes | none — the `settings/*/test` routes are real features (test SMTP/SMS/storage) |
| Hardcoded credentials | none; `TemporaryPassword::generate()` is used throughout |
| Localhost / dev URLs | only stock Laravel `env()` defaults in `config/*` — correct, must stay |
| `console.log` in views | 106 occurrences, pre-existing |
| Unrouted controllers | 3 candidates (below) |

Three controllers have **no reference in routes, app code, views or config**:

```
app/Http/Controllers/InstallController.php               (5.9 KB)
app/Http/Controllers/Admin/FirebaseSettingsController.php
app/Http/Controllers/SuperAdmin/SuperAdminController.php
```

**Not deleted.** They are reported, not removed: the brief's rule is to
delete only what is *proven* unused, and absence of a code reference does
not prove an operator has no use for an installer. Deleting them frees
nothing measurable; the finding is the value.

## 10. Tests executed / passed / failed

- **Executed:** 260 backend, 21 Flutter, plus browser QA across 6 roles
- **Passed:** 260 backend, 21 Flutter, all browser checks
- **Failed:** 0

## 11. NOT VERIFIED

These could not be exercised here and must not be read as working:

| Item | Why |
|---|---|
| **MySQL** — the production engine | No server available. SQLite hides real bugs: unknown double-quoted identifiers become string literals instead of erroring, and `YEAR()`/`MONTH()` are MySQL-only. **Run the suite against MySQL before release.** |
| **`flutter build apk --release` / `appbundle --release`** | Blocked by P0-9: the Android Studio JBR is incomplete (`jbr/lib` has no `jvm.cfg`), and no other JDK is installed. The build fails instantly. |
| **On-device / emulator run** | No device or emulator in this environment |
| **FCM push delivery** | No Firebase credentials exercised |
| **SMTP delivery** | No mail server; `UserCreated` was not observed delivering a real message |
| **Guard flow on a real low-end Android device** | Verified only at a 390×844 Chromium viewport |
| **Concurrent gate use** | Not load-tested |

## 12. Production blockers

Carried forward; none are introduced by this work.

| # | Blocker | Owner |
|---|---|---|
| P0-3 | MySQL suite never run | engineering |
| P0-7 | `applicationId` is still `com.example.*` | operator decision |
| P0-8 | Release builds are debug-signed; no release keystore | operator decision |
| P0-9 | Android JDK broken — blocks all release builds | operator |
| — | 31 dev accounts still use `password` / `password123` | operator (`users:rotate-weak-passwords --force`) |
| — | `database.sqlite` remains in git history | operator decision |

## 13. Deployment steps

```bash
# 1. Verify against the real engine FIRST - this is P0-3
DB_CONNECTION=mysql DB_DATABASE=societyflow_test ./vendor/bin/phpunit

# 2. Deploy code, then install production dependencies
composer install --no-dev --optimize-autoloader

# 3. Apply the schema. Forward-only; never migrate:fresh on live data.
php artisan migrate --force
#    Adds societies.property_type (NOT NULL, default 'both', indexed) and
#    backfills only where the evidence is unambiguous.

# 4. REVIEW THE BACKFILL before announcing the feature.
#    Societies left as 'both' were ambiguous by design - see §7.
#    Correct them at /super-admin/societies/{id}/edit.

# 5. Cache for production
php artisan config:cache && php artisan route:cache && php artisan view:cache

# 6. Storage
php artisan storage:link   # or: php artisan storage:setup

# 7. Rotate weak credentials (operator decision - reports by default)
php artisan users:rotate-weak-passwords          # report
php artisan users:rotate-weak-passwords --force  # rotate

# 8. Mobile - blocked until P0-7, P0-8, P0-9 are resolved
cd societyflowmob && flutter build appbundle --release
```

**Rollback:** the migration's `down()` drops the column. Nothing else in
this work alters schema, so reverting the code is sufficient.

---

## Statement on correctness

This work is **not** claimed to be bug free. It is claimed that 260 backend
tests and 21 Flutter tests pass, that the flows in §3 were driven in a real
browser and behaved as described, and that the eight defects in §4 were
found, fixed and covered by tests. MySQL, release builds, devices, FCM and
SMTP were not exercised at all — §11 lists exactly what that leaves open.
