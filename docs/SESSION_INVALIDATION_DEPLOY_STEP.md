# One-time web-session invalidation at deployment (NOT executed - prepared for the operator)

**Why.** From this release a password change ends the user's *other* web sessions (`AuthenticateSession`, Laravel 10.50). That protection only
attaches to a session the first time it makes a request after the deploy: a session that already existed adopts whatever password is current at that
moment. A session stolen *before* the deploy therefore survives a password change made *before its first post-deploy request*. Flushing the existing
web sessions once, at deploy time, removes that window. Mobile API tokens are separate (see below) and are not touched by this step.

**Nothing here has been run.** The production session driver, its storage and whether several servers share it are **UNVERIFIED** - that is step 1.

## 1. Verify (read-only, on the production host)
1. `php artisan tinker --execute="echo config('session.driver');"` (or read `SESSION_DRIVER` in the production `.env`; never paste the file anywhere).
2. Record: driver (`file` / `database` / `redis` / `cookie`), `SESSION_CONNECTION` / table, `SESSION_PREFIX`, and **how many app servers** serve users.
   - `file` on several servers = each server has its own sessions (or a shared mount): flush every one.
   - `database`: the `sessions` table of the production database (the same database the app uses).
   - `redis`: which Redis DB and key prefix the sessions live under (do not flush a DB shared with the cache or queue).
   - `cookie` (sessions inside the cookie): a flush is not possible server-side; this release then cannot revoke existing cookies - stop and ask.
3. Confirm a backup/snapshot exists (the step deletes only session data, never users or records).

## 2. Procedure (low-traffic window; announce a short re-login)
| Driver | Command |
|---|---|
| any | optional: `php artisan down --retry=60` before, `php artisan up` after |
| file | on each server: `find storage/framework/sessions -type f ! -name .gitignore -delete` |
| database | `DELETE FROM sessions;` (or `TRUNCATE TABLE sessions;`) in the production database |
| redis | delete only the session keys: `redis-cli -n <session db> --scan --pattern '<prefix>*'` piped to `DEL` (check the pattern with the scan first) |

Order: deploy code -> `php artisan migrate --force` (includes `2026_10_22_000001_bind_mobile_otps_to_account`) -> `php artisan config:cache route:cache view:cache` -> **flush sessions** -> `php artisan queue:restart` -> `php artisan up`.

## 3. Expected user impact
- Every signed-in **web** user is signed out once and signs in again. Forms open at that moment show "page expired" on submit.
- **Mobile app users are not signed out** (API tokens are not sessions).
- Remember-me cookies stop working once (their embedded password hash is re-checked) and are re-issued at the next sign-in.
- Impersonation ("login as society admin") sessions end; the Super Admin starts again.

## 4. Verify afterwards
1. A fresh login works; the session store grows again.
2. On a **test account**: sign in on two browsers, change the password in one, reload the other -> it lands on the login page.
3. `storage/logs` shows no burst of `AuthenticationException` beyond the expected redirects.

## 5. Mobile tokens (separate decision, unchanged by this change)
Sanctum `expiration` is `null`: a mobile token lives until a password event, logout, or the account being made inactive/suspended (tokens are now
deleted at that moment). Expiry policy is its own task - see the report from the authentication round.
