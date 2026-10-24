# Deploying SocietyFlow to CloudPanel (societyflow.joboapps.com)

> **Automated deployment from Bitbucket (pipeline, non-interactive mode, automatic code rollback, Cloudflare / `TRUSTED_PROXIES`, first-deploy procedure):** [BITBUCKET_PIPELINES_SETUP.md](BITBUCKET_PIPELINES_SETUP.md). This page remains the manual, supervised process and the rollback reference.
**Status 2026-10-23: PREPARED, NOT EXECUTED.** Nothing has been uploaded, nothing was run on the live server, and it was **never logged into or changed** (the only contact was a few plain public GET requests on 2026-10-24, listed in BITBUCKET_PIPELINES_SETUP.md section 1)
(no SSH). Everything about it below is either from your message or marked **UNVERIFIED**. The scripts were tested against a
scratch copy of the application on this PC (section 10), not against CloudPanel.

| Fact | Value | Source |
|---|---|---|
| Domain | https://societyflow.joboapps.com/ | you |
| Site user | `joboapps-societyflow` | you |
| App directory | `/home/joboapps-societyflow/htdocs/societyflow.joboapps.com/` | you |
| Document root | `.../societyflow.joboapps.com/public` | you |
| Web server / PHP | Nginx + PHP-FPM (CloudPanel's normal stack) | **UNVERIFIED** - run `inspect-server.sh` |
| PHP version, database engine, queue driver, migrations already applied, whether `public/storage` is a symlink | unknown | **UNVERIFIED** |

This document adds the CloudPanel-specific process. The general rules (credentials, extensions, session flush, queue restart, release point) stay in
[PRODUCTION_CHECKLIST.md](PRODUCTION_CHECKLIST.md) and are not repeated here; read its "Go/No-Go additions (2026-10-22)" section too.

## 1. What the local audit found

| Question | Answer |
|---|---|
| Laravel / PHP | Laravel 10.50.0 (`composer.lock`); `composer.json` requires PHP `^8.1`; developed on 8.2.12. Needs `gd` and `zip` (via `phpoffice/phpspreadsheet`) - **off in the local php.ini**, so green local tests do not prove them on the server |
| Document root | `public/` (standard Laravel). `public/.htaccess` and `public/web.config` are for Apache / IIS and are **ignored by Nginx**; so is the root `.htaccess` |
| Front-end build | **None.** No `package.json`, Vite or Mix; layouts load Tailwind / Alpine / Font Awesome from CDNs; `public/js` and `public/css` are plain files. Nothing to build for deployment |
| `vendor/` | **Not shipped.** The local `vendor/` contains dev packages (PHPUnit, Sail, Faker...). The server runs `composer install --no-dev --optimize-autoloader` from the shipped `composer.json` + `composer.lock` (needs the server to reach packagist) |
| User uploads | `storage/app/public/**` (served through `public/storage`), `storage/app/society-documents/**` (private, served only through authorising routes), and `public/storage/**`. Controllers write to `public_path('storage/...')`; with `public/storage` a symlink to `storage/app/public` both are the same folder. Git ignores all of it, **copying the project folder does not**. Local dev holds ~858 + 23 files of real uploads and a PII database: **never upload the project folder** |
| `storage:setup` | Deliberately **not used**: on a real `public/storage` directory it merges that folder into `storage/app/public` and replaces it with a symlink (on a name collision the `storage/app/public` copy wins). Only `storage:link`, only when `public/storage` does not exist at all |
| Seeders | 13 of 43 create accounts with **hard-coded passwords** (`password`, `123456`, `guard123`, one is a Super Admin seeder); more are demo / sample data. They are excluded from the package. `db:seed` is **never** part of a deployment |
| Queue / scheduler | `config/queue.php` default is `sync` (no worker needed). Scheduled work in `app/Console/Kernel.php`: event status updates, visitor escalation (every minute), bill reminders (09:00 and 18:00) - needs the cron line in section 7. With a non-`sync` driver a worker is needed and `queue:restart` after each deploy |
| Existing deployment automation | None (no deploy script, CI, Dockerfile, Envoy). Only documents: `PRODUCTION_CHECKLIST.md`, `DISK_SPACE_AND_DEPLOYMENT.md`, `MESSAGING_STAGING_AND_DEPLOYMENT.md`, `SESSION_INVALIDATION_DEPLOY_STEP.md`, `PHP_UPGRADE_RUNBOOK.md` (local PC only) |
| Migrations | 115 files; the latest is `2026_10_22_000001_bind_mobile_otps_to_account`. **What production has already run is UNVERIFIED.** A static review of every `up()` is generated with each package (`MIGRATIONS-REVIEW.txt`); 19 migrations drop / rename schema or write data - details in section 8 |
| Working tree | `production-readiness`, HEAD `3787ceb`, **191 uncommitted paths**: the package is built from the working tree and is tagged `-dirty` in its id. A release point (branch / tag) needs your approval - see PRODUCTION_CHECKLIST.md section 6 |

## 2. What is in the package, and what is not (and why)

Built by `scripts/deploy/New-ReleasePackage.ps1` from an explicit allow-list: `app/`, `bootstrap/app.php`, `config/`, `database/migrations`, `database/seeders` (safe ones),
`public/` (code and assets), `resources/`, `routes/`, `artisan`, `composer.json`, `composer.lock`, plus `RELEASE.txt` and `RELEASE.sha256` (a hash of every file, so the
server can prove it matches). About 773 files, 1.1 MB.

Not shipped: `.env*`, `vendor/`, `storage/` (all of it), `bootstrap/cache/*`, `public/storage`, `*.sqlite` and every database / dump / key / log / archive pattern,
`tests/`, `docs/`, `scripts/`, `societyflowmob` (the 3 GB Flutter project), demo / sample / hard-coded-password seeders, Windows start scripts. Each package carries
`EXCLUDED.txt` with the reason for every exclusion. The server keeps its own `.env`, storage, uploads, databases and `vendor/`.

The script refuses to mark a package READY (it writes `NOT-READY.txt`) if any check fails: required files missing; anything forbidden present; a staged file that is not
byte-identical to its source; a seeder with a literal password; a private key, cloud key, `APP_KEY` or DB password in any shipped file; a PHP syntax error; composer.lock out
of step with composer.json; an archive that does not list exactly the staged files; an extracted archive that does not match `RELEASE.sha256`.

## 3. The files this adds

| File | Runs where | Purpose |
|---|---|---|
| `scripts/deploy/New-ReleasePackage.ps1` | your PC | builds + validates the package; **local only**, never connects anywhere |
| `scripts/deploy/server/inspect-server.sh` | server | read-only inspection (task D) |
| `scripts/deploy/server/db-backup.sh` | server | verified database backup; writes only into the backup folder |
| `scripts/deploy/server/apply-release.sh` | server | dry run by default; with `--apply` does the deployment |
| `scripts/deploy/server/rollback-code.sh` | server | restores the previous **code** from the backup |
| `.gitattributes` | repo | keeps these `.sh` files LF so they run on Linux |

Server tools live on the server **outside** the web root, e.g. `/home/joboapps-societyflow/deploy-tools/`.

## 4. Before the first deployment (read-only; do this first)

1. Upload `server-tools/` from the package folder to `/home/joboapps-societyflow/deploy-tools/` and run, as the site user:
   ```bash
   cd ~/deploy-tools && sha256sum -c SHA256SUMS && chmod 700 *.sh
   PHP_BIN=/usr/bin/php8.2 bash inspect-server.sh        # use the PHP version CloudPanel shows for this site
   ```
   It prints no secret and changes nothing. Send me (or read) the output: it answers every UNVERIFIED row above (PHP CLI and FPM versions and whether they match,
   extensions incl. `gd`/`zip`, Laravel and database versions, applied vs pending migrations, document root, `public/storage` type, disk space, ownership, existing
   backups, cron / worker state, upload folders containing script files, stray files under `public/`, risky `.env` values such as `APP_DEBUG` or `OTP_EXPOSE_DEV_CODE`).
2. Equivalent individual commands, if you prefer to see each one (all read-only):
   ```bash
   cd /home/joboapps-societyflow/htdocs/societyflow.joboapps.com
   php -v | head -1; ls /usr/bin/php[0-9]*                         # CLI PHP and the others installed
   grep -l 'user *= *joboapps-societyflow' /etc/php/*/fpm/pool.d/*.conf   # the pool file's folder name is the FPM PHP version
   php -m | grep -iE '^(gd|zip|pdo_mysql|curl|mbstring|openssl|fileinfo)$'
   php artisan --version
   grep -E '^\s*root\s' /etc/nginx/sites-enabled/societyflow.joboapps.com.conf   # must be .../public
   ls -ld public/storage; readlink -f public/storage; ls -ld storage storage/app/public bootstrap/cache
   php artisan migrate:status | grep -c Pending                    # read-only; lists what the server has not run
   df -h /home; du -sh storage/app/public storage/app/society-documents
   ls -lt /home/joboapps-societyflow/backups 2>/dev/null | head
   ```
   Database engine and version without credentials: `inspect-server.sh` does it; by hand use `php artisan db:show` **only if you accept it prints the host and user name**.
3. In CloudPanel take (or confirm a recent) site backup: Sites -> societyflow.joboapps.com -> Backups. Treat it as an extra, not a replacement for section 5.

## 5. Build and upload the package (on your PC)

```powershell
cd G:\xampp\htdocs\SocietyFlow
powershell -NoProfile -ExecutionPolicy Bypass -File scripts\deploy\New-ReleasePackage.ps1 -Label rc
```
Output: `C:\Users\USER\SocietyFlow-releases\<release-id>\` (outside the repository). Continue only if it ends `READY` and the folder holds `READY.txt` (not `NOT-READY.txt`).
Read `EXCLUDED.txt`, `MIGRATIONS-REVIEW.txt` and the `WARN` lines in `VALIDATION.txt`.

Upload **to a folder outside the web root**: `/home/joboapps-societyflow/incoming/`. CloudPanel's File Manager works; or from your PC with the site user's SSH/SFTP access (Sites -> SSH & FTP):
```powershell
scp "<package>.tar.gz" "<package>.tar.gz.sha256" joboapps-societyflow@SERVER_IP:/home/joboapps-societyflow/incoming/
scp -r "<release-folder>\server-tools" joboapps-societyflow@SERVER_IP:/home/joboapps-societyflow/deploy-tools-new
```
(Uploading anything is a production change: do it only when you decide to.)

## 6. Deploy (on the server, as the site user - never root)

```bash
cd ~/deploy-tools
export PHP_BIN=/usr/bin/php8.2          # the version inspect-server.sh reported for the web runtime
# 1) DRY RUN - changes nothing on the live site; unpacks the package into ~/releases/<id> and shows what would change
bash apply-release.sh --package ~/incoming/societyflow-<release-id>.tar.gz
# 2) read the lists; when satisfied:
bash apply-release.sh --package ~/incoming/societyflow-<release-id>.tar.gz --apply
```
What `--apply` does, in order, stopping at the first problem: verify checksums and free space and the PHP extensions; confirm the Nginx root is `<app>/public`; unpack and verify
`RELEASE.sha256`; show new / changed / identical / server-only files; **ask you to type the release id**; back up the current code (not storage) + a 600-mode copy of `.env`;
back up the database with `db-backup.sh` and verify it; record the upload file names + sizes; `artisan down`; copy the release over the code (adds and overwrites only,
**nothing is deleted**; `.env`, `storage/`, `public/storage` are not in the package so cannot be touched); `composer install --no-dev --optimize-autoloader`; clear config / route / view caches;
**show pending migrations and the SQL they would run, then ask you to type `MIGRATE`** (never automatic; no seeding, no rollback); `storage:link` only if `public/storage` does not exist;
rebuild config / route / view caches; `queue:restart`; `artisan up`; verify `RELEASE.sha256`, uploads unchanged, `/login` = 200, `/api/v1/health` identifies SocietyFlow, no new ERROR lines.
If anything fails after maintenance mode started, it **stays in maintenance mode** and prints the rollback command, so a half-updated site is never shown to users.

Flags: `--skip-db-backup` (asks again; only for a release with no migrations), `--skip-composer`, `--clear-app-cache` (also flushes rate-limit counters / settings cache), `--no-queue-restart`.
It never runs: `migrate:fresh`, `migrate:rollback`, `db:seed`, `db:wipe`, `storage:setup`, or anything that restarts Nginx / PHP-FPM / MariaDB.

**One-time web-session flush** (users re-log-in once after the password-change change) is a separate decision: [SESSION_INVALIDATION_DEPLOY_STEP.md](SESSION_INVALIDATION_DEPLOY_STEP.md).

## 7. CloudPanel settings that matter

* **Cron (scheduler)** - Sites -> Cron Jobs (runs as the site user), every minute:
  `* * * * * /usr/bin/php8.2 /home/joboapps-societyflow/htdocs/societyflow.joboapps.com/artisan schedule:run >> /dev/null 2>&1`
* **Queue** - if `.env` has `QUEUE_CONNECTION=sync` nothing is needed. With `database` / `redis`, a worker must run and be restarted by `queue:restart` after a deploy: e.g. a cron
  `* * * * * /usr/bin/php8.2 .../artisan queue:work --stop-when-empty --max-time=55 >> /dev/null 2>&1`, or a supervisor program (root-managed).
* **Nginx: stop uploaded script files from executing.** CloudPanel's PHP vhost sends every `*.php` it can find to PHP-FPM, and `public/storage` holds user uploads (`.htaccess` does nothing on Nginx).
  In the site's **Vhost** editor add, **above** the `location ~ \.php$` block, then save (CloudPanel validates with `nginx -t`):
  ```nginx
  location ~* ^/storage/.*\.(php|phtml|phar|pht|phps)$ { return 403; }
  ```
  Keep a copy of the original vhost text first. This edits the live web server config: do it deliberately, then test an image URL still loads.
* **PHP settings** per site (Sites -> Settings -> PHP): `memory_limit` >= 512M, `upload_max_filesize` / `post_max_size` >= 40M (the app accepts documents up to 10 MB and images; the dev box uses 40M), `display_errors` Off.
  PHP **8.2** (the tested version) - and `gd`, `zip` loaded (CloudPanel's PHP builds normally include them; `inspect-server.sh` checks).
* **Production `.env`** - never overwritten by the deploy. Verify (not print) at least: `APP_ENV=production`, `APP_DEBUG=false`, HTTPS `APP_URL`, `SESSION_SECURE_COOKIE=true`,
  `OTP_EXPOSE_DEV_CODE` unset/false, `TRUSTED_PROXIES` set if a proxy / CDN is in front (otherwise the login lockout sees every visitor as one IP), `CORS_ALLOWED_ORIGINS` set,
  `EXTERNAL_MESSAGING_ENABLED` and its allow-list **deliberately** chosen. See PRODUCTION_CHECKLIST.md section 7.

## 8. Rollback

**Code (the normal path; minutes):** 
```bash
cd ~/deploy-tools
bash rollback-code.sh --backup ~/backups/societyflow/code-<stamp>.tar.gz            # dry run: shows how live differs from the backup
bash rollback-code.sh --backup ~/backups/societyflow/code-<stamp>.tar.gz --apply     # asks you to type ROLLBACK
```
Restores the previous code and its `vendor/`, rebuilds caches, `queue:restart`, `up`. It does not touch `.env`, `storage/`, uploads or the database. Files the new release added stay (nothing refers to them).
The deploy log, the code backup, the `.env` copy, the database dump and the upload before/after lists are all in `~/backups/societyflow/` (mode 700; the `.env` copy is 600 and holds secrets).

**Database - when it is NOT safe to "just roll back":** never run `php artisan migrate:rollback` on production. Some `down()` methods are empty or destructive (`2026_10_21_000001` is deliberately empty; rolling back
the OTP migration drops the column while code that needs it may still run). What to do depends on what the release migrated:

| Kind of migration | Old code afterwards | Action |
|---|---|---|
| Creates tables / adds nullable columns or indexes (most of 2026-10: facility payments, festival tables, OTP `user_id`, SMS templates, visitor workflow) | works - ignores them | leave the schema as it is; roll back code only |
| Widens / relaxes a column or recreates foreign keys (`2026_10_07_000005`, `2026_10_09_000001`, `2026_10_21_000001`) | works | leave it |
| Replaces a unique key (`2026_10_13_000002` bill uniqueness per type) | old code works (the new key is broader) | leave it |
| Rewrites data (`2026_10_14_000002` marks every unused OTP code used and changes `otp` to a hash column) | mobile OTP codes in flight are invalid; users request a new one | leave it; expected |
| Anything that **drops or deletes** (see the flagged list in `MIGRATIONS-REVIEW.txt`; mostly older table-rebuilds) | may not work | **separate recovery plan**: restore from the verified dump |

**Restoring the database dump (data written after the dump is lost - decide with the business first):** put the site in maintenance mode, restore the dump **into a scratch database first**, compare row counts
with production, then either point the app at it or copy specific tables back; use `mysql -u <user> -p` interactively (do not put the password on the command line). Prove a dump restores *before* you need it:
```bash
zcat ~/backups/societyflow/db-<name>-<stamp>.sql.gz | mysql -u <user> -p sf_restore_test      # a scratch database you created in CloudPanel
```

## 9. After deploying - verification

1. `cat RELEASE.txt` shows the new release id; `sha256sum -c --quiet RELEASE.sha256` prints nothing; `php artisan migrate:status | grep -c Pending` -> 0; `php artisan --version`.
2. From your PC (read-only): `curl -sI https://societyflow.joboapps.com/login` -> 200 (and the `Server` / `X-Powered-By` headers show the web PHP version if `expose_php` is on); `curl -s https://societyflow.joboapps.com/api/v1/health` -> `{"application":"societyflow",...}`.
3. In a browser, as a **test** admin (not real residents): sign in; Owners -> edit an owner -> Villa Area / Villa No. load; open five existing photos and download one private document (uploads survived); download a PDF report (needs `gd`); notification bell loads.
4. Sign in as a Guard and see the web layout; sign in once on the mobile app against the live API.
5. `tail -n 60 storage/logs/laravel-*.log` shows no new ERROR; `php artisan schedule:list` lists the jobs; `php artisan queue:failed` is empty.
6. Compare `~/backups/societyflow/uploads-before-<stamp>.txt` with `uploads-after-<stamp>.txt`: the script already reports it; only additions are expected.
7. No real message was sent by the deploy. Before enabling any provider-dependent feature, follow [MESSAGING_STAGING_AND_DEPLOYMENT.md](MESSAGING_STAGING_AND_DEPLOYMENT.md).

## 10. How this was tested (on this PC, not on CloudPanel)

On a scratch copy of the application (own `.env` with a throw-away key, synthetic SQLite database, fake uploads, `public/storage` as a link): package build and validation (READY; the first build was correctly refused for an AWS-style placeholder and a seeder comment, both fixed);
dry run (live tree byte-identical afterwards); a deployment that **stopped mid-way** and left maintenance mode on (it exposed a real bug - `dump-autoload --no-dev` on a vendor that still holds dev packages breaks boot; fixed by always running `composer install --no-dev`);
the **rollback** from that state (0 files differing from the backup afterwards); a second full deployment including the migration prompt (`.env`, uploads, the private document, `public/storage` and a server-only file all unchanged; dev packages removed from `vendor/`;
`/login` 200 and health OK); the database backup script on SQLite. **Not yet exercised:** the MySQL/MariaDB branch of `db-backup.sh` against a real server, Nginx vhost detection, CloudPanel's file ownership, and composer with network access. Do the dry run first and read it.

## 11. What I still need you to confirm

1. SSH access: can you log in as `joboapps-societyflow` (key or password), and do you want me to prepare a command list only, or will you paste `inspect-server.sh` output back?
2. The PHP version CloudPanel shows for this site (Sites -> Settings), and whether `gd` + `zip` show as loaded.
3. Database: MySQL or MariaDB (assumed), name of the DB user's privileges (the dump needs SELECT, LOCK TABLES, TRIGGER; `mysqldump` must be installed).
4. Whether the live site was previously deployed by git, FTP, or the File Manager (decides how different the first dry run will look), and whether `public/storage` is a symlink or a folder.
5. Which migrations production has run (`migrate:status` output) - this decides which of the flagged migrations will actually execute.
6. Queue driver on production and whether you want a worker.
7. Whether to create a release commit / tag first (needs your approval; the package is currently `-dirty`).
8. When a maintenance window is acceptable (the site is in maintenance mode for a few minutes; longer if composer must download packages or a migration is slow).
