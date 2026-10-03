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
      It is `sync`, so every email and push send blocks the HTTP response.
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
      credentials live there).

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
  see a pending approval. Web Push (VAPID) is the only working channel.
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
