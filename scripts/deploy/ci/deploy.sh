#!/usr/bin/env bash
# deploy.sh - runs INSIDE the Bitbucket pipeline: uploads a package built by build-release.sh to the CloudPanel server and runs the
# server-side apply script there. It never contains, prints or stores a credential: the SSH key comes from Bitbucket's SSH-key store
# (Repository settings -> Pipelines -> SSH keys) and the server's identity from its Known hosts list; every setting below is a Bitbucket variable.
#
#   bash scripts/deploy/ci/deploy.sh <build-dir> <dry-run|apply>
#
# Required repository / deployment variables (names only; the values are yours to set - docs/BITBUCKET_PIPELINES_SETUP.md):
#   PROD_SSH_HOST   PROD_SSH_USER   PROD_APP_DIR   PROD_URL        optional: PROD_SSH_PORT (22), PROD_PHP_BIN (php), PROD_COMPOSER_BIN (composer)
#
# Exit codes: 0 deployed (or dry run fine) and the outside smoke check passed; 3 the release has pending migrations (a person must run the
# interactive deploy); anything else is a failure. In every failure the previous code stays live or is restored by the server script.
set -euo pipefail

BUILD_DIR="${1:?build directory (output of build-release.sh)}"
MODE="${2:?dry-run or apply}"
case "$MODE" in dry-run|apply) ;; *) echo "deploy: mode must be dry-run or apply" >&2; exit 2;; esac

need() { [ -n "${!1:-}" ] || { echo "deploy: the Bitbucket variable $1 is not set" >&2; exit 2; }; }
for v in PROD_SSH_HOST PROD_SSH_USER PROD_APP_DIR PROD_URL; do need "$v"; done
PORT="${PROD_SSH_PORT:-22}"; PHP_BIN_R="${PROD_PHP_BIN:-php}"; COMPOSER_BIN_R="${PROD_COMPOSER_BIN:-composer}"
# Values go into a remote command line: accept only plain characters.
chk() { printf '%s' "$2" | grep -qE "$3" || { echo "deploy: $1 has characters that are not allowed" >&2; exit 2; }; }
chk PROD_SSH_HOST "$PROD_SSH_HOST" '^[A-Za-z0-9._-]+$'
chk PROD_SSH_USER "$PROD_SSH_USER" '^[a-z_][a-z0-9_-]*$'
chk PROD_SSH_PORT "$PORT" '^[0-9]{1,5}$'
chk PROD_APP_DIR "$PROD_APP_DIR" '^/[A-Za-z0-9._/-]+$'
chk PROD_PHP_BIN "$PHP_BIN_R" '^[A-Za-z0-9._/-]+$'
chk PROD_COMPOSER_BIN "$COMPOSER_BIN_R" '^[A-Za-z0-9._/-]+$'
chk PROD_URL "$PROD_URL" '^https://[A-Za-z0-9._-]+(/[A-Za-z0-9._/-]*)?$'

ARCHIVE="$(ls "$BUILD_DIR"/societyflow-*.tar.gz | head -n1)"; [ -f "$ARCHIVE" ] || { echo "deploy: no package in $BUILD_DIR" >&2; exit 2; }
RELEASE_ID="$(tar -xzOf "$ARCHIVE" RELEASE.txt | grep '^release_id=' | cut -d= -f2)"
COMMIT="$(tar -xzOf "$ARCHIVE" RELEASE.txt | grep '^source_head=' | cut -d= -f2)"
echo "deploying release $RELEASE_ID (commit ${COMMIT:0:12}) to $PROD_SSH_HOST as $PROD_SSH_USER - mode: $MODE"

# Strict host-key checking is NEVER switched off: the server must already be in Bitbucket's Known hosts, or the connection is refused.
SSH_OPTS=(-p "$PORT" -o BatchMode=yes -o StrictHostKeyChecking=yes -o ConnectTimeout=20 -o ServerAliveInterval=15)
SCP_OPTS=(-P "$PORT" -o BatchMode=yes -o StrictHostKeyChecking=yes -o ConnectTimeout=20)
REMOTE="$PROD_SSH_USER@$PROD_SSH_HOST"
rsh() { ssh "${SSH_OPTS[@]}" "$REMOTE" "$@"; }

rsh 'echo "ssh ok as $(id -un)"; test "$(id -u)" -ne 0' || { echo "deploy: SSH login failed, or the account is root (never deploy as root)" >&2; exit 1; }
rsh "test -f '$PROD_APP_DIR/artisan' && test -f '$PROD_APP_DIR/.env'" || { echo "deploy: $PROD_APP_DIR is not the live application directory" >&2; exit 1; }

# 1) the server tools travel with the release (they are committed code too): verify, then install
TOOLS_TMP="deploy-tools-incoming-$RELEASE_ID"
rsh "rm -rf ~/'$TOOLS_TMP' && mkdir -p ~/'$TOOLS_TMP' ~/incoming ~/deploy-tools"
scp "${SCP_OPTS[@]}" "$BUILD_DIR"/server-tools/* "$REMOTE:$TOOLS_TMP/"
rsh "cd ~/'$TOOLS_TMP' && sha256sum -c --quiet SHA256SUMS && cp -f *.sh SHA256SUMS ~/deploy-tools/ && chmod 700 ~/deploy-tools/*.sh && rm -rf ~/'$TOOLS_TMP'" || { echo "deploy: the server tools did not verify" >&2; exit 1; }

# 2) the package: upload, then let the server verify its checksum before anything else
scp "${SCP_OPTS[@]}" "$ARCHIVE" "$ARCHIVE.sha256" "$REMOTE:incoming/"
PKG="incoming/$(basename "$ARCHIVE")"

# 3) always a dry run first (changes nothing on the live site), then the real thing
REMOTE_ENV="PHP_BIN='$PHP_BIN_R' COMPOSER_BIN='$COMPOSER_BIN_R'"
echo "---- server-side dry run"
rsh "cd ~ && $REMOTE_ENV bash ~/deploy-tools/apply-release.sh --package ~/'$PKG' --app-dir '$PROD_APP_DIR' --domain '${PROD_URL#https://}'" || { echo "deploy: the server-side dry run failed" >&2; exit 1; }
[ "$MODE" = apply ] || { echo "dry run finished: nothing on the live site was changed"; exit 0; }

echo "---- server-side apply (non-interactive: stops, changing nothing, if a migration is pending)"
set +e
rsh "cd ~ && $REMOTE_ENV bash ~/deploy-tools/apply-release.sh --package ~/'$PKG' --app-dir '$PROD_APP_DIR' --domain '${PROD_URL#https://}' --apply --non-interactive --confirm-release '$RELEASE_ID'"
CODE=$?
set -e
if [ "$CODE" = 3 ]; then echo "deploy: NOT deployed - this release has pending database migrations; a person must run the interactive deploy (docs/BITBUCKET_PIPELINES_SETUP.md section 6)" >&2; exit 3; fi
[ "$CODE" = 0 ] || { echo "deploy: the server-side apply failed (exit $CODE); see its output above. The previous code was kept or restored." >&2; exit 1; }

# 4) a check from OUTSIDE the server, independent of the server's own report
echo "---- smoke check from the pipeline"
bash scripts/deploy/server/smoke-check.sh --url "$PROD_URL" --expect-sha "$COMMIT" || { echo "deploy: the outside smoke check FAILED - roll back with rollback-code.sh (see the setup guide)" >&2; exit 1; }
echo "deployed release $RELEASE_ID (commit ${COMMIT:0:12}) and verified it from outside"
