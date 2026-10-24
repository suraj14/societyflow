#!/usr/bin/env bash
# apply-release.sh - put a SocietyFlow release package on the live CloudPanel site, safely. Run it as the SITE USER (not root).
#
#   DRY RUN (default - changes nothing on the live site):
#       bash apply-release.sh --package /home/joboapps-societyflow/incoming/societyflow-<id>.tar.gz
#   APPLY (asks you to type the release id; asks again before touching the database):
#       bash apply-release.sh --package ... --apply
#
#   options  --app-dir DIR        default /home/joboapps-societyflow/htdocs/societyflow.joboapps.com
#            --site-user USER     default joboapps-societyflow          (only used for checks)
#            --domain NAME        default societyflow.joboapps.com      (checks the Nginx document root when readable)
#            --backup-dir DIR     default /home/joboapps-societyflow/backups/societyflow
#            --releases-dir DIR   default /home/joboapps-societyflow/releases     (where the package is unpacked)
#            --skip-db-backup     DO NOT make a database backup (only for a release with no migrations; asks you to confirm)
#            --skip-composer      do not run composer (only if composer.lock did not change)
#            --clear-app-cache    also run cache:clear (resets rate-limit counters and the settings cache)
#            --no-queue-restart   do not run queue:restart
#   env      PHP_BIN (default php; use /usr/bin/php8.2 etc. to match the site's PHP), COMPOSER_BIN (default composer)
#
# What it NEVER does: delete or overwrite .env, storage/, public/storage, bootstrap/cache contents it did not create, databases or uploads;
# run migrate:fresh / rollback / seed / db:wipe; run storage:setup; restart Nginx / PHP-FPM / MariaDB; print a secret.
# Files that exist on the server but are not in the package are LEFT IN PLACE (nothing is mirrored or removed).
# If any step fails after the site was put into maintenance mode, the script STOPS and leaves it in maintenance mode so a half-updated
# site is not exposed; it tells you whether to fix forward or to run rollback-code.sh.
set -Eeuo pipefail
umask 022

APP_DIR="/home/joboapps-societyflow/htdocs/societyflow.joboapps.com"
SITE_USER="joboapps-societyflow"
DOMAIN="societyflow.joboapps.com"
BACKUP_DIR="/home/joboapps-societyflow/backups/societyflow"
RELEASES_DIR="/home/joboapps-societyflow/releases"
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
PACKAGE=""; APPLY=0; SKIP_DB=0; SKIP_COMPOSER=0; CLEAR_APP_CACHE=0; QUEUE_RESTART=1

while [ $# -gt 0 ]; do
    case "$1" in
        --package)          PACKAGE="$2"; shift 2;;
        --app-dir)          APP_DIR="$2"; shift 2;;
        --site-user)        SITE_USER="$2"; shift 2;;
        --domain)           DOMAIN="$2"; shift 2;;
        --backup-dir)       BACKUP_DIR="$2"; shift 2;;
        --releases-dir)     RELEASES_DIR="$2"; shift 2;;
        --apply)            APPLY=1; shift;;
        --skip-db-backup)   SKIP_DB=1; shift;;
        --skip-composer)    SKIP_COMPOSER=1; shift;;
        --clear-app-cache)  CLEAR_APP_CACHE=1; shift;;
        --no-queue-restart) QUEUE_RESTART=0; shift;;
        -h|--help)          sed -n '2,30p' "$0"; exit 0;;
        *) echo "unknown option: $1" >&2; exit 2;;
    esac
done

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
STAMP="$(date -u +%Y%m%d-%H%M%S)"
DOWN=0
say()  { printf '%s\n' "$*"; }
step() { printf '\n== %s\n' "$*"; }
ok()   { printf '   ok    %s\n' "$*"; }
warn() { printf '   WARN  %s\n' "$*"; }
down_hint() {
    [ "$DOWN" = 1 ] || return 0
    printf 'The site is IN MAINTENANCE MODE and has been left that way on purpose (a half-updated site must not be shown to users).\n' >&2
    printf '  - to go back to the previous code:   bash %s/rollback-code.sh --backup %s --apply\n' "$HERE" "${BACKUP_CODE:-<code backup>}" >&2
    printf '  - or fix the cause, finish the remaining steps by hand, then:   cd %s && %s artisan up\n' "$APP_DIR" "$PHP_BIN" >&2
}
die()  { printf '\nSTOPPED: %s\n' "$*" >&2; down_hint; exit 1; }
art()  { ( cd "$APP_DIR" && "$PHP_BIN" artisan "$@" ); }
env_val() { local v; v="$(grep -E "^$1=" "$APP_DIR/.env" 2>/dev/null | tail -n1 | cut -d= -f2-)"; v="${v%\"}"; v="${v#\"}"; v="${v%\'}"; v="${v#\'}"; printf '%s' "$v"; }
confirm() { # confirm "<prompt>" "<exact text to type>"
    local answer; printf '\n%s\n   type exactly "%s" to continue, anything else stops here: ' "$1" "$2"
    IFS= read -r answer || answer=""
    [ "$answer" = "$2" ] || die "not confirmed - nothing further was changed."
}
on_error() {
    local code=$? line=$1
    printf '\nFAILED at line %s (exit %s).\n' "$line" "$code" >&2
    down_hint
    exit "$code"
}
trap 'on_error $LINENO' ERR

# ---------------------------------------------------------------------------------------------------- 0. preconditions
step "0. Preconditions   (mode: $([ "$APPLY" = 1 ] && echo APPLY || echo 'DRY RUN - the live site is not changed'))"
[ -n "$PACKAGE" ] || die "--package is required"
[ "$(id -u)" -ne 0 ] || die "do not run this as root: files would become root-owned and PHP-FPM could no longer write them. Use the site user ($SITE_USER)."
[ "$(id -un)" = "$SITE_USER" ] || warn "running as $(id -un), the site user is $SITE_USER"
for t in tar gzip sha256sum cmp find df stat awk; do command -v "$t" >/dev/null 2>&1 || die "required tool not found: $t"; done
command -v "$PHP_BIN" >/dev/null 2>&1 || die "PHP not found: $PHP_BIN (set PHP_BIN=/usr/bin/php8.2)"
[ -f "$PACKAGE" ] || die "package not found: $PACKAGE"
for p in artisan .env bootstrap/app.php public/index.php vendor/autoload.php; do [ -e "$APP_DIR/$p" ] || die "$APP_DIR/$p missing - this is not the live application directory"; done
ok "live app dir looks right: $APP_DIR"
[ -f "$APP_DIR/storage/framework/down" ] && warn "the site is already in maintenance mode"
PHPV="$("$PHP_BIN" -r 'echo PHP_VERSION;')"
"$PHP_BIN" -r 'exit(version_compare(PHP_VERSION, "8.1.0", ">=") ? 0 : 1);' || die "PHP $PHPV is older than 8.1 (composer.json requires ^8.1)"
ok "PHP CLI $PHPV ($PHP_BIN)"
MISSING_EXT=""
PHP_MODS="$("$PHP_BIN" -m 2>/dev/null)"   # captured once: `php -m | grep -q` can die of SIGPIPE under pipefail
for e in ctype dom fileinfo filter gd hash iconv json libxml mbstring openssl pcre pdo_mysql session simplexml tokenizer xml xmlreader xmlwriter zip zlib curl; do
    grep -qix "$e" <<<"$PHP_MODS" || MISSING_EXT="$MISSING_EXT $e"
done
[ -z "$MISSING_EXT" ] || die "PHP extensions missing for the CLI:$MISSING_EXT (composer install would refuse; gd and zip are required by phpoffice/phpspreadsheet)"
ok "required PHP extensions present"

if [ -f "$PACKAGE.sha256" ]; then
    ( cd "$(dirname "$PACKAGE")" && sha256sum -c --quiet "$(basename "$PACKAGE").sha256" ) || die "the package does not match its .sha256 file - it was damaged or changed in transit; upload it again"
    ok "package checksum verified"
else
    warn "no $(basename "$PACKAGE").sha256 next to the package - integrity not checked (RELEASE.sha256 inside is still checked after unpacking)"
fi
PKG_BYTES="$(stat -c %s "$PACKAGE")"
AVAIL_KB="$(df -Pk "$APP_DIR" | awk 'NR==2 {print $4}')"
NEED_KB=$(( PKG_BYTES / 1024 * 4 + 1024 * 1024 ))
[ "$AVAIL_KB" -ge "$NEED_KB" ] || die "free space on the app volume is ${AVAIL_KB} KiB; about ${NEED_KB} KiB is needed (package unpacked + code backup + composer)"
ok "free space: $(( AVAIL_KB / 1024 )) MiB"

VHOST=""; for c in "/etc/nginx/sites-enabled/$DOMAIN.conf" "/etc/nginx/sites-enabled/$DOMAIN" "/etc/nginx/conf.d/$DOMAIN.conf"; do [ -r "$c" ] && VHOST="$c" && break; done
if [ -n "$VHOST" ]; then
    ROOT_LINE="$(grep -E '^\s*root\s' "$VHOST" | head -n1 | sed -E 's/^\s*root\s+//; s/;\s*$//')"
    [ "$ROOT_LINE" = "$APP_DIR/public" ] && ok "Nginx document root is $ROOT_LINE" || die "Nginx document root is '$ROOT_LINE' but this script deploys to $APP_DIR/public - fix the mismatch first"
else
    warn "cannot read the Nginx vhost; confirm in CloudPanel that the root directory is $APP_DIR/public"
fi
if [ -L "$APP_DIR/public/storage" ]; then ok "public/storage is a symlink -> $(readlink "$APP_DIR/public/storage")"
elif [ -d "$APP_DIR/public/storage" ]; then warn "public/storage is a REAL directory with files in it: it will be left exactly as it is"
else warn "public/storage does not exist; it will be created with storage:link at the end"; fi

# ---------------------------------------------------------------------------------------------------- 1. unpack and verify
step "1. Unpack the package into a staging folder (outside the web root) and verify it"
RELEASE_ID="$(tar -xzOf "$PACKAGE" RELEASE.txt 2>/dev/null | grep -E '^release_id=' | cut -d= -f2)"
[ -n "$RELEASE_ID" ] || die "the package has no RELEASE.txt - it was not made by New-ReleasePackage.ps1"
STAGE="$RELEASES_DIR/$RELEASE_ID"
mkdir -p "$RELEASES_DIR"; chmod 700 "$RELEASES_DIR" 2>/dev/null || true
if [ -e "$STAGE" ] && [ -n "$(ls -A "$STAGE" 2>/dev/null)" ]; then
    die "$STAGE already exists and is not empty (this release was unpacked before). Check it, remove it yourself if it is stale, and run again."
fi
mkdir -p "$STAGE"
tar --no-same-owner --no-same-permissions -xzf "$PACKAGE" -C "$STAGE"
( cd "$STAGE" && sha256sum -c --quiet RELEASE.sha256 ) || die "files in the package do not match RELEASE.sha256"
ok "release $RELEASE_ID unpacked: $(find "$STAGE" -type f | wc -l) files, every hash matches RELEASE.sha256"
BAD="$(find "$STAGE" \( -name '.env' -o -name '.env.*' -o -name '*.sqlite' -o -name '*.pem' -o -name '*.key' \) -o -path "$STAGE/storage" -o -path "$STAGE/vendor" -o -path "$STAGE/public/storage" | head -3 || true)"
[ -z "$BAD" ] || die "the package contains something that must never be deployed: $BAD"
ok "package holds no .env, key, database, storage or vendor"
grep -E '^(built_utc|source_head|uncommitted_paths_at_build|files)=' "$STAGE/RELEASE.txt" | sed 's/^/         /'
[ -f "$APP_DIR/RELEASE.txt" ] && { say "         currently live:"; grep -E '^(release_id|built_utc)=' "$APP_DIR/RELEASE.txt" | sed 's/^/           /'; } || say "         currently live: no RELEASE.txt (first packaged deploy)"

# ---------------------------------------------------------------------------------------------------- 2. what would change
step "2. What would change on the live site (nothing is copied yet)"
LISTS="$STAGE/.preview"; mkdir -p "$LISTS"
: > "$LISTS/add.txt"; : > "$LISTS/change.txt"; : > "$LISTS/same.txt"
( cd "$STAGE" && find . -type f -not -path './.preview/*' | sed 's|^\./||' | sort ) | while IFS= read -r f; do
    if [ ! -e "$APP_DIR/$f" ]; then echo "$f" >> "$LISTS/add.txt"
    elif cmp -s "$STAGE/$f" "$APP_DIR/$f"; then echo "$f" >> "$LISTS/same.txt"
    else echo "$f" >> "$LISTS/change.txt"; fi
done
( cd "$APP_DIR" && find app config routes resources database/migrations database/seeders public bootstrap/app.php -type f -not -path 'public/storage/*' 2>/dev/null | sort ) > "$LISTS/live-code.txt"
( cd "$STAGE" && find . -type f | sed 's|^\./||' | sort ) > "$LISTS/stage-all.txt"
comm -23 "$LISTS/live-code.txt" "$LISTS/stage-all.txt" > "$LISTS/live-only.txt"
say "   new files:      $(wc -l < "$LISTS/add.txt")"
say "   changed files:  $(wc -l < "$LISTS/change.txt")"
say "   identical:      $(wc -l < "$LISTS/same.txt")"
say "   only on the server (kept as they are, NOT deleted): $(wc -l < "$LISTS/live-only.txt")"
say "   full lists: $LISTS/{add,change,live-only}.txt"
say "   -- new migrations in this release:"; grep '^database/migrations/' "$LISTS/add.txt" | sed 's/^/        /' || say "        (none)"
say "   -- sample of changed files:"; head -n 15 "$LISTS/change.txt" | sed 's/^/        /'
LOCK_CHANGED=0; cmp -s "$STAGE/composer.lock" "$APP_DIR/composer.lock" || LOCK_CHANGED=1
say "   composer.lock changed: $([ "$LOCK_CHANGED" = 1 ] && echo YES - composer install will run || echo no)"
if grep -qE '^(\.env|storage/|public/storage|vendor/)' "$LISTS/add.txt" "$LISTS/change.txt"; then die "the preview touches a protected path (.env, storage, public/storage or vendor) - this should be impossible; do not continue"; fi
ok "no protected path (.env, storage/, public/storage, vendor/) would be touched"

if [ "$APPLY" != 1 ]; then
    printf '\nDRY RUN complete. The live site was not changed. The package is unpacked at %s (outside the web root).\n' "$STAGE"
    printf 'Read the lists above, then run the same command with --apply.\n'
    exit 0
fi

# ---------------------------------------------------------------------------------------------------- 3. confirm, back up
confirm "About to deploy $RELEASE_ID to the LIVE site $DOMAIN ($APP_DIR)." "$RELEASE_ID"
mkdir -p "$BACKUP_DIR"; chmod 700 "$BACKUP_DIR" 2>/dev/null || true
LOG="$BACKUP_DIR/deploy-$STAMP.log"
exec > >(tee -a "$LOG") 2>&1
say "log: $LOG"

step "3a. Back up the current code (not storage, not uploads: those are never touched)"
BACKUP_CODE="$BACKUP_DIR/code-$STAMP.tar.gz"
( umask 077
  tar -czf "$BACKUP_CODE" -C "$APP_DIR" \
      --exclude=./storage --exclude=./public/storage --exclude=./node_modules --exclude=./.git \
      --exclude=./.env --exclude='./.env.*' --exclude='./bootstrap/cache/*.php' --exclude='./database/*.sqlite*' . )
gzip -t "$BACKUP_CODE"
BACKUP_LIST="$BACKUP_DIR/code-$STAMP.list.txt"
tar -tzf "$BACKUP_CODE" > "$BACKUP_LIST"
grep -qE '^(\./)?artisan$' "$BACKUP_LIST" || die "the code backup does not contain artisan"
grep -qE '^(\./)?vendor/autoload\.php$' "$BACKUP_LIST" || die "the code backup does not contain vendor/ - it could not restore the previous release"
( cd "$BACKUP_DIR" && sha256sum "$(basename "$BACKUP_CODE")" > "$(basename "$BACKUP_CODE").sha256" )
ok "code backup: $BACKUP_CODE ($(stat -c %s "$BACKUP_CODE") bytes, $(wc -l < "$BACKUP_LIST") entries, gzip ok, artisan and vendor present)"
( umask 077; cp -p "$APP_DIR/.env" "$BACKUP_DIR/env-$STAMP.bak"; chmod 600 "$BACKUP_DIR/env-$STAMP.bak" )
ok ".env copied to $BACKUP_DIR/env-$STAMP.bak (mode 600 - contains secrets, keep it private; the deploy does not modify .env)"
[ -f "$APP_DIR/RELEASE.txt" ] && cp -p "$APP_DIR/RELEASE.txt" "$BACKUP_DIR/previous-RELEASE-$STAMP.txt" || true

step "3b. Back up the database"
DB_BACKUP=""
if [ "$SKIP_DB" = 1 ]; then
    warn "--skip-db-backup given: NO database backup will be made"
    confirm "You are skipping the database backup. This is only safe when this release has no migrations." "NO DB BACKUP"
else
    DB_OUT="$(APP_DIR="$APP_DIR" BACKUP_DIR="$BACKUP_DIR" PHP_BIN="$PHP_BIN" bash "$HERE/db-backup.sh" --label "pre-$RELEASE_ID")" || die "the database backup failed - nothing was changed on the site"
    printf '%s\n' "$DB_OUT" | sed 's/^/   /'
    DB_BACKUP="$(printf '%s\n' "$DB_OUT" | grep '^BACKUP_FILE=' | tail -n1 | cut -d= -f2-)"
    [ -s "$DB_BACKUP" ] || die "the database backup file is missing or empty"
fi

step "3c. Record the uploads (names and sizes only) so they can be compared afterwards"
UPL_BEFORE="$BACKUP_DIR/uploads-before-$STAMP.txt"
( cd "$APP_DIR" && find -L storage/app public/storage -type f -printf '%s %p\n' 2>/dev/null | sort -k2 ) > "$UPL_BEFORE" || true
ok "$(wc -l < "$UPL_BEFORE") upload files recorded in $UPL_BEFORE"
LOG_FILE="$(ls -t "$APP_DIR"/storage/logs/*.log 2>/dev/null | head -n1 || true)"; LOG_OFFSET=0; [ -n "$LOG_FILE" ] && LOG_OFFSET="$(stat -c %s "$LOG_FILE")"

# ---------------------------------------------------------------------------------------------------- 4. maintenance mode and copy
step "4. Maintenance mode, then copy the release over the code (adds and overwrites only; nothing is deleted)"
art down --retry=60
DOWN=1
ok "site is in maintenance mode"
( cd "$STAGE" && tar --exclude='./.preview' -cf - . ) | ( cd "$APP_DIR" && tar -xpf - --no-same-owner )
( cd "$APP_DIR" && sha256sum -c --quiet RELEASE.sha256 ) || die "after copying, files on the server do not match RELEASE.sha256"
ok "the live files now match RELEASE.sha256 exactly"

# ---------------------------------------------------------------------------------------------------- 5. dependencies
step "5. PHP dependencies"
if [ "$SKIP_COMPOSER" = 1 ]; then warn "--skip-composer given (the autoloader is NOT refreshed: new classes may not load)"
else
    command -v "$COMPOSER_BIN" >/dev/null 2>&1 || die "composer not found ($COMPOSER_BIN)"
    # Always `install --no-dev`, never `dump-autoload --no-dev`: install makes vendor/ match composer.lock exactly (dev packages removed,
    # nothing downloaded when it already matches) and rebuilds the optimized autoloader. A bare dump-autoload --no-dev on a vendor/ that
    # still holds dev packages leaves their service providers listed but unloadable, and the site fails to boot.
    ( cd "$APP_DIR" && "$COMPOSER_BIN" install --no-dev --optimize-autoloader --no-interaction --prefer-dist )
    ok "composer install --no-dev finished ($([ "$LOCK_CHANGED" = 1 ] && echo 'composer.lock changed' || echo 'composer.lock unchanged: nothing to download'))"
fi

# ---------------------------------------------------------------------------------------------------- 6. caches (clear)
step "6. Clear stale framework caches (before migrating, so the new code and settings are read)"
art config:clear; art route:clear; art view:clear; art event:clear || true
[ "$CLEAR_APP_CACHE" = 1 ] && { art cache:clear; ok "application cache cleared"; } || say "   (application cache left alone; use --clear-app-cache to flush it)"

# ---------------------------------------------------------------------------------------------------- 7. migrations - always a human decision
step "7. Database migrations"
PENDING="$(art migrate:status 2>/dev/null | grep -E 'Pending' || true)"
if [ -z "$PENDING" ]; then
    ok "no pending migrations"
else
    say "   PENDING:"; printf '%s\n' "$PENDING" | sed 's/^/     /'
    say "   --- the SQL they would run (preview only, nothing executed; first 120 lines, full text in $LISTS/migrate-pretend.txt):"
    art migrate --pretend --force > "$LISTS/migrate-pretend.txt" 2>&1 || warn "the --pretend preview itself reported an error: read $LISTS/migrate-pretend.txt before migrating"
    sed -n '1,120p' "$LISTS/migrate-pretend.txt" | sed 's/^/     /'
    [ -n "$DB_BACKUP" ] && say "   database backup taken before this step: $DB_BACKUP"
    confirm "Run these migrations on the LIVE database now? (php artisan migrate --force - no seeding, no rollback)" "MIGRATE"
    art migrate --force
    ok "migrations ran"
    PENDING_AFTER="$(art migrate:status 2>/dev/null | grep -c 'Pending' || true)"
    [ "$PENDING_AFTER" = 0 ] && ok "no pending migrations left" || die "$PENDING_AFTER migration(s) still pending after migrate"
fi

# ---------------------------------------------------------------------------------------------------- 8. storage link, permissions
step "8. Storage link and permissions (storage:setup is deliberately NOT run)"
if [ -L "$APP_DIR/public/storage" ] || [ -d "$APP_DIR/public/storage" ]; then ok "public/storage exists - left untouched"
else art storage:link && ok "public/storage linked to storage/app/public"; fi
for p in storage/logs storage/framework bootstrap/cache; do [ -w "$APP_DIR/$p" ] && ok "$p writable" || die "$p is not writable by $(id -un) - PHP-FPM would fail; fix ownership in CloudPanel before going on"; done

# ---------------------------------------------------------------------------------------------------- 9. caches (rebuild), workers, up
step "9. Rebuild caches, restart workers, leave maintenance mode"
art config:cache
art route:cache
art view:cache
ok "config, route and view caches rebuilt"
[ "$QUEUE_RESTART" = 1 ] && { art queue:restart; ok "queue workers told to restart (a no-op when QUEUE_CONNECTION=sync)"; }
art up
DOWN=0
ok "site is live again"

# ---------------------------------------------------------------------------------------------------- 10. verification
step "10. Verification"
( cd "$APP_DIR" && sha256sum -c --quiet RELEASE.sha256 ) && ok "release files match RELEASE.sha256" || warn "release file hash mismatch"
art --version | sed 's/^/         /'
UPL_AFTER="$BACKUP_DIR/uploads-after-$STAMP.txt"
( cd "$APP_DIR" && find -L storage/app public/storage -type f -printf '%s %p\n' 2>/dev/null | sort -k2 ) > "$UPL_AFTER" || true
LOST="$(comm -23 "$UPL_BEFORE" "$UPL_AFTER" | wc -l)"
[ "$LOST" = 0 ] && ok "uploads: nothing missing or changed ($(wc -l < "$UPL_AFTER") files now, $(comm -13 "$UPL_BEFORE" "$UPL_AFTER" | wc -l) new since the snapshot)" || warn "$LOST upload file(s) from the snapshot are missing or changed in size: compare $UPL_BEFORE and $UPL_AFTER"
APP_URL="$(env_val APP_URL)"
if command -v curl >/dev/null 2>&1 && [ -n "$APP_URL" ]; then
    CODE="$(curl -sS -m 30 -o /dev/null -w '%{http_code}' "$APP_URL/login" 2>/dev/null || echo 000)"
    [ "$CODE" = 200 ] && ok "GET $APP_URL/login -> 200" || warn "GET $APP_URL/login -> $CODE"
    HEALTH="$(curl -sS -m 30 "$APP_URL/api/v1/health" 2>/dev/null || true)"
    printf '%s' "$HEALTH" | grep -q '"application":"societyflow"' && ok "GET $APP_URL/api/v1/health identifies as societyflow" || warn "health endpoint answer unexpected: ${HEALTH:0:120}"
else warn "curl or APP_URL missing - run the browser checks in docs/DEPLOYMENT_CLOUDPANEL.md section 9"; fi
if [ -n "$LOG_FILE" ] && [ -f "$LOG_FILE" ]; then
    NEW_ERR="$(tail -c +"$((LOG_OFFSET + 1))" "$LOG_FILE" | grep -c '\.ERROR\|\.CRITICAL' || true)"
    [ "$NEW_ERR" = 0 ] && ok "no new ERROR/CRITICAL lines in $(basename "$LOG_FILE")" || warn "$NEW_ERR new ERROR/CRITICAL line(s) in $(basename "$LOG_FILE") since the deploy began - read them"
fi

printf '\nDONE. Release %s is live.\n' "$RELEASE_ID"
printf '  code backup : %s\n  .env copy   : %s\n  db backup   : %s\n  log         : %s\n' "$BACKUP_CODE" "$BACKUP_DIR/env-$STAMP.bak" "${DB_BACKUP:-none}" "$LOG"
printf '  rollback    : bash %s/rollback-code.sh --backup %s --apply     (code only; see docs/DEPLOYMENT_CLOUDPANEL.md section 8 for the database)\n' "$HERE" "$BACKUP_CODE"
printf '  The unpacked package stays in %s (delete it yourself when you no longer need it).\n' "$STAGE"
