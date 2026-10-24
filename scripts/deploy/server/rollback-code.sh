#!/usr/bin/env bash
# rollback-code.sh - put the PREVIOUS CODE back from the backup that apply-release.sh made. Run as the site user.
#
#   DRY RUN (default): shows what the backup contains and how it differs from the live code.
#       bash rollback-code.sh --backup /home/joboapps-societyflow/backups/societyflow/code-<stamp>.tar.gz
#   APPLY (asks you to type ROLLBACK):
#       bash rollback-code.sh --backup ... --apply
#
# This restores CODE ONLY: the files that were on the server before the release (including the old vendor/). It does not touch .env,
# storage/, public/storage, uploads or the database. Files the new release ADDED are left in place (harmless: nothing refers to them).
#
# THE DATABASE IS NOT ROLLED BACK. If the release ran migrations, check docs/DEPLOYMENT_CLOUDPANEL.md section 8 first: additive
# migrations (new tables / nullable columns) are compatible with the old code; anything else needs its own recovery plan. Never run
# `php artisan migrate:rollback` on production.
set -Eeuo pipefail
umask 022

APP_DIR="/home/joboapps-societyflow/htdocs/societyflow.joboapps.com"
PHP_BIN="${PHP_BIN:-php}"
BACKUP=""; APPLY=0
while [ $# -gt 0 ]; do
    case "$1" in
        --backup)  BACKUP="$2"; shift 2;;
        --app-dir) APP_DIR="$2"; shift 2;;
        --apply)   APPLY=1; shift;;
        -h|--help) sed -n '2,16p' "$0"; exit 0;;
        *) echo "unknown option: $1" >&2; exit 2;;
    esac
done
die()  { printf '\nSTOPPED: %s\n' "$*" >&2; exit 1; }
say()  { printf '%s\n' "$*"; }
ok()   { printf '   ok    %s\n' "$*"; }
art()  { ( cd "$APP_DIR" && "$PHP_BIN" artisan "$@" ); }
env_val() { local v; v="$(grep -E "^$1=" "$APP_DIR/.env" 2>/dev/null | tail -n1 | cut -d= -f2-)"; v="${v%\"}"; v="${v#\"}"; v="${v%\'}"; v="${v#\'}"; printf '%s' "$v"; }
confirm() { local a; printf '\n%s\n   type exactly "%s" to continue: ' "$1" "$2"; IFS= read -r a || a=""; [ "$a" = "$2" ] || die "not confirmed - nothing was changed."; }

[ -n "$BACKUP" ] || die "--backup is required (a code-<stamp>.tar.gz from apply-release.sh)"
[ "$(id -u)" -ne 0 ] || die "do not run this as root"
[ -f "$BACKUP" ] || die "backup not found: $BACKUP"
command -v "$PHP_BIN" >/dev/null 2>&1 || die "PHP not found: $PHP_BIN"
[ -e "$APP_DIR/artisan" ] || die "$APP_DIR is not the application directory"

say "== Checking the backup"
[ -f "$BACKUP.sha256" ] && { ( cd "$(dirname "$BACKUP")" && sha256sum -c --quiet "$(basename "$BACKUP").sha256" ) || die "the backup does not match its .sha256 file"; ok "checksum verified"; } || say "   (no .sha256 next to it)"
gzip -t "$BACKUP" || die "the backup is not a valid gzip file"
LIST="$(mktemp)"; trap 'rm -f "$LIST"' EXIT
tar -tzf "$BACKUP" > "$LIST"
grep -qE '^(\./)?artisan$' "$LIST" && grep -qE '^(\./)?vendor/autoload\.php$' "$LIST" || die "the backup lacks artisan or vendor/ - it is not a full code backup"
if grep -qE '^(\./)?(\.env|storage/|public/storage)' "$LIST"; then die "the backup contains .env / storage / public/storage - it was not made by apply-release.sh; refusing"; fi
ok "$(wc -l < "$LIST") entries; contains artisan and vendor/; contains no .env, storage or uploads"
PREV="$(dirname "$BACKUP")/previous-RELEASE-$(basename "$BACKUP" | sed -E 's/^code-//; s/\.tar\.gz$//').txt"
[ -f "$PREV" ] && { say "   the release this backup restores:"; grep -E '^(release_id|built_utc)=' "$PREV" | sed 's/^/     /'; } || say "   (no record of which release this backup was; it is the code that was live before the deploy)"
[ -f "$APP_DIR/RELEASE.txt" ] && { say "   live now:"; grep -E '^(release_id|built_utc)=' "$APP_DIR/RELEASE.txt" | sed 's/^/     /'; }

say; say "== Difference between the backup and the live code (files that would be restored)"
DIFF_OUT="$(mktemp)"; trap 'rm -f "$LIST" "$DIFF_OUT"' EXIT
( cd "$APP_DIR" && tar -dzf "$BACKUP" ) 2>&1 | grep -E 'Contents differ|Size differs|No such file|Cannot (stat|open)' > "$DIFF_OUT" || true
say "   $(grep -c . "$DIFF_OUT" || true) files whose content differs from the backup or are missing live (permission / timestamp-only differences are ignored); first 25:"
head -n 25 "$DIFF_OUT" | sed 's/^/     /'

if [ "$APPLY" != 1 ]; then say; say "DRY RUN complete. Nothing was changed. Add --apply to roll the code back."; exit 0; fi

confirm "Roll the LIVE code back from $(basename "$BACKUP")? (code only: .env, storage, uploads and the database are not touched)" "ROLLBACK"
STAMP="$(date -u +%Y%m%d-%H%M%S)"
say; say "== Maintenance mode"
art down --retry=60 || say "   (artisan down failed - continuing; the site may be partly unavailable)"
say "== Restoring the files"
tar -xzf "$BACKUP" -C "$APP_DIR" --no-same-owner
ok "files restored from $BACKUP"
say "== Rebuilding caches from the restored code"
art config:clear; art route:clear; art view:clear; art event:clear || true
rm -f "$APP_DIR"/bootstrap/cache/packages.php "$APP_DIR"/bootstrap/cache/services.php
( cd "$APP_DIR" && "$PHP_BIN" artisan package:discover --ansi >/dev/null 2>&1 ) || say "   (package:discover reported a problem - check vendor/)"
art config:cache; art route:cache; art view:cache
art queue:restart || true
art up
ok "site is live again on the restored code"

say; say "== Verification"
APP_URL="$(env_val APP_URL)"
if command -v curl >/dev/null 2>&1 && [ -n "$APP_URL" ]; then
    CODE="$(curl -sS -m 30 -o /dev/null -w '%{http_code}' "$APP_URL/login" 2>/dev/null || echo 000)"
    [ "$CODE" = 200 ] && ok "GET $APP_URL/login -> 200" || say "   WARN  GET $APP_URL/login -> $CODE"
fi
art --version | sed 's/^/         /'
say
say "DONE. The code is back to the backup. REMEMBER: the database was not rolled back."
say "If the release ran migrations, read docs/DEPLOYMENT_CLOUDPANEL.md section 8 now. Do not run migrate:rollback."
