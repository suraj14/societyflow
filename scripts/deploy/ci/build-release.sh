#!/usr/bin/env bash
# build-release.sh - build the deployable package from COMMITTED files only. Used by the Bitbucket pipeline; also runnable locally.
#
#   bash scripts/deploy/ci/build-release.sh [OUT_DIR]          (default OUT_DIR: build/release, relative to the repository root)
#   ALLOW_DIRTY=1 bash ...        local tests only: allow uncommitted changes to tracked files (they are NOT packaged: only HEAD is)
#
# The package holds exactly what `git ls-files` says is committed, inside the same allow-list as scripts/deploy/New-ReleasePackage.ps1:
#   app, bootstrap/app.php, config, database/migrations, database/seeders (safe ones), database/factories, lang, public, resources, routes,
#   artisan, composer.json, composer.lock  (+ RELEASE.txt and RELEASE.sha256).
# Never in it: .env*, vendor/, storage/, bootstrap/cache, public/storage, databases, keys, logs, tests, docs, the Flutter app, and seeders that
# create accounts with hard-coded passwords or load demo data. The build FAILS (exit 1, no package) if a check below fails.
#
# Output: <OUT_DIR>/societyflow-<release-id>.tar.gz (+ .sha256), INCLUDED.txt, server-tools/ (the scripts that run on the server).
# Release id: <UTC time>-<short commit>. The full commit SHA is written into RELEASE.txt, so "which code is live" is always answerable.
set -euo pipefail

cd "$(git rev-parse --show-toplevel)"
OUT_DIR="${1:-build/release}"
fail() { echo "build-release: FAILED - $*" >&2; exit 1; }

# ---- the source must be one exact commit
if [ -n "$(git status --porcelain --untracked-files=no)" ] && [ "${ALLOW_DIRTY:-0}" != 1 ]; then
    fail "tracked files have uncommitted changes; a deployment package is built from a commit (set ALLOW_DIRTY=1 only for a local test)"
fi
SHA="$(git rev-parse HEAD)"; SHORT="$(git rev-parse --short=10 HEAD)"
BRANCH="${BITBUCKET_BRANCH:-$(git rev-parse --abbrev-ref HEAD)}"
COMMIT_TS="$(git show -s --format=%ct HEAD)"
RELEASE_ID="$(date -u +%Y%m%d-%H%M%S)-$SHORT"
WORK="$(mktemp -d)"; trap 'rm -rf "$WORK"' EXIT
STAGE="$WORK/stage"; mkdir -p "$STAGE" "$OUT_DIR"

# ---- select files: committed + inside the allow-list + not excluded
git ls-files -z -- app bootstrap/app.php config database/migrations database/seeders database/factories lang public resources routes artisan composer.json composer.lock \
    | tr '\0' '\n' | LC_ALL=C sort > "$WORK/candidates.txt"
: > "$WORK/files.txt"; : > "$WORK/excluded.txt"
EXCL_NAME='(^|/)(\.env(\..*)?|thumbs\.db|\.ds_store|web\.config|hot|id_(rsa|dsa|ecdsa|ed25519)(\.pub)?)$'
EXCL_EXT='\.(sqlite|sqlite3|sqlite-journal|db|sql|gz|tgz|zip|tar|rar|7z|bak|log|tmp|temp|pem|key|crt|csr|p12|pfx|jks|keystore|ppk|apk|aab|orig|rej|swp)$'
DEMO_SEEDER='(test|demo|sample|dummy|quick|realistic|comprehensive)'
while IFS= read -r f; do
    [ -n "$f" ] || continue
    if printf '%s' "$f" | grep -qiE "$EXCL_NAME" || printf '%s' "$f" | grep -qiE "$EXCL_EXT"; then echo "$f  (secret / database / archive / log / key pattern)" >> "$WORK/excluded.txt"; continue; fi
    case "$f" in public/storage/*|storage/*|vendor/*) echo "$f  (runtime / vendor path)" >> "$WORK/excluded.txt"; continue;; esac
    case "$f" in
        database/seeders/*.php)
            if grep -vE '^\s*(//|\*|/\*|#)' "$f" | grep -qE "(Hash::make|bcrypt)\(\s*['\"]"; then echo "$f  (seeder with a HARD-CODED password)" >> "$WORK/excluded.txt"; continue; fi
            if printf '%s' "$(basename "$f" .php)" | grep -qiE "$DEMO_SEEDER"; then echo "$f  (demo / sample / test-data seeder)" >> "$WORK/excluded.txt"; continue; fi;;
    esac
    echo "$f" >> "$WORK/files.txt"
done < "$WORK/candidates.txt"

# ---- stage the COMMITTED content (git show, not the working tree), keep the executable bit out: the server sets modes by umask
while IFS= read -r f; do mkdir -p "$STAGE/$(dirname "$f")"; git show "HEAD:$f" > "$STAGE/$f"; done < "$WORK/files.txt"
N_FILES="$(wc -l < "$WORK/files.txt" | tr -d ' ')"
{
    echo "release_id=$RELEASE_ID"
    echo "built_utc=$(date -u '+%Y-%m-%d %H:%M:%S')"
    echo "source_branch=$BRANCH"
    echo "source_head=$SHA"
    echo "uncommitted_paths_at_build=0"
    echo "composer_lock_sha256=$(sha256sum composer.lock | cut -d' ' -f1)"
    echo "built_with_php=$(php -r 'echo PHP_VERSION;' 2>/dev/null || echo unknown)"
    echo "files=$N_FILES"
    echo "built_by=${BITBUCKET_BUILD_NUMBER:+bitbucket-pipelines #$BITBUCKET_BUILD_NUMBER}${BITBUCKET_BUILD_NUMBER:-local}"
    echo "note=built from commit $SHORT only; storage/, .env, vendor/ and databases are NOT part of this package"
} > "$STAGE/RELEASE.txt"
( cd "$STAGE" && find . -type f ! -name RELEASE.sha256 | sed 's|^\./||' | LC_ALL=C sort | while IFS= read -r p; do sha256sum -- "$p"; done > RELEASE.sha256 )

# ---- validate the staged tree
for r in artisan composer.json composer.lock bootstrap/app.php public/index.php routes/web.php routes/api.php app/Http/Kernel.php config/app.php config/database.php resources/views/layouts/app.blade.php RELEASE.txt RELEASE.sha256; do
    [ -f "$STAGE/$r" ] || fail "required file missing from the package: $r"
done
ls "$STAGE"/database/migrations/*.php >/dev/null 2>&1 || fail "no migrations in the package"
FORBID="$(cd "$STAGE" && find . -type f \( -iname '.env' -o -iname '.env.*' -o -iname '*.sqlite' -o -iname '*.sql' -o -iname '*.pem' -o -iname '*.key' -o -iname '*.log' -o -iname '*.jks' -o -iname '*.keystore' \) -print; find . -maxdepth 1 \( -name storage -o -name vendor -o -name tests -o -name docs -o -name societyflowmob \) -print)"
[ -z "$FORBID" ] || fail "forbidden content staged: $(printf '%s' "$FORBID" | head -3 | tr '\n' ' ')"
if [ -d "$STAGE/database/seeders" ] && grep -rlE "(Hash::make|bcrypt)\(\s*['\"]" "$STAGE/database/seeders" 2>/dev/null | while read -r s; do grep -vE '^\s*(//|\*|/\*|#)' "$s" | grep -qE "(Hash::make|bcrypt)\(\s*['\"]" && echo "$s"; done | grep -q .; then
    fail "a shipped seeder hashes a literal password"
fi
HITS="$(cd "$STAGE" && grep -rInP -e '-----BEGIN [A-Z ]*PRIVATE KEY-----' -e '\bAKIA(?!IOSFODNN7EXAMPLE)[0-9A-Z]{16}\b' -e '\bAIza[0-9A-Za-z_-]{35}\b' -e 'APP_KEY=base64:[A-Za-z0-9+/=]{20,}' -e '(?m)^\s*DB_PASSWORD=\S+' -e '\b(ghp_[A-Za-z0-9]{36}|xox[baprs]-[A-Za-z0-9-]{10,})\b' . 2>/dev/null | sed -E 's/^([^:]+:[0-9]+):.*/\1 [value masked]/' | head -5 || true)"
[ -z "$HITS" ] || fail "secret-looking content in the package: $HITS"
if command -v php >/dev/null 2>&1; then
    ( cd "$STAGE" && find . -name '*.php' ! -name '*.blade.php' -print0 | xargs -0 -n1 php -l >/dev/null ) 2>"$WORK/lint.err" || fail "PHP syntax error in the package: $(head -2 "$WORK/lint.err" | tr '\n' ' ')"
fi
( cd "$STAGE" && sha256sum -c --quiet RELEASE.sha256 ) || fail "RELEASE.sha256 does not match the staged files"

# ---- archive (reproducible for one commit: sorted, fixed owner, commit time) and verify it
ARCHIVE="$OUT_DIR/societyflow-$RELEASE_ID.tar.gz"
( cd "$STAGE" && find . -type f | sed 's|^\./||' | LC_ALL=C sort > "$WORK/tarlist.txt" )
tar --owner=0 --group=0 --numeric-owner --mtime="@$COMMIT_TS" -czf "$ARCHIVE" -C "$STAGE" -T "$WORK/tarlist.txt"
[ "$(tar -tzf "$ARCHIVE" | wc -l | tr -d ' ')" = "$(wc -l < "$WORK/tarlist.txt" | tr -d ' ')" ] || fail "the archive does not list exactly the staged files"
mkdir -p "$WORK/verify" && tar -xzf "$ARCHIVE" -C "$WORK/verify" && ( cd "$WORK/verify" && sha256sum -c --quiet RELEASE.sha256 ) || fail "the extracted archive does not match RELEASE.sha256"
( cd "$OUT_DIR" && sha256sum "$(basename "$ARCHIVE")" > "$(basename "$ARCHIVE").sha256" )
{ echo "SocietyFlow release $RELEASE_ID from commit $SHA - files in the package ($(wc -l < "$WORK/tarlist.txt" | tr -d ' '))"; echo; cat "$WORK/tarlist.txt"; echo; echo "--- left out of the allow-list folders:"; cat "$WORK/excluded.txt"; } > "$OUT_DIR/INCLUDED.txt"

# ---- the scripts that run on the server (LF endings), with a checksum list
mkdir -p "$OUT_DIR/server-tools"
for t in scripts/deploy/server/*.sh; do tr -d '\r' < "$t" > "$OUT_DIR/server-tools/$(basename "$t")"; done
( cd "$OUT_DIR/server-tools" && sha256sum *.sh > SHA256SUMS )
for t in "$OUT_DIR"/server-tools/*.sh; do bash -n "$t" || fail "server script fails bash -n: $(basename "$t")"; done

echo "release_id=$RELEASE_ID"
echo "commit=$SHA"
echo "archive=$ARCHIVE"
echo "sha256=$(cut -d' ' -f1 "$ARCHIVE.sha256")"
echo "files=$(wc -l < "$WORK/tarlist.txt" | tr -d ' ')   excluded_in_allowlist_folders=$(wc -l < "$WORK/excluded.txt" | tr -d ' ')"
