# SocietyFlow — known issues

Open findings only. Fixed-and-verified items move to CURRENT_STATUS.md's
"recently fixed" section and TODO.md gets a `done (verified <commit>)` row;
don't duplicate them here. Severity follows CLAUDE.md's P0–P3 scale.

**Last updated:** 2026-09-16

## P0 — none currently open

The `&&`-short-circuit IDOR class (CLAUDE.md §3) was swept across
Payments, Notices and Users this session; all confirmed-exploitable
instances are fixed and tested (see CURRENT_STATUS.md). See
`docs/PROJECT_AUDIT.md` §4 for exactly which controllers were checked and
which were not, so a later pass knows where to continue rather than
re-checking the same ground.

## P1

- **`P1-25` — EventController fails Super Admin closed, not open.**
  `show()/edit()/update()/destroy()/index()` all compare
  `$event->society_id !== $user->society_id` with no `hasRole('Super Admin')`
  exemption. Super Admin's `society_id` is `null` (CLAUDE.md §2), so the
  comparison is true for every event and Super Admin is blocked from every
  event in every society — the opposite of the documented "Super Admin is
  global" contract that `PaymentController`/`NoticeController` correctly
  honour. Not a security leak (fails closed). Fix needs `index()` and
  `create()` touched too, plus a test proving Super Admin can act across
  societies without granting anyone else cross-society access — scoped as
  its own task rather than folded into this session's IDOR sweep.
- **`P1-24` — six forms still show a one-option property-type dropdown.**
  `admin/rents/create`, `admin/tenants/create`, `payments/create`,
  `facility-bookings/create`, `residents/create`, `utility-bills/index`
  include the raw `partials/property-type-options` instead of
  `partials/property-type-field.blade.php`, which already implements the
  brief's "don't ask again" rule. Each view's unit-list JS fires from the
  select's `onchange`, which never fires once the field is hidden — the
  component's `$sfOnChange` hook exists for exactly that and needs wiring
  per view. See TODO.md P1-24 for the full description.
- **Android release builds blocked (`P0-9` in TODO.md).** Android Studio's
  bundled JBR is missing `jvm.cfg`/`server\` under `jbr\lib\` (only
  `modules` present, ~64 MB vs the ~300 MB a working JBR needs).
  `flutter doctor -v` confirms no JDK is otherwise on `PATH`/`JAVA_HOME`.
  Re-verified in this session, not just carried over from notes — see
  `docs/PROJECT_AUDIT.md` §3. Needs an operator to reinstall Android Studio
  or install a standalone JDK 17; not something to attempt from an agent
  session without being asked, since it changes machine-wide state outside
  the repo.
- **MySQL/MariaDB not available in this environment** to re-run the P0-3
  MySQL suite pass. A prior session ran it successfully on MariaDB
  10.4.27; this session could not re-verify it (no server running here).

## P2 / P3

See TODO.md's P2 table for schema-parity and polish items (constraint
gaps between SQLite and MySQL, deprecation cleanup, etc.) — not
duplicated here.

## Explicitly checked and found NOT to be issues

Recorded so a future audit doesn't re-spend time on these:

- `VisitorController` show/edit/update — correct Super Admin exemption
  throughout, no IDOR.
- `ComplaintController` show/edit/update/destroy — already hardened by a
  prior session (`authorizeComplaintManagement()`), including a prior fix
  for unrestricted mass assignment.
- `UserController` edit/update/destroy — the `hasRole('Admin') && ...`
  pattern was present but not exploitable (every call site is gated by
  `authorizeRole('Super Admin', 'Admin')` first); hardened anyway this
  session, see CURRENT_STATUS.md.
- Flutter: `pub get`, `analyze`, `test` all run clean in this environment.
  Only the native Android build step is blocked.
