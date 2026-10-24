# Production deployment checklist

Items marked **BLOCKING** must be done before the application faces real
residents. They are the things this codebase cannot do for itself.

---

## BLOCKING — credentials

- [ ] **Rotate every account created before the default-password fix.**

      OwnerController and UserController used to assign one hardcoded
      password (`password` / `password123`) to every account they created
      and print it on screen. 31 accounts in the development database
      still carry one, including `superadmin@societyflow.com` and
      `admin@societyflow.com`. The mobile API accepts email + password
      with no second factor, so an email address is a login.

      ```bash
      php artisan users:rotate-weak-passwords            # report only
      php artisan users:rotate-weak-passwords --force    # rotate + email
      ```

      `--force` emails each user a new temporary password and revokes
      their API tokens. Confirm mail is working first, or affected users
      are locked out.

- [ ] **Treat the committed database as compromised.** `database/database.sqlite`
      was tracked in git with real resident PII and those password hashes.
      It is untracked now, but it remains in history. Purging history is a
      rewrite and needs a deliberate decision — coordinate with anyone
      holding a clone.

- [ ] Generate a fresh `APP_KEY` for production; do not reuse the
      development one.

## BLOCKING — environment

- [ ] `APP_ENV=production`, `APP_DEBUG=false`.

      Note the development `.env` already says `APP_ENV=production` on a
      local XAMPP box with a LAN `APP_URL` and SQLite. That is
      mislabelled, not configured — verify the real production values
      rather than trusting the flag.

- [ ] `APP_URL` set to the real HTTPS origin.
- [ ] `SESSION_SECURE_COOKIE=true` (currently `false`).
- [ ] `CORS_ALLOWED_ORIGINS` set to the real origins. It defaults to `*`.
- [ ] `DB_CONNECTION=mysql` with production credentials.
- [ ] `QUEUE_CONNECTION` set to a real driver and a worker running.
      It is `sync`, so every email/SMS/WhatsApp send blocks the HTTP response.
      `config/queue.php` must exist (it was missing until 2026-10-08, which
      made every queued email silently vanish into `NullQueue`); verify with
      `php artisan tinker --execute="echo get_class(app('queue')->connection());"`
      - it must not print `NullQueue`.
      Worker (keep alive with systemd/supervisor):

      ```bash
      php artisan queue:work --tries=3 --timeout=90
      ```
- [ ] `LOG_LEVEL=error` and log rotation configured.
- [ ] `TRUSTED_PROXIES` set when the app sits behind nginx / a load
      balancer / Cloudflare (comma-separated IPs or CIDRs, or `*` when the
      app is reachable only through the proxy). Without it the app never
      sees HTTPS, so secure cookies and `https://` URLs are not produced.
- [ ] HTTPS everywhere. `SecurityHeaders` sends HSTS only when
      `APP_ENV=production` **and** the request is HTTPS.
- [ ] Sanctum tokens never expire (`config/sanctum.php` `expiration` is
      null). Password change/reset revokes them; decide whether a hard
      lifetime is wanted before launch.

## Timezone (India)

Storage stays in `APP_TIMEZONE`; every calendar rule (today, this month,
overdue, access-pass hours, the 09:00 / 18:00 bill reminders) runs on the
society's own timezone through `App\Support\SocietyClock`, which reads
`societies.timezone` (default `Asia/Kolkata`, editable in Settings → App
Settings → Time Zone).

- [ ] **Fresh database:** set `APP_TIMEZONE=Asia/Kolkata` so stored
      timestamps read as IST everywhere, including raw `created_at`
      columns in views and exports.
- [ ] **Existing database that ran under UTC:** keep `APP_TIMEZONE=UTC`.
      Changing it would re-interpret every stored timestamp 5½ hours off.
      The society-timezone rules above are correct either way; only raw
      timestamp display stays in UTC.
- [ ] `SOCIETY_TIMEZONE` only needs setting if the default for societies
      with no timezone of their own should not be `Asia/Kolkata`.

## BLOCKING — mobile

- [ ] Set `ApiConfig.useProduction = true` and point `prodUrl` at the
      HTTPS origin. The app currently uses cleartext HTTP and discovers
      its server by scanning the LAN.
- [ ] Remove `android:usesCleartextTraffic="true"` from
      `android/app/src/main/AndroidManifest.xml` once the server is HTTPS.
- [ ] Sign the release build with a real keystore.

## Database

- [ ] Back up before migrating.
- [ ] `php artisan migrate --force`.
- [ ] Run the suite against MySQL as well as SQLite — see
      [TESTING.md](TESTING.md). SQLite hides a class of bug that is fatal
      on MySQL.
- [ ] Check money columns after migrating. The SQLite schema declares
      several as `NUMERIC(10,0)`. SQLite ignores the precision; MySQL will
      **truncate to whole units**. Verify `maintenance_bills` and
      `payments` amounts keep their decimals.
- [ ] `flats_old` is an orphaned leftover table. Confirm nothing reads it,
      then drop it manually.

## Deployment

> **CloudPanel (societyflow.joboapps.com):** the step-by-step process, the package builder, the server scripts (inspect, backup, dry-run apply, rollback)
> and the Nginx upload-execution rule are in [DEPLOYMENT_CLOUDPANEL.md](DEPLOYMENT_CLOUDPANEL.md). The list below is the generic checklist; do not run
> `php artisan storage:setup`, `db:seed` or `migrate:rollback` on production.

- [ ] `composer install --no-dev --optimize-autoloader`
- [ ] `php artisan config:cache route:cache view:cache`
- [ ] `php artisan storage:link` (or `php artisan storage:setup`).
      The existing symlink points at a POSIX-style path
      (`/d/xampp/...`) that will not resolve under a normal web server.
- [ ] `php artisan schedule:run` on cron — event status updates and bill
      reminders depend on it:

      ```
      * * * * * cd /path/to/SocietyFlow && php artisan schedule:run >> /dev/null 2>&1
      ```

      The two reminder jobs are pinned to `SOCIETY_TIMEZONE` (IST), so the
      server's own clock may stay UTC.
- [ ] `php artisan migrate --force` runs the one-time data repair
      (`2026_10_03_000001`): active tenancies lose stale move-out dates,
      `flats.status` is re-derived, and bills marked paid without a payment
      row get a clearly labelled `unrecorded`-method reconciliation payment.
      Re-run `php artisan data:integrity-audit` afterwards; it must report
      "All invariants hold."
- [ ] Confirm `storage/` and `bootstrap/cache/` are writable and that
      `storage/app/private/` is **not** web-reachable (Firebase
      credentials live there). The same applies to
      `storage/app/society-documents/` (society rules PDFs, served only
      through the authorising `/society-rules/{id}/file` routes) - include it
      in backups.

## Known gaps at time of writing

These are functional holes, not regressions. They were present before this
work and are not closed by it.

- **No payment gateway.** Razorpay and Stripe keys are collected and
  encrypted in settings, but nothing charges, checks out, or handles a
  webhook. All payments are manual data entry.
- **No subscription enforcement.** `SocietyMiddleware` is not registered
  and no route uses it, so no society is gated on plan or expiry. The core
  SaaS constraint does not exist yet.
- **No mobile push.** The server now targets the FCM v1 API correctly, but
  the Flutter app has no `firebase_messaging` dependency and
  `AppFeatures.enablePushNotifications` is `false`. A guard registering a
  visitor notifies nobody on mobile — the resident must open the app to
  see a pending approval.
- **Web Push (VAPID) is not actually working, found during the 2026-10-07
  certification pass — this line previously (incorrectly) called it "the
  only working channel".** `PushNotificationService::computeECDH()` is a
  placeholder (`hash('sha256', $privateKey . $publicKey, true)`), not real
  elliptic-curve Diffie-Hellman key agreement — the AES-128-GCM key it
  derives is wrong, so a subscribed browser can never decrypt the payload,
  regardless of how correctly VAPID keys are configured. No test covers
  this path and no `minishlink/web-push`-equivalent dependency is
  installed. Not fixed this round: a correct implementation (real
  `openssl_pkey_derive()` ECDH + RFC 8291 HKDF + the spec's padding
  delimiter byte) can only be proven correct against a real subscribed
  browser receiving and decrypting a real notification - no browser
  automation was available to verify a blind rewrite. Recommended fix:
  install `minishlink/web-push` (a maintained, spec-correct library)
  rather than hand-rolling the crypto again.
- **Ownership resolution is partly heuristic.** `flats.owner_id` is unset
  on most rows (108 of 121 in development), so `UserFlatResolver` falls
  back to matching the owner record's recorded building/flat or villa
  area/number against the flat. Renaming a unit detaches a resident from
  their bills and visitors. **There is no automatic backfill command** —
  an earlier note pointed at `owners:backfill-flat-links`, which was never
  built. Note also that an unset `owner_id` usually means the unit has no
  owner yet rather than a broken link; only a person who knows the estate
  can tell the two apart, so a backfill must come from records of actual
  ownership and never from matching addresses or names (P1-7).
- **Three overlapping identity models.** `residents` (legacy), `owners`
  and `tenants` all describe a person attached to a unit, plus
  `users.owner_id` / `users.tenant_id`. Consolidating them is the largest
  remaining piece of structural work.
- **Document numbers can be reissued.** Deleting the most recent complaint
  or bill lets the next one reuse its number. Uniqueness among live rows
  is guaranteed; a durable high-water mark needs a counter table.
- **No FormRequest classes.** Validation is inline across ~90 controllers.
- **`RestrictStaffAccess` authorises by URL path prefix.** Any new route
  outside its denylist is implicitly granted to Staff.

## Added 2026-10-21 (pre-deployment audit)
- [ ] **Run `php artisan migrate --force` on MySQL** - includes `2026_10_21_000001_widen_email_logs_status_on_mysql.php`; without it every blocked/simulated e-mail fails to log (ENUM). Back up first.
- [ ] `route:cache` is now safe (no closure routes; `DeploymentCacheAndLogSchemaTest` guards it). Run `route:clear` after every deploy.
- [ ] Production `.env`: `APP_URL=https://societyflow.joboapps.com`, `SESSION_SECURE_COOKIE=true`, `APP_DEBUG=false`, `EXTERNAL_MESSAGING_ENABLED` and `EXTERNAL_MESSAGING_ALLOWLIST` deliberate (the switch stays false until a provider test is approved), a real queue (`QUEUE_CONNECTION=database` + a supervised `queue:work`) and the scheduler cron (`* * * * * php artisan schedule:run`).
- [ ] Android: create a release keystore (`android/key.properties`) and replace the `com.example.societyflow` application id before any store upload.
- [ ] Rotate the default-password accounts first (docs/DEMO_ACCOUNTS.md).

## Go/No-Go additions (2026-10-22) - read before uploading anything

Nothing below has been run against production. What production actually has (migrations applied, PHP extensions, `.env`, session driver,
storage layout, deployed build) is **UNVERIFIED**. Establish it first (section 0); do not assume it. The release verdict itself lives in the
audit report, not in this file.

### 0. Establish what production is (read-only, before planning any step)
- [ ] Run `php artisan migrate:status` on the production host (read-only) or `SELECT migration, batch FROM migrations ORDER BY id;` and save the
      output. The pending list is whatever production lacks - **do not assume which of this release's migrations already ran**, and compare against
      the whole `database/migrations/` folder, not only the four below.
- [ ] To preview SQL use `php artisan migrate --pretend --force`. Where `APP_ENV=production`, `migrate` (even with `--pretend`) stops at an
      "Application in production - are you sure?" prompt, and a non-interactive shell hangs on it. `--pretend` executes nothing; `--force` only skips the prompt.
- [ ] Record (names and versions only - never paste secret values): PHP version and `php -m`, the DB engine and `SELECT VERSION();`, `SESSION_DRIVER`,
      `CACHE_DRIVER`, `QUEUE_CONNECTION`, `FILESYSTEM_DISK`, and `ls -ld public/storage`.
- [ ] Identify the currently deployed build (commit hash, or a file manifest: `find . -type f -not -path './storage/*' -not -path './vendor/*' -exec sha256sum {} + | sort -k2 > /safe/prod-manifest.txt`)
      so a rollback target exists. Without it there is nothing to roll back to.

### 1. Migrations in this release (all additive; none rewrites existing rows)
New since the last commit (3787ceb). Production may be missing older ones as well - see section 0.

| Migration | What it does | Rollback |
|---|---|---|
| `2026_10_15_000003_facility_booking_payments` | adds payment/refund columns to `facility_bookings` | `down()` does nothing; old code ignores the columns |
| `2026_10_16_000001_create_festival_tables` | creates the festival tables | `down()` drops them |
| `2026_10_21_000001_widen_email_logs_status_on_mysql` | `email_logs.status` ENUM -> VARCHAR(20) on MySQL/MariaDB (no-op on SQLite) | `down()` is deliberately empty (narrowing would truncate `blocked`/`simulated`) |
| **`2026_10_22_000001_bind_mobile_otps_to_account`** | adds nullable `user_id` + index to `mobile_otps` (binds password-reset codes to the account) | `down()` drops the column and index |

- [ ] **`2026_10_22_000001_bind_mobile_otps_to_account` is still pending on the development database.** Mobile OTP login and OTP password reset
      fail until it has run, because the new code writes `mobile_otps.user_id`. It must be in the production run.
- [ ] Rehearse the run on a restored copy of the production backup (staging) first, then `php artisan data:integrity-audit` ("All invariants hold.").
- [ ] Order: verified backup -> maintenance mode -> deploy code -> `php artisan migrate --force` -> `config:cache route:cache view:cache` ->
      one-time web-session flush (section 8) -> `php artisan queue:restart` -> `php artisan up` -> smoke test.

### 2. Queue worker after deploying changed queued-job code
- [ ] Run **`php artisan queue:restart`** after every deploy that changes queued-job code. A worker keeps the old code in memory until it is told to
      restart; this release changes `DispatchEventNotification`, `SendEmailJob` and the notice listeners.
- [ ] Confirm the supervisor/systemd unit brought the workers back and the `jobs` table drains; `php artisan queue:failed` must be empty or understood.
- [ ] After `config:cache`, workers must be restarted too (they hold the old configuration).

### 3. PHP version and extensions (check BOTH the CLI and the web/FPM runtime - they can load different `php.ini` files)
Declared by the installed runtime packages (`composer.lock`): **ctype, dom, fileinfo, filter, gd, hash, iconv, json, libxml, mbstring, openssl, pcre,
session, simplexml, tokenizer, xml, xmlreader, xmlwriter, zip, zlib**; plus **pdo_mysql** (MySQL/MariaDB) and **curl** (the SMS and WhatsApp services call
`curl_init` directly); `intl` is optional. PHP `^8.1` (development runs 8.2.12).
- [ ] **`gd` and `zip` are hard requirements** (via `phpoffice/phpspreadsheet`, a dependency of `maatwebsite/excel`). The development PHP has neither, so
      green local tests do **not** prove them. `composer install` refuses to run without them, and a vendor tree installed with extension checks
      fails at boot if they later disappear. `gd` is also what DomPDF uses for images in PDFs (society logo): generate a financial PDF with a logo on staging.
- [ ] `php -v; php -m | sort`, and for the web runtime compare `php --ini` with the FPM pool's ini. Do not add a `phpinfo()` route to check.
- [ ] Optional hardening for a later change (not this release): `maatwebsite/excel` is not used by any application code and is what drags in `gd`/`zip`.

### 4. Uploaded files: `public/storage` and `storage/app` must survive the deploy
Where uploads live: public uploads are written to `public/storage/<folder>` (via `public_path('storage/...')`) and `storage/app/public/<folder>`, and
`public/storage` should be a symlink to `storage/app/public` (`php artisan storage:link`); private society documents live in `storage/app/society-documents`.
On the development box that is 858 files under `storage/app/public` and 23 under `storage/app/society-documents`. Git ignores all of it
(`/storage/*`, `/public/storage`), so a git-based or `git archive` deploy never touches uploads. **Copying, zipping or FTP-ing the project folder does.**
- [ ] **Never upload the development folder wholesale.** It holds the development database (real PII), `storage/backups/*.sqlite`, the development
      `.env`, 858 development uploads and a `public/storage` junction.
- [ ] **Never** run `rsync --delete`, an FTP "synchronise", `rm -rf public` or `rm -rf storage` on the live tree. If rsync is used, exclude explicitly and
      read a dry run first, with no `--delete` the first time:
      `rsync -a --dry-run -i --exclude '.env' --exclude 'storage/' --exclude 'public/storage' --exclude 'bootstrap/cache/*.php' --exclude 'database/*.sqlite' --exclude '.git/' ./release/ /var/www/societyflow/`
- [ ] Before deploying: `ls -ld public/storage; readlink -f public/storage`.
      (a) A symlink to `storage/app/public`: keep it, do nothing.
      (b) A **real directory** with files in it: leave it exactly as it is. Do **not** run `php artisan storage:link` (it errors when the path exists) and do
      **not** run `php artisan storage:setup` casually - it merges a real `public/storage` into `storage/app/public` (on a file-name collision the
      `storage/app/public` copy wins and the other is discarded) and then replaces the directory with the symlink. Do that only as its own planned step,
      after a verified uploads backup and a before/after manifest.
- [ ] Manifest before and after; they must match (new files only):
      `find -L storage/app public/storage -type f -printf '%s %p\n' | sort > /safe/uploads-before.txt`, repeat as `uploads-after.txt`, then `diff`.
- [ ] Back up uploads separately from the database and prove the archive:
      `tar -C /var/www/societyflow -czf /safe/uploads-$(date +%F).tgz storage/app $([ -d public/storage ] && [ ! -L public/storage ] && echo public/storage)`;
      `tar -tzf <file> | wc -l` must equal the manifest count; restore a sample into a scratch directory and open it.
- [ ] Prefer an atomic layout that keeps uploads out of the code tree: `releases/<timestamp>/` for code, `shared/.env` and `shared/storage` linked into each
      release, `current` -> newest release. Deploying = build a release directory, link the shared paths, migrate, switch `current`; rolling back = switch back.
- [ ] If production keeps uploads on external storage (`FILESYSTEM_DISK`, `AWS_*`, an NFS mount), nothing in this section touches those objects; do not change
      the disk setting in this release. The development value is `FILESYSTEM_DISK=public` - confirm what production uses before deploying.
- [ ] After deploying: open five existing images and download two private documents through the app; upload a synthetic file in a test society and delete it.
- [ ] Permissions: the web user must write `storage/` and `bootstrap/cache/` (and `public/storage` if it is a real directory) - 775 with the right group, not 777.
      The application itself creates upload folders with mode 0777 in a few controllers (known hardening item P2-4).

### 5. Backup verification and a tested rollback (nothing has been created or restored by the audit)
- [ ] Database backup: `mysqldump --single-transaction --routines --triggers --no-tablespaces --default-character-set=utf8mb4 -u <backup-user> -p <database> | gzip > /safe/societyflow-$(date +%F-%H%M).sql.gz`
- [ ] **Verify it, do not trust it:** `gzip -t <file>`; restore into a scratch database (`CREATE DATABASE sf_restore_test`; `zcat <file> | mysql sf_restore_test`); compare row counts of
      `users, owners, tenants, flats, maintenance_bills, payments, facility_bookings, complaints, visitors, notices, email_logs, notifications, personal_access_tokens, migrations`
      against production; run `php artisan migrate:status` against the restored copy.
- [ ] Store the production `.env` copy encrypted and offline (it contains secrets); never in the repository, never in a ticket.
- [ ] **Code rollback (the normal path)** - safe because all four migrations above are additive and old code ignores the new columns/tables:
      `php artisan down` -> switch to the previous release (or redeploy the previous artifact) -> `php artisan optimize:clear` -> `php artisan queue:restart` -> `php artisan up` -> smoke test.
- [ ] **Do not run `php artisan migrate:rollback` on production**: `2026_10_21_000001` has an empty `down()` and the OTP column would be dropped while code that needs it may still run.
- [ ] **Data rollback (only if data was damaged)**: restore the verified dump (everything written after it is lost - decide with the business; prefer restoring into a scratch database and copying back specific tables).
- [ ] "Tested" means rehearsed once on staging with the real backup, timed, and the result recorded. An untested rollback is not a rollback plan.

### 6. A reviewable release point (no commit or tag exists yet; none is created without approval)
The tested code is 140 uncommitted changes on `production-readiness` (HEAD 3787ceb). Establish a release point without losing or altering the working tree:
- [ ] (needs approval) `git switch -c release/2026-10-22` - the uncommitted changes come along untouched; commit them in reviewable groups
      (migrations; authentication; notifications and messaging; finance and facility; festival; tests; docs); `git tag -a rc-2026-10-22 -m "release candidate"`.
      `production-readiness` stays at 3787ceb. Afterwards `git diff` is empty and the tree is byte-identical, so earlier test results still describe it.
- [ ] Prove the tag is self-contained (catches an untracked file that was never added): `git worktree add ../sf-rc rc-2026-10-22`, `composer install`, run the suites there.
- [ ] Build the deployable artifact from the tag: `git archive --format=zip rc-2026-10-22 -o societyflow-rc-2026-10-22.zip` - tracked files only, so no `.env`, no `storage/`, no uploads,
      no SQLite; record `sha256sum` of the zip and deploy exactly that file.
- [ ] Without any commit (interim only): `git diff HEAD --binary > rc.patch` plus `git ls-files --others --exclude-standard` and a SHA-256 manifest of those files, kept outside the repository.
- [ ] Tag what production runs now (`prod-before-rc`) once its commit is known - that is the rollback target.

### 7. Production `.env` - build it from `.env.example`, never copy the development `.env`
The development `.env` has `APP_URL` on a LAN address, `DB_CONNECTION=sqlite`, `QUEUE_CONNECTION=sync`, `SESSION_SECURE_COOKIE=false`, `FILESYSTEM_DISK=public`,
`EXTERNAL_MESSAGING_ENABLED=true` with a two-entry allow-list, and provider keys. Copying it would ship all of that. `.env.example` itself has development defaults
(`APP_ENV=local`, `APP_DEBUG=true`) and uses `CACHE_STORE`, which this Laravel 10 app ignores - the key that works is `CACHE_DRIVER`.
- [ ] `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://<real domain>`, a fresh `APP_KEY`, `LOG_LEVEL=error`, `LOG_STACK=daily`.
- [ ] `SESSION_SECURE_COOKIE=true`; `SESSION_DRIVER` verified (file/database/redis) and, for `database`, the `sessions` table present; `SESSION_LIFETIME` as decided.
- [ ] `CACHE_DRIVER` set explicitly (`file` is fine on one server; several servers need `redis` or `database` so rate limits, locks and duplicate-send memory are shared).
- [ ] `QUEUE_CONNECTION=database` (or redis) with a supervised worker; `TRUSTED_PROXIES` when behind a proxy/Cloudflare **(also required for the per-IP login limits to see real client IPs)**;
      `CORS_ALLOWED_ORIGINS` set to the real origin(s).
- [ ] `EXTERNAL_MESSAGING_ENABLED` stays `false` until a provider test is approved. `EXTERNAL_MESSAGING_ALLOWLIST` must be **absent** in production unless a restricted test window is intended
      (absent = unrestricted; present = only those recipients - a copied development value would silence everyone else).
- [ ] `OTP_EXPOSE_DEV_CODE` absent or `false`. No `SEED_ADMIN_PASSWORD`. No test provider keys.
- [ ] After `config:cache`, nothing reads `.env` any more: re-run `php artisan config:cache` whenever `.env` changes, then `queue:restart`.

### 8. One-time web-session invalidation
- [ ] Follow `docs/SESSION_INVALIDATION_DEPLOY_STEP.md` (verify the production session driver and storage first). It is what makes the new password-change session protection cover sessions that already exist.

### 9. Known security gaps to close or knowingly accept before launch
- [ ] **The mobile API password login has no failed-attempt lockout** - only the general `throttle:api` limit (60 requests/minute per IP, shared with every other API call). Proposed fix is in the audit report.
- [ ] **Default-password accounts** (32 in development, including the only Super Admin) - the production database must be checked and cleaned under an approved plan (`docs/DEMO_ACCOUNTS.md`).
- [ ] **Account recovery depends on unverified providers**: e-mail reset needs a verified SMTP sender; mobile OTP login/reset needs verified Fast2SMS DLT templates. Until then only an administrator reset works.
- [ ] Sanctum tokens never expire (separate decision, see section "BLOCKING - environment").

### 10. Correction to the credentials item at the top of this file
`php artisan users:rotate-weak-passwords` is **not** safe to run as written above: report mode prints each account's default password in clear; `--force --no-mail` generates new passwords and
**discards them** (accounts become unusable); `--force` e-mails them (a failing mail setup loses them) and rotates every matching account at once with no per-user or per-society option.
Use the plan in `docs/DEMO_ACCOUNTS.md` instead, and only after approval.