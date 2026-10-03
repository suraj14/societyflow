# SocietyFlow — Final Production Readiness

**Date:** 2026-09-29. Scope and hard constraint stated up front: **this
session has no SSH, hosting-panel, deployment-pipeline or file-system access
to `https://societyflow.joboapps.com`.** Every production finding below
comes from outbound, read-only HTTP requests made from this local session
(confirmed reachable) — never from server-side commands, which could not be
run there. Where the brief asked for `php artisan route:list`/`route:clear`/
`cache:clear` *on production*, those could not be executed; the exact
commands the operator needs are given instead, evidence-backed by what was
actually observed over HTTP plus a control test against the identical code
running locally.

---

## P0 finding: production `/api/v1/*` is unreachable (mobile-breaking)

**Status: 🔴 NOT FIXED — cannot be fixed without production server access.**
Diagnosed as precisely as HTTP-only probing allows; handed off below.

### Evidence

| Request (production) | Result |
|---|---|
| `GET /login` | 200 |
| `GET /sanctum/csrf-cookie` | 204 |
| `GET /api/v1/health` | **404** |
| `GET /api/v1/auth/login` | **404** |
| `GET /api/v1/settings` | **404** |
| `GET /api/v1/` | **404** |
| `GET /api/health` (no v1) | **404** |
| `GET https://api.societyflow.joboapps.com/v1/health` (subdomain guess) | unreachable (no such host) |

The `/api/v1/health` 404 body is Laravel's own styled error page (not a
generic web-server or CDN block page), and `cf-cache-status: DYNAMIC` with
no `Age` header on that response confirms Cloudflare is not serving it from
cache — it's a live response from the origin on every request. **The
request reaches the Laravel application; Laravel itself has no matching
route.**

### Control test — same code, run locally

```
GET http://localhost/SocietyFlow/api/v1/health      → 200 {"application":"societyflow","api":"v1","status":"ok"}
GET http://localhost/SocietyFlow/api/v1/auth/login  → 405 Method Not Allowed  (route exists, wrong verb - proof it's registered)
php artisan route:list --path=api                    → api/v1/* routes present, correctly grouped
```

`app/Providers/RouteServiceProvider.php` registers `routes/api.php` under
the standard Laravel 10 `api` middleware + `/api` prefix — textbook, no
defect. **This rules out a code-level bug in this repository.** The exact
same source, run locally, serves `/api/v1/*` correctly.

### Root cause — two candidates, cannot be distinguished without server access

1. **Stale `route:cache` on production (leading theory).** If
   `php artisan route:cache` was run against a deploy that predates these
   `/api/v1/*` routes, and no later deploy re-ran it, Laravel serves the
   cached route table forever and silently ignores the current
   `routes/api.php` — this produces exactly a clean 404 from a running
   Laravel app, matching everything observed.
2. **A reverse-proxy/CDN rule intercepting `/api/*` before Laravel.** Less
   likely given the 404 is Laravel's own styled page and is confirmed
   uncached/dynamic (see above), but not ruled out without seeing the
   Cloudflare dashboard or the web server vhost config.

### Exact commands for whoever has production access

Run on the production server, in this order, checking output between each
step (per this session's own explicit "do not claim fixed until verified"
instruction):

```bash
# 1. Diagnose first - do not skip to clearing caches blind.
php artisan route:list --path=api
php artisan route:list --path=api/v1
php artisan --version                 # confirm Laravel 10.x, matching this repo
git log -1 --oneline                  # confirm the deployed commit actually contains routes/api.php's /v1 group
cat routes/api.php | head -30         # confirm the file on disk matches this repo's routes/api.php

# 2. If route:list shows the routes ARE registered but requests still 404,
#    the problem is web-server/proxy level, not Laravel - check the vhost
#    config and any Cloudflare Page Rules for a path match on /api/*.

# 3. If route:list shows the routes are MISSING or route:list itself errors,
#    clear every cache and rebuild in this order:
php artisan optimize:clear
php artisan route:clear
php artisan config:clear
php artisan cache:clear
composer dump-autoload -o

# 4. Re-check before re-caching:
php artisan route:list --path=api/v1

# 5. Only once the routes appear correctly, re-cache for production:
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Required verification after any fix (not yet performed - no access)

| # | Check | Expected |
|---|---|---|
| 1 | `GET /api/v1/health` | 200, `{"application":"societyflow","api":"v1","status":"ok"}` |
| 2 | `POST /api/v1/auth/login` (bad credentials) | 401/422, not 404 |
| 3 | `GET /api/v1/dashboard` with no token | 401 |
| 4 | `GET /api/v1/visitors` with a valid Sanctum token | 200 |
| 5 | `GET /api/v1/complaints` with a valid Sanctum token | 200 |
| 6 | `GET /api/v1/dashboard` with a valid Sanctum token | 200 |
| 7 | Flutter app against production `baseUrl` | actual login + one authenticated screen load |

**None of the above has been run against production** — 1-6 need the fix
applied first; 7 additionally needs the Flutter SDK, which is separately
blocked (see below). This is stated plainly rather than assumed passing.

---

## P0 finding: production cookie/HTTPS hardening

**Status: 🔴 NOT FIXED — same access constraint as above.**

| Check | Observed (production, live HTTP response) |
|---|---|
| HTTPS enforced | Yes — `http://` redirects 301 to `https://` |
| `Set-Cookie` on `societyflow_session` | `httponly; samesite=lax` present; **`secure` flag absent** |
| `Strict-Transport-Security` header | **absent** |
| `X-Frame-Options` / `X-Content-Type-Options` / `X-XSS-Protection` | present (but each appears **twice** in the raw header set, e.g. `nosniff,nosniff` - minor hygiene issue, suggests both Laravel and Cloudflare are setting the same header; not a vulnerability, worth a single source of truth) |

The missing `Secure` flag on the session cookie is consistent with this
repo's own `.env.example`/local `.env` having `SESSION_SECURE_COOKIE=false`
(confirmed in the local audit) - **if production's `.env` has the same
value**, that would fully explain it, but this was verified by inspecting
the actual `Set-Cookie` response header from production over the network,
exactly as instructed, not by assuming the local `.env` value applies
there.

**Action required (operator, on production):**
```bash
# In production .env:
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax        # already effectively lax; keep explicit

php artisan config:clear && php artisan config:cache
```
Then re-request any page and re-inspect the literal `Set-Cookie` header -
per this session's own instruction, config-file correctness alone does not
close this out; the network response after the change is the only proof
that counts, and that check could not be performed here (no access to
re-trigger a deploy).

**HSTS**: add at the edge (Cloudflare's own "Always Use HTTPS" +
HSTS setting in the SSL/TLS app settings) or via a `SecureHeaders`-style
middleware in Laravel if Cloudflare-level control isn't available. Re-check
`Strict-Transport-Security` on a live response afterward - not before.

---

## Backend (this repository, actually run)

- **Tests:** 586 (pending final re-confirmation this session — see below)
- **Assertions:** 1737
- **Failed:** 0 — **Errors:** 0 — **Skipped:** 0
- **Authorization:** `AuthorizationIsolationTest`, `CrossSocietyListingTest`,
  `CrossSocietyAuthorizationGapsTest`, `PaymentCrossSocietyWriteTest`,
  `PaymentListingScopeTest`, `NoticeShowIsolationTest`,
  `EventSuperAdminAccessTest` (new) — all passing. Not independently
  re-derived from scratch this session; re-run as part of the full suite.
- **Multi-tenancy:** society-scoping pattern (`!hasRole('Super Admin')`
  exemption) verified present in every controller checked this session; the
  one exception found (`EventController`) fixed and tested.
- **API:** `VisitorApiApprovalTest` (new, 7 cases) closes a real gap - the
  mobile approve/deny/check-in/check-out path had zero prior coverage.
- **Database:** SQLite dev DB clean; MySQL/MariaDB **not re-run this
  session** (last confirmed 472/1261 several sessions ago - a real, open
  gap, not silently dropped).
- **Security (local dev box):** `.env`/dev-SQLite/logs no longer web-
  exposed (prior session's `.htaccess` fix, re-verified still in effect);
  no hardcoded credentials, no debug statements, no debug routes (checked
  this session).
- **Performance:** `QueryPerformanceTest` passing; no fresh N+1 audit
  performed this session beyond what that suite already covers.

## Web (this repository)

Authentication/Dashboard/Visitors/Complaints/Bills/Payments/Notices/Events/
Facilities/Reports/Settings: all have passing feature-test coverage per the
full suite; **no fresh manual click-through was performed this session** -
this is stated as a real gap, not implied to be covered by unit tests alone.
Responsive/mobile-web layout: not evaluated this session.

## Flutter

- **Flutter analyze:** BLOCKED - no `flutter`/`dart` executable anywhere on
  this machine (checked again this session).
- **Flutter test:** BLOCKED - same reason.
- **Android build:** BLOCKED - same reason.
- **Real device:** BLOCKED - no device or SDK available.
- **API/Auth/Visitors/Complaints/Bills/Payments/Notices/Events/Facilities/
  Notifications:** BLOCKED - cannot be exercised without the SDK, and even
  if it could, production's own API is currently unreachable (see above),
  so a production connectivity test would fail regardless of the SDK.
- Correction from the brief's assumed path: the mobile project here is
  `C:\xampp\htdocs\SocietyFlow\societyflowmob`, not
  `D:\xampp\htdocs\SocietyFlow\societyflow`.

## Production

- **HTTPS:** working (redirect confirmed).
- **SSL:** Cloudflare-terminated, confirmed reachable over TLS.
- **Session:** cookie missing `Secure` flag - **not fixed** (no access).
- **Queue/Scheduler/Storage/Database/Mail/Backup/Monitoring/Logs:** **not
  inspected** - every one of these needs server-side access (SSH, a queue
  worker's process list, `storage/logs`, a mail-send test, a backup
  listing) that this session does not have. Not guessed at; left explicitly
  unverified rather than assumed fine.
- **API routing:** broken (`/api/v1/*` 404) - see above, the session's
  single most important finding.
