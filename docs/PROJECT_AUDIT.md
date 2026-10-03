# SocietyFlow — project audit

**Date:** 2026-09-16. Scope: full-codebase P0 sweep requested in the
"lead architect" brief. See [KNOWN_ISSUES.md](KNOWN_ISSUES.md) for the
itemised findings this audit produced and [CURRENT_STATUS.md](CURRENT_STATUS.md)
for what has actually been fixed and verified so far.

This file records what was *checked* and how, so a later session does not
re-run the same sweep from zero. It is not a list of recommendations to
action blindly — every finding here was read in the source before being
counted, per this repo's own standing rule (see
`CrossSocietyAuthorizationGapsTest`'s docblock: "a grep for 'no scoping
call' produces false positives ... every case here was verified to be a
real gap, not a naming mismatch").

## 1. Static validation — all green

| Check | Command | Result |
|---|---|---|
| PHP syntax, every tracked file | `for f in $(git ls-files '*.php'); do php -l "$f"; done` | 0 errors |
| Composer manifest | `php composer.phar validate --no-check-publish` | valid |
| Route table | `php artisan route:list` | 399 routes, no errors |
| Config cache | `php artisan config:clear` | clean |
| App cache | `php artisan cache:clear` | clean |
| Blade compilation, every view | `php artisan view:cache` | compiles clean |
| Migrations | `php artisan migrate:status` | 22+ ran, 0 pending |
| Flutter dependencies | `flutter pub get` (in `societyflowmob/`) | resolves clean |
| Flutter static analysis | `flutter analyze` | 4 info-level deprecations only (documented, intentional — see §3) |
| Flutter unit/widget tests | `flutter test` | 21/21 passing |
| Backend test suite | `./vendor/bin/phpunit` | see CURRENT_STATUS.md for the exact count as of the latest run |

No PHP parse errors, no Dart compile errors, no missing providers, no
broken imports, no orphaned routes were found in this pass.

## 2. Git repository health

The repository was found with a **corrupted branch ref**
(`.git/refs/heads/production-readiness` was 41 NUL bytes — a Windows crash
mid-write) in the prior session on this branch. It was repaired by
restoring the tip commit from `.git/logs/refs/heads/production-readiness`.
`git fsck --connectivity-only` is clean. If this recurs, the reflog is the
recovery path — the commit objects themselves are untouched by a ref
corruption.

## 3. Flutter / Android build

`flutter build apk` (and therefore `--debug` and `--release`) **cannot run
in this environment**. `flutter doctor -v` reports:

```
X No Java Development Kit (JDK) found
```

Android Studio's bundled JBR exists at
`C:\Program Files\Android Studio\jbr\bin\java.exe` but is itself broken —
`jbr\lib\` contains only a `modules` file (no `jvm.cfg`, no `server\`), so
`java -version` fails with `could not open 'jvm.cfg'`. This matches what a
prior session recorded as P0-9 and confirms it is still unresolved; it was
not silently re-broken by this session. Fixing it means reinstalling
Android Studio's JBR or a standalone JDK 17 with `JAVA_HOME` set — an
environment repair, not a code change, and not attempted here without
being asked.

**Everything that does not need the Android toolchain was actually run**,
not assumed from prior notes: `pub get`, `analyze`, `test` all executed
fresh in this session (§1).

## 4. Authorization sweep

Targeted read of every controller method that accepts a route-bound
Eloquent model (`show`/`edit`/`update`/`destroy` and equivalents), looking
specifically for CLAUDE.md §3's documented failure mode — a check written
as `if ($user->hasRole('Admin') && ...)`, where every role the condition
does not name falls through unscoped.

**Confirmed exploitable (fixed and tested this session — see
CURRENT_STATUS.md):**
- `PaymentController::store()` / `update()` — cross-society IDOR
- `PaymentController::index()` / stats query — Guard/Accountant unscoped listing
- `NoticeController::show()` — leaked to every role (5 sibling methods hardened defensively, not independently exploitable)

**Same-shaped check, confirmed NOT exploitable, hardened anyway:**
- `UserController::edit()/update()/destroy()` — every call site is already
  behind `authorizeRole('Super Admin', 'Admin')`, so no third role ever
  reached the check. Rewritten from `hasRole('Admin') && ...` to
  `!hasRole('Super Admin') && ...` so it no longer depends on that gate
  staying as it is, and so a user holding both roles is still exempt.
  `UserControllerSocietyIsolationTest` (5 tests) proves both the before and
  after states.

**Controllers checked and found already correct** (Super Admin properly
exempted, no third-role gap):
- `VisitorController::show()/edit()/update()` — uses the correct
  `!== ... && !hasRole('Super Admin')` idiom throughout.
- `ComplaintController::show()/edit()/update()/destroy()` — already
  hardened by a prior session (`authorizeComplaintManagement()`), including
  a prior fix for `$complaint->update($request->all())` mass-assignment.

**A different-shaped gap found, not a security leak:**
- `EventController::show()/edit()/update()/destroy()` (and `index()`)
  check `$event->society_id !== $user->society_id` with **no Super Admin
  exemption at all**. Since Super Admin's `society_id` is `null`
  (CLAUDE.md §2), this fails closed: Super Admin cannot view, list, edit
  or delete an event in *any* society, which contradicts the "Super Admin
  is global" contract that `PaymentController`/`NoticeController` both
  honour correctly. Not a data leak — the opposite failure mode — and
  lower priority than the IDOR findings. Filed as `P1-25` in TODO.md
  rather than fixed in this pass; fixing it correctly touches `index()`
  and `create()` too and deserves its own test, not a rushed addition here.

**Not yet swept** (raise this count if continuing the audit):
`ServiceProviderController`, `FacilityBookingController` (already covered
by a prior session per `CrossSocietyAuthorizationGapsTest`), rent/utility
bill controllers, admin assignment controllers. ~90 controllers exist
total per CLAUDE.md; this pass covered the modules the current brief calls
out by name (Payments, Notices, Users, Visitors, Complaints, Events) plus
what a prior session's docs record as already audited. It is not a claim
that every controller in the app has been reviewed.

## 5. Property-type / UX audit

Already covered by a prior session and re-verified, not re-done from
scratch: the enums (`PropertyType`, `SocietyPropertyType`), `Society`
helper methods, `ResolvesSocietyPropertyType` trait, and
`partials/property-type-field.blade.php` component already implement the
brief's "don't ask again" rule correctly. Six forms have not yet been
converted to use that component and still show a one-option dropdown —
tracked as `P1-24` in TODO.md, not fixed in this pass (scope decision, see
CURRENT_STATUS.md's "Next recommended task").

## 6. What this pass did not attempt

Per the brief's own instruction to record blockers rather than fake
completion:

- **MySQL**: no MySQL/MariaDB server available in this environment. The
  full suite has run on MariaDB in a prior session per CURRENT_STATUS.md;
  it was not re-run here.
- **Payment gateway integration** (Razorpay/Cashfree): no credentials
  configured. `UpiPaymentService` (server-resolved amount/payee/VPA) is
  the payment-adjacent feature that exists; a real gateway integration
  needs sandbox credentials before it can be built and verified honestly.
- **Firebase Cloud Messaging**: `firebase_messaging` is absent from
  `pubspec.yaml` and `AppFeatures.enablePushNotifications=false` by
  design (CLAUDE.md §11). Not touched.
- **`flutter build apk`**: blocked by the JDK issue in §3.
- **Browser click-through verification**: not attempted this session;
  prior sessions recorded Chromium crashing in this environment.
