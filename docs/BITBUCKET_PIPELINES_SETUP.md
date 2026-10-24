# Automatic deployment: Bitbucket -> CloudPanel (setup, safety, recovery)

**Status 2026-10-24: BUILT AND TESTED LOCALLY; NOT ENABLED; NO PRODUCTION DEPLOYMENT HAS BEEN PERFORMED.**
No SSH access to the server was available, so nothing on the server was inspected or changed. The pipeline has never run in Bitbucket (only a
syntax / structure check and the scripts' own tests were possible here). The production step is a **manual** button on purpose.
How the manual / one-off process works (package, dry run, rollback): [DEPLOYMENT_CLOUDPANEL.md](DEPLOYMENT_CLOUDPANEL.md). Branches: [BITBUCKET_WORKFLOW.md](BITBUCKET_WORKFLOW.md).

## 1. What was verified, and what was not

| Fact | State | Evidence |
|---|---|---|
| Web code is in Bitbucket on `production-readiness` | VERIFIED | local = remote commit hash |
| Mobile code is in its own repository (`societyflow-mobile`, `main`) | VERIFIED | local = remote commit hash |
| Live site is up, behind **Cloudflare**, serves the SocietyFlow health endpoint and `/api/v1/password-policy` | VERIFIED (plain public GETs, 24 Oct) | `/` 200, `/login` 200, `/owners` 302 -> `/login`, `/api/v1/health` 200 |
| Live code is **older** than `production-readiness` | VERIFIED by behaviour | its login page still shows the **demo-credentials card** that the current code removed |
| **The live login page advertises demo accounts (a default admin login together with its default password)** | VERIFIED, **URGENT** | public page source; the credentials were not tried and must not be |
| PHP version, database engine/version, queue driver, applied migrations, `public/storage` type, disk space, Composer, `mysqldump`, SSH access | **UNVERIFIED** | needs `inspect-server.sh` output |
| Whether the live database has any of the October migrations | **UNVERIFIED** | needs `migrate:status` on the server |
| Whether production uses the same `APP_KEY` that was committed in `.env.mysql.template` | **UNVERIFIED** | see TODO P0-20 |
| The pipeline YAML and `deploy.sh` against a real Bitbucket runner and a real server | **NOT RUN** | tested here: YAML structure, the scripts' syntax, the package builder, and the server scripts on a scratch copy |

**Do this first, whatever else you decide:** the live demo credentials are an open door. Deploying this release removes the card, but the accounts behind it may still have those
passwords. On the server run `php artisan users:rotate-weak-passwords` **without `--force`** (report only) to see which accounts still use known passwords; rotating them is your call
(the command locks people out until they reset - read docs/CREDENTIAL_ROTATION.md first).

## 2. How a deployment works

```
git push  ->  Bitbucket pipeline
   1 Install + test      PHP 8.2, SQLite in memory, composer check-platform-reqs, route:list, lint, the whole PHPUnit suite   (every branch and pull request)
   2 Build package       scripts/deploy/ci/build-release.sh  -> committed files only, allow-list, secret scan, checksums     (every branch and pull request)
   3 Deploy to production  [production-readiness only, MANUAL button]  scripts/deploy/ci/deploy.sh
        upload tools + package over SSH (strict host-key checking) -> server dry run -> server apply (--non-interactive) -> outside smoke check
```
What the server does in `apply-release.sh --non-interactive` (all of it logged): verify checksums -> **stop with exit 3, changing nothing, if the release has a pending migration** -> exclusive lock ->
back up code + `.env` copy (600) + database (verified) -> record upload names and **business-table record counts** -> maintenance mode -> copy the release over the code (never deletes,
never touches `.env`, `storage/`, `public/storage`) -> `composer install --no-dev` -> rebuild caches -> `queue:restart` -> back online -> compare record counts (any lost row = failure) ->
smoke check (home, login, `/owners` protected, health, no error pages, no demo-credentials card) -> **if the smoke check fails: the previous code is restored automatically** (safe because no
migration ran) -> `deployments.log` line, and `LAST_SUCCESSFUL_RELEASE` only after everything passed.

**Migrations are never run by the pipeline.** A release that contains one needs the interactive deploy (section 6), where you see the SQL and type `MIGRATE` after a verified backup.
Production deployments therefore split naturally into two kinds: code-only releases (automatic-capable) and releases with schema changes (supervised).

## 3. Branches and what triggers deployment

* **Deploys from:** `production-readiness` (as you asked for the first phase). It is the only branch with the deploy step. To move deployment to `main` later: merge `integration/main-merge`
  into `main` by pull request, then rename the key `production-readiness:` to `main:` in `bitbucket-pipelines.yml` (one line) and set a branch restriction on `main` (section 5).
* **Every other branch / pull request:** tests and a package build only - nothing can reach the server.
* **Manual button:** Pipelines -> the latest `production-readiness` run -> step 3 "Deploy to production" -> Run. Or **Run pipeline** -> custom `server-dry-run` (changes nothing live)
  or `production-deploy` (builds and deploys the commit you pick).
* **Making it fully automatic** is deleting the single line `trigger: manual`. Do that only when the checklist in section 7 is signed off **and** you accept that every green push to the branch goes live.

## 4. One-time server prerequisites (CloudPanel, as `joboapps-societyflow`) - all UNVERIFIED until you run `inspect-server.sh`

1. SSH works for the site user (CloudPanel -> Sites -> societyflow.joboapps.com -> SSH & FTP). Never deploy as root; the script refuses root.
2. PHP CLI >= 8.1 (tested on 8.2) with `gd`, `zip`, `pdo_mysql`, `curl`, `mbstring`, `openssl`, `fileinfo`, `xml`, `dom`, `tokenizer`, `ctype`, `json`; `composer` 2.x; `mysqldump` (for MySQL/MariaDB sites); `flock`; `tar`, `gzip`, `curl`, `sha256sum`.
   If the CLI default `php` is not the site's version, set `PROD_PHP_BIN` (e.g. `/usr/bin/php8.2`).
3. Folders (the scripts create them): `~/incoming`, `~/releases`, `~/deploy-tools`, `~/backups/societyflow` (mode 700). All outside the web root.
4. The document root in the Nginx vhost is `/home/joboapps-societyflow/htdocs/societyflow.joboapps.com/public` (the scripts refuse to run if the vhost they can read says otherwise).
5. `.env` on the server already holds the production values and is **never** touched. Check (by name, not by pasting values): `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://societyflow.joboapps.com`,
   `SESSION_SECURE_COOKIE=true`, `OTP_EXPOSE_DEV_CODE` unset, **`TRUSTED_PROXIES`** (see 6), `EXTERNAL_MESSAGING_ENABLED` as you intend.
6. **Cloudflare / `TRUSTED_PROXIES`:** the site is behind Cloudflare. Without trusting the proxy, Laravel sees Cloudflare's address for every visitor, so (a) the new login lockout counts all users of one Cloudflare node
   together (one attacker can lock an account for everybody) and (b) the API limiter (60 requests / minute / IP) is shared by every mobile user behind that node. Set `TRUSTED_PROXIES=*` in the server `.env` **only if the origin
   accepts connections from Cloudflare alone** (CloudPanel firewall / Cloudflare IP allow-list); otherwise list Cloudflare's published ranges. Then `php artisan config:cache`. Confirm with a logged "Login locked out" line showing a real client address.
7. Cron for the scheduler, the Nginx rule that stops uploaded `.php` files executing under `/storage/`, and (if `QUEUE_CONNECTION` is not `sync`) a queue worker: DEPLOYMENT_CLOUDPANEL.md section 7.
8. **Restore rehearsal:** a database dump from `db-backup.sh` restored into a scratch database and its table / row counts compared (DEPLOYMENT_CLOUDPANEL.md section 8). Until this has been done once, treat the backups as unproven.

## 5. Bitbucket settings (names only - you set the values)

**Repository settings -> Pipelines -> Settings:** enable Pipelines.
**Repository settings -> Pipelines -> SSH keys:** *Generate keys* (a dedicated deployment key; the private key stays in Bitbucket). Copy the **public** key into CloudPanel (Sites -> SSH & FTP -> SSH keys).
**Same page -> Known hosts:** enter the server's host name and *Fetch*, then **compare the fingerprint with the one CloudPanel / your provider shows** before saving. Host-key checking is never turned off.
**Repository settings -> Deployments -> Production** (create the environment "Production"): set these **deployment variables** (mark secured where it is a secret; none of these is a password):

| Variable | Example form | Purpose |
|---|---|---|
| `PROD_SSH_HOST` | the server's host name or IP | where to connect (must match the Known host) |
| `PROD_SSH_USER` | `joboapps-societyflow` | the site user |
| `PROD_SSH_PORT` | `22` | optional |
| `PROD_APP_DIR` | `/home/joboapps-societyflow/htdocs/societyflow.joboapps.com` | the live application directory |
| `PROD_URL` | `https://societyflow.joboapps.com` | for the outside smoke check |
| `PROD_PHP_BIN` | `/usr/bin/php8.2` | optional; the site's PHP CLI |
| `PROD_COMPOSER_BIN` | `composer` | optional |

Optional hardening: Deployments -> Production -> **Deployment permissions** (only admins may run it) and a **branch restriction** so only reviewed pull requests can be merged into the deploying branch.
No private key, password, `.env` content or token goes into the repository, the YAML or a variable of this list.

## 6. First deployment (supervised) and every release that contains a migration

The live site is older than this branch, so the first release will carry a **long list of pending migrations**. It must be done by a person:

1. `inspect-server.sh` -> read it; fix anything marked `[!!]`. Take a CloudPanel site backup.
2. In Bitbucket run the custom pipeline **`server-dry-run`**: it uploads the tools and the package and runs the server dry run (nothing live changes). Read the new / changed file lists and the **pending migration list**.
3. Read `MIGRATIONS-REVIEW.txt` (in the package folder built locally with `New-ReleasePackage.ps1`, or `INCLUDED.txt` in the pipeline artifact) and the flagged migrations; decide each is safe for the live data (DEPLOYMENT_CLOUDPANEL.md section 8 classifies the October ones).
4. SSH in and run the interactive deploy: `bash ~/deploy-tools/apply-release.sh --package ~/incoming/<package> --apply` - it backs up code and database (verified), shows the SQL, and waits for you to type `MIGRATE`.
5. Verify (section 7). From the next code-only release on, the pipeline step can be used.

## 7. Pre / post deployment verification (data preservation)

Automatic (the server script does these and fails loudly): checksums of every file, uploads before / after (nothing missing), **record counts, highest ids and money totals of 29 business tables before vs after** (owners, flats, tenants, residents,
users, maintenance bills, payments, expenses, facility bookings, visitors, complaints, notices, documents, settings, ...), smoke check, new log errors, `deployments.log`.
By hand once per release: sign in as an admin of a **test** society; open `https://societyflow.joboapps.com/owners` and compare the owner count with `fingerprint-before-<stamp>.txt` (`owners` row); open one owner's edit page (Villa Area / Villa No. load);
open two receipts and a private document; check the bell; log in once from the mobile app. Compare `~/backups/societyflow/uploads-before-*.txt` with `uploads-after-*.txt` (only additions).
Checklist before enabling any automation: [ ] `inspect-server.sh` clean [ ] DB backup restored once in a scratch database and counts compared [ ] SSH deploy key + known host verified [ ] `TRUSTED_PROXIES` decided [ ] Nginx upload rule in place
[ ] cron / queue decided [ ] demo-credential accounts handled [ ] a rollback rehearsed on the dry-run package [ ] you have read `deployments.log` format and know where `LAST_SUCCESSFUL_RELEASE` is.

## 8. Recovery

* **Which release is live / was the last good one:** `cat <app>/RELEASE.txt` (id, commit, build time); `cat ~/backups/societyflow/LAST_SUCCESSFUL_RELEASE`; `tail ~/backups/societyflow/deployments.log` (every attempt: OK / ROLLED_BACK / FAILED_CHECKS with the previous release).
* **Code:** `bash ~/deploy-tools/rollback-code.sh --backup ~/backups/societyflow/code-<stamp>.tar.gz --apply`. Restores the previous code and `vendor/`; never touches `.env`, uploads or the database. The pipeline already does this by itself when its smoke check fails.
* **Database:** separate and rarely needed. Never `migrate:rollback`. A code-only release changes no schema, so there is nothing to undo. A release with migrations: additive ones are compatible with the old code (leave them); anything that
  rewrote data needs a restore from the dump taken just before - and a restore **loses everything written after it**, so decide with the business, restore into a scratch database first and copy tables back deliberately. Never restore an old dump over a newer production database to "undo a deploy".
* **Uploads** are never part of a release; they live in `storage/app` and `public/storage` and are listed (names + sizes) before and after each deploy.

## 9. Your day-to-day Git commands

Web (`G:\xampp\htdocs\SocietyFlow`, remote `bitbucket`):
```powershell
git status                       # which branch, what changed (review it; never `git add .` blindly)
git switch production-readiness  # or: git switch -c feature/my-change   (a new branch for a bigger change)
git pull bitbucket production-readiness      # bring in others' commits first (a fast-forward or a merge; never --force)
git add <the files you reviewed>             # not .env, not storage/, not *.sqlite, not build/ folders
git commit -m "What and why"
git push bitbucket production-readiness      # tests + package build start in Bitbucket; production waits for your click
```
Merge conflict: `git status` lists the files; open each, keep the right lines, remove the `<<<<<<<` markers, `git add <file>`, `git commit`. To stop and start over: `git merge --abort` (or `git rebase --abort`). Never `git push --force`, `git reset --hard` or `git clean` on shared branches.
Mobile (`G:\xampp\htdocs\societyflowmob`, remote `origin`, branch `main`): the same commands with `git push origin main`; the Flutter app has its own pipeline (tests + debug APK) and nothing deploys the backend from it.

## 10. Risks and unresolved blockers (nothing here is fixed by this document alone)

1. **Live demo credentials** on the public login page (section 1) - deploy removes the card; the accounts need a decision.
2. **No server access** for me: every server fact is unverified; the pipeline and `deploy.sh` have not run against a real server.
3. **First release is a big jump** with many pending migrations: supervised deploy only; a restore rehearsal first.
4. **APP_KEY exposure** (TODO P0-20): production may share the key committed in `.env.mysql.template`; rotation is a separate planned task (encrypted settings must be re-encrypted; all sessions end). Not done, not required by this process.
5. **Cloudflare / `TRUSTED_PROXIES`** (section 4.6) - affects lockout and API limits today, independent of deployment.
6. **Laravel 10 / PHP 8.2 support horizons** (TODO P2-69); dependency advisories unchecked (`composer audit` could not reach packagist from the dev PC) - the pipeline can run it; add `composer audit` to step 1 once you accept it may fail the build on advisories.
7. **Release commit / tag**: none yet; the pipeline records the commit SHA in `RELEASE.txt`, so tagging `rc-YYYY-MM-DD` before each deploy is advisable.
8. **Mobile release** needs a keystore and a final application id (separate repository; no APK is published by any of this).
9. **Cost / time:** the PHPUnit suite takes about 20 minutes on a single runner; consider parallel steps later.
