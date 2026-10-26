# Automatic deployment: Bitbucket -> CloudPanel (setup, safety, recovery)

**Status 2026-10-24: BUILT AND REHEARSED LOCALLY (scratch site, local stand-ins for ssh and Bitbucket); NOT ENABLED; NO PRODUCTION DEPLOYMENT HAS BEEN PERFORMED.**
No SSH access to the server was available, so nothing on the server was inspected or changed. The pipeline has never run in Bitbucket: here the YAML was parsed, its steps were simulated on a clean checkout,
and the scripts were run end to end against a scratch copy of the application (including a failed deployment that rolled itself back). The production step is a **manual** button on purpose.
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
| The pipeline YAML and `deploy.sh` against a real Bitbucket runner and a real server | **NOT RUN** | tested here: YAML parses (Symfony YAML), the scripts' syntax, the package builder, the server scripts on a scratch copy, and `deploy.sh` / `smoke-check.sh` **executed** against fake `ssh`/`scp` and a stub site (`DeploymentScriptsBehaviourTest`) |
| Only the designated branch can deploy | VERIFIED in the script (refuses before any connection; 13 cases tested) | a custom pipeline can be started on any branch, so `deploy.sh` checks `BITBUCKET_BRANCH` itself. The Bitbucket-side restriction is a **plan-dependent extra** (next row) |
| Bitbucket deployment permissions (who may deploy, which branches may deploy to *Production*) | **UNVERIFIED for your account** | Atlassian's announcement lists them under the **Premium** tier ([blog](https://www.atlassian.com/blog/bitbucket/deployment-permissions-now-available-in-bitbucket-pipelines)); check Repository settings -> Deployments -> Production |
| The live site serves the release that was just deployed | CHECKED BY THE PIPELINE, never yet run | the health endpoint sends an opaque `X-SocietyFlow-Release` header; the deploy compares it with the release id it shipped (section 2) |
| The pipeline can open an SSH connection to the server | **UNVERIFIED** | Pipelines connects from shared Amazon addresses that change; Atlassian does not support allow-listing them (section 4.9) |
| How long the test step takes in Bitbucket | **UNKNOWN until the first run** | measured on the dev PC: the whole suite (2,635 tests) in ONE process against MariaDB 10.4.32 - what the pipeline does - took **27.5 minutes**, all passing; the same engine in 8 parallel shards took about 4 minutes, in-memory SQLite in 8 shards about 39 minutes (section 10, item 9) |

**Do this first, whatever else you decide:** the live demo credentials are an open door. Deploying this release removes the card, but the accounts behind it may still have those
passwords. On the server run `php artisan users:rotate-weak-passwords` **without `--force`** (report only) to see which accounts still use known passwords; rotating them is your call
(the command locks people out until they reset - read docs/CREDENTIAL_ROTATION.md first).

## 2. How a deployment works

```
git push  ->  Bitbucket pipeline
   1 Install + test      PHP 8.2 + a throw-away MariaDB 10.4.32 service, composer check-platform-reqs, route:list, lint, the whole PHPUnit suite   (every branch and pull request)
   2 Build package       scripts/deploy/ci/build-release.sh  -> committed files only, allow-list, secret scan, checksums     (every branch and pull request)
   3 Deploy to production  [production-readiness only, MANUAL button]  scripts/deploy/ci/deploy.sh
        refuse unless this run is on the designated branch at the packaged commit (before any connection)
        -> upload tools + package over SSH (strict host-key checking) -> server dry run -> server apply (--non-interactive)
        -> outside smoke check, which includes: the live site must report THIS release (X-SocietyFlow-Release)
```
What the server does in `apply-release.sh --non-interactive` (all of it logged): verify checksums -> **stop with exit 3, changing nothing, if the release has a pending migration** -> exclusive lock ->
back up code + `.env` copy (600) + database (verified) -> record upload names and **business-table record counts** -> maintenance mode -> copy the release over the code (never deletes,
never touches `.env`, `storage/`, `public/storage`) -> `composer install --no-dev` -> rebuild caches -> `queue:restart` -> back online -> compare record counts (any lost row = failure) ->
smoke check (home, login, `/owners` protected, health, no error pages, no demo-credentials card, **and the live site reports this release**) -> `deployments.log` line (with the release's `tag=`), and
`LAST_SUCCESSFUL_RELEASE` only after everything passed.

**If anything fails after the files were copied** (composer, an unwritable folder, the smoke check, the release check) the unattended run **restores the previous code by itself and brings the site back up** - safe because this mode never runs a
migration, so there is no database change the old code could clash with. It records `ROLLED_BACK`, runs the smoke check again on the restored site and exits non-zero (the pipeline step goes red). If that rollback *also* fails it says
`AUTOMATIC ROLLBACK FAILED`, records `ROLLBACK_FAILED`, and leaves the site in maintenance mode with the exact recovery command - then a person must act. An *interactive* run (you at an SSH prompt) does the opposite on purpose: it stops,
leaves the site in maintenance mode and tells you, because you are there to decide. Lost records are reported (`FAILED_CHECKS`) but never answered with a code swap: restoring code cannot bring rows back.
Exit codes of `apply-release.sh`: 0 deployed; **3 = stopped before changing anything because a migration is pending** (a failing command that happens to exit 3 is reported as 1, so the two cannot be confused); anything else = failed.

**How "the live site serves this release" is checked.** The packaged deploy puts `RELEASE.txt` (with `release_id=`) in the application root. `GET /api/v1/health` then answers with the usual body plus a header
`X-SocietyFlow-Release: <tag>`, where `tag` = the first 16 hex characters of `sha256(release id)` - a hash, so the public answer reveals neither the build time nor the commit. After the copy the deploy polls that header for up to
60 s (PHP may keep serving the old compiled code for a moment) and fails if it is missing or belongs to another release. You can do the same by hand:
`curl -sI https://societyflow.joboapps.com/api/v1/health | grep -i x-societyflow-release`, and map a tag back to a release with `grep tag=<tag> ~/backups/societyflow/deployments.log`
or `printf %s <release id> | sha256sum | cut -c1-16`. A site deployed by hand (no `RELEASE.txt`) sends no header; the first packaged deployment is the one that starts sending it.

**Migrations are never run by the pipeline.** A release that contains one needs the interactive deploy (section 6), where you see the SQL and type `MIGRATE` after a verified backup.
Production deployments therefore split naturally into two kinds: code-only releases (automatic-capable) and releases with schema changes (supervised).

## 3. Branches and what triggers deployment

* **Deploys from:** `production-readiness` (as you asked for the first phase). It is the only branch with the deploy step. To move deployment to `main` later: merge `integration/main-merge`
  into `main` by pull request, then rename the key `production-readiness:` to `main:` in `bitbucket-pipelines.yml` (one line) and set a branch restriction on `main` (section 5).
* **Every other branch / pull request:** tests and a package build only - nothing can reach the server.
* **Manual button:** Pipelines -> the latest `production-readiness` run -> step 3 "Deploy to production" -> Run. Or **Run pipeline** -> custom `server-dry-run` (changes nothing live)
  or `production-deploy` (builds and deploys the commit you pick).
* **The designated branch is enforced twice.** (1) The deploy step exists only under `branches: production-readiness`. (2) Bitbucket lets a *custom* pipeline be started on **any** branch, so `deploy.sh`
  itself refuses - before opening a single connection - unless `BITBUCKET_BRANCH` equals `PROD_DEPLOY_BRANCH` (default `production-readiness`) and the package was built from `BITBUCKET_COMMIT`. This covers the dry run
  too: `server-dry-run` on another branch is refused. When production moves to `main`, change the key under `branches:` **and** set `PROD_DEPLOY_BRANCH=main`. The script check protects against honest mistakes;
  a branch can also edit this file, which is why section 5 asks for the Bitbucket-side restriction where your plan has it.
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
9. **Can the pipeline reach SSH at all? (decision for you; I changed no firewall.)** Bitbucket Pipelines connects from shared Amazon (AWS) addresses that change. Atlassian states it does not recommend using those ranges as a security control
   and that IP rules should not be the only protection ([Atlassian: IP addresses](https://support.atlassian.com/bitbucket-cloud/docs/what-are-the-bitbucket-cloud-ip-addresses-i-should-use-to-configure-my-corporate-firewall/)).
   If the CloudPanel firewall only lets your own address reach port 22, the deploy fails at its first step (`ssh: connect to host ... timed out`). Options, in the order I would consider them:
   (a) leave port 22 reachable but **key-only** for the site user (no password login; CloudPanel's SSH keys are key based - confirm password authentication is off) and rely on the deploy key + host-key checking;
   (b) restrict to Atlassian's smaller published range: add `atlassian-ip-ranges: true` to the deploy step with `size: 4x` (more build minutes; "not available in 1x and 2x steps"; list at `ip-ranges.atlassian.com`) - UNVERIFIED on your plan;
   (c) a self-hosted Bitbucket runner on the server (outbound connection only, port 22 can stay closed) - more setup, not built here.
10. **PHP must pick up the new files promptly.** PHP-FPM keeps compiled code (OPcache). The release check waits up to 60 s for the new release to be served; if the files are right (`sha256sum -c RELEASE.sha256` passes in the app folder) but the old release
    keeps answering, the site's PHP-FPM needs a reload (CloudPanel -> Sites -> your site -> PHP settings; saving them is expected to reload it - UNVERIFIED on your version) or `opcache.validate_timestamps=1` for the site.
    Not inspected: I had no server access. `inspect-server.sh` now prints the OPcache settings that apply to the site's pool and marks `[!!]` when `validate_timestamps` is off or `revalidate_freq` is long.

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
| `PROD_DEPLOY_BRANCH` | `production-readiness` | optional; the only branch `deploy.sh` will deploy from (default `production-readiness`; set `main` when production moves there) |

No private key, password, `.env` content or token goes into the repository, the YAML or a variable of this list.

**Who can deploy - the trust model (read this once).** The deploy key and the `PROD_*` variables let a pipeline step run commands on the server as the site user, and a deployed package *is* code that the site executes. So anyone who can
edit `bitbucket-pipelines.yml` or `deploy.sh` on some branch **and** run a pipeline for the Production environment is effectively a server administrator. The controls, strongest first:
1. **Deployment permissions** on Production (Repository settings -> Pipelines -> Deployments -> Production): only administrators (or named people) may run a deployment, and only the designated branch may deploy. Atlassian lists this under the
   **Premium** tier - check your plan; if it is missing, the next two are what you have.
2. **Repository access:** give write access only to people you would trust with the server. Do not add collaborators "just to try".
3. **The script's own branch check** (above): stops honest mistakes such as running `production-deploy` on the wrong branch; it cannot stop someone who edits it on that branch.
Also recommended for `production-readiness` (and later `main`) where your plan offers branch permissions / merge checks: changes arrive by pull request, with a passing pipeline and at least one approval, no force push, no branch deletion.
That is what makes "reviewed code only" true - the pipeline alone cannot guarantee it.

## 6. First deployment (supervised) and every release that contains a migration

The live site is older than this branch, so the first release will carry a **long list of pending migrations**. It must be done by a person:

1. `inspect-server.sh` -> read it; fix anything marked `[!!]`. Take a CloudPanel site backup.
2. In Bitbucket run the custom pipeline **`server-dry-run`**: it uploads the tools and the package and runs the server dry run (nothing live changes). Read the new / changed file lists and the **pending migration list**.
3. Read `MIGRATIONS-REVIEW.txt` (in the package folder built locally with `New-ReleasePackage.ps1`, or `INCLUDED.txt` in the pipeline artifact) and the flagged migrations; decide each is safe for the live data (DEPLOYMENT_CLOUDPANEL.md section 8 classifies the October ones).
4. SSH in and run the interactive deploy: `bash ~/deploy-tools/apply-release.sh --package ~/incoming/<package> --apply` - it backs up code and database (verified), shows the SQL, and waits for you to type `MIGRATE`.
5. Verify (section 7). From the next code-only release on, the pipeline step can be used.

The dry run unpacks the package into `~/releases/<release id>`; the real run **reuses that folder, but only if it is exactly the package** (same files, same checksum list, every file matching it). A different, edited, missing or extra file stops the
script before anything is copied and names the folder to look at. So "dry run, read, then apply" and "run the deployment step again after fixing something" both work. (A rehearsal of the whole chain found that the first version refused its own
dry-run folder, which would have made every automated deployment fail; this is covered by `DeploymentScriptsBehaviourTest`.)

## 7. Pre / post deployment verification (data preservation)

Automatic (the server script does these and fails loudly): checksums of every file, uploads before / after (nothing missing), **record counts, highest ids and money totals of 29 business tables before vs after** (owners, flats, tenants, residents,
users, maintenance bills, payments, expenses, facility bookings, visitors, complaints, notices, documents, settings, ...), smoke check **including that the live site reports the release just deployed**, new log errors, `deployments.log`.
By hand once per release: sign in as an admin of a **test** society; open `https://societyflow.joboapps.com/owners` and compare the owner count with `fingerprint-before-<stamp>.txt` (`owners` row); open one owner's edit page (Villa Area / Villa No. load);
open two receipts and a private document; check the bell; log in once from the mobile app. Compare `~/backups/societyflow/uploads-before-*.txt` with `uploads-after-*.txt` (only additions).
Checklist before enabling any automation: [ ] `inspect-server.sh` clean [ ] DB backup restored once in a scratch database and counts compared [ ] SSH deploy key + known host verified [ ] `TRUSTED_PROXIES` decided [ ] Nginx upload rule in place
[ ] cron / queue decided [ ] demo-credential accounts handled [ ] a rollback rehearsed on the dry-run package [ ] you have read `deployments.log` format and know where `LAST_SUCCESSFUL_RELEASE` is.

## 8. Recovery

* **Which release is live / was the last good one:** `cat <app>/RELEASE.txt` (id, commit, build time); `cat ~/backups/societyflow/LAST_SUCCESSFUL_RELEASE`; `tail ~/backups/societyflow/deployments.log` (every attempt: OK / ROLLED_BACK / FAILED_CHECKS with the previous release
  and the release's `tag=`, which is what the live health header shows). From outside: `curl -sI https://societyflow.joboapps.com/api/v1/health | grep -i x-societyflow-release`.
* **Code:** `bash ~/deploy-tools/rollback-code.sh --backup ~/backups/societyflow/code-<stamp>.tar.gz --apply`. Restores the previous code and `vendor/`; never touches `.env`, uploads or the database. The unattended (pipeline) run already does this by itself when any step after the copy fails, including a failed smoke or release check; read `deployments.log` for `ROLLED_BACK` / `ROLLBACK_FAILED`.
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
9. **Cost / time (measured on the dev PC, not in Bitbucket).** Every in-memory SQLite test re-runs all migrations (~3 s each), so a full SQLite run needed ~39 minutes even in 8 parallel shards. Against MariaDB the schema is migrated once per
   process and each test rolls back in a transaction: the test step therefore uses a MariaDB 10.4.32 service container. Recorded runs: MariaDB, 8 shards, ~4 minutes; MariaDB, **one process (what the pipeline runs), 27.5 minutes for 2,635 tests**, all
   passing. A Bitbucket runner will differ (fewer cores, a container start, `composer install`, extension builds), so expect roughly half an hour per push and every push spends build minutes (a free plan's monthly allowance is a few such runs).
   Read the real duration after the first run. If it is too much: split the suite over parallel steps (the same `--filter` slicing as `scripts/run-test-shards.ps1`, which cut the MariaDB run from 27.5 to ~4 minutes locally), or run the full suite only on
   `production-readiness` and pull requests into it, with a short gate (lint, `DeploymentArtifactsTest`, the security suites) on other branches.
10. **A check can be blocked by Cloudflare or a firewall** (a bot challenge or a 403 for the requesting address). Two checks request the public URL: the server asks for its own `APP_URL` (a block there makes the unattended run
    roll the release back as "smoke check failed", every time - the `FAIL` lines show a 403/503/challenge page instead of the real pages), and the pipeline asks from outside (a block there fails the step but leaves the new release live; `deploy.sh` says so).
    In both cases look at the `FAIL` lines and open the site in a browser first; the fix is a Cloudflare / firewall rule for that address, not a rollback. Neither check has been tried against the real site.
