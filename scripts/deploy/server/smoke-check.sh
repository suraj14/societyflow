#!/usr/bin/env bash
# smoke-check.sh - is the deployed site actually serving? READ-ONLY plain GET requests; no login, no POST, no data written.
#
#   bash smoke-check.sh --url https://societyflow.joboapps.com [--expect-release <release id> [--release-wait SECONDS] [--release-interval SECONDS]]
#   exit 0 = every check passed, 1 = at least one failed, 2 = wrong usage.
#
# Checks: the home page and /login answer 200; /owners is protected (302 to /login, or 200 only if it is the login page); the API health
# endpoint identifies itself as societyflow; no answer is a 5xx; no body shows a framework error page, a stack trace or an SQL error
# (APP_DEBUG leaking); a login page that still lists demo credentials is flagged. It is run by the deploy script and by the pipeline.
#
# --expect-release proves WHICH code is being served: the health answer carries the header X-SocietyFlow-Release, the first 16 hex characters
# of sha256(release id) (App\Support\ReleaseFingerprint reads the id from the RELEASE.txt the packaged deploy puts in the application root).
# It must equal the fingerprint of the release that was just deployed, otherwise the site is still serving the previous release, a cached
# copy, or another server. PHP can keep running the old compiled code for a few seconds after the files change, so the header is polled for
# --release-wait seconds (default 60, every --release-interval seconds, default 5) before a mismatch counts as a failure.
set -uo pipefail

URL=""; EXPECT_RELEASE=""; RELEASE_WAIT=60; RELEASE_INTERVAL=5
needs_value() { [ "$1" -ge 2 ] || { echo "smoke-check: $2 needs a value" >&2; exit 2; }; }
while [ $# -gt 0 ]; do
    case "$1" in
        --url) needs_value $# "$1"; URL="${2%/}"; shift 2;;
        --expect-release) needs_value $# "$1"; EXPECT_RELEASE="$2"; shift 2;;
        --release-wait) needs_value $# "$1"; RELEASE_WAIT="$2"; shift 2;;
        --release-interval) needs_value $# "$1"; RELEASE_INTERVAL="$2"; shift 2;;
        -h|--help) sed -n '2,16p' "$0"; exit 0;;
        *) echo "unknown option: $1" >&2; exit 2;;
    esac
done
[ -n "$URL" ] || { echo "smoke-check: --url is required" >&2; exit 2; }
command -v curl >/dev/null 2>&1 || { echo "smoke-check: curl not found" >&2; exit 2; }
if [ -n "$EXPECT_RELEASE" ]; then
    printf '%s' "$EXPECT_RELEASE" | grep -qE '^[A-Za-z0-9._-]{3,80}$' || { echo "smoke-check: --expect-release is not a plain release id" >&2; exit 2; }
    printf '%s' "$RELEASE_WAIT" | grep -qE '^[0-9]{1,4}$' || { echo "smoke-check: --release-wait must be a number of seconds" >&2; exit 2; }
    printf '%s' "$RELEASE_INTERVAL" | grep -qE '^[1-9][0-9]{0,2}$' || { echo "smoke-check: --release-interval must be a whole number of seconds (1 or more)" >&2; exit 2; }
    command -v sha256sum >/dev/null 2>&1 || { echo "smoke-check: sha256sum not found" >&2; exit 2; }
fi

fail=0; TMP="$(mktemp -d)"; trap 'rm -rf "$TMP"' EXIT
pass() { printf '  PASS  %s\n' "$*"; }
miss() { printf '  FAIL  %s\n' "$*"; fail=1; }
get()  { # get <name> <path>   -> sets CODE, LOC; body in $TMP/<name>
    local out; out="$(curl -sS -m 30 -o "$TMP/$1" -w '%{http_code} %{redirect_url}' "$URL$2" 2>"$TMP/err")" || { CODE=000; LOC=""; return; }
    CODE="${out%% *}"; LOC="${out#* }"
}
release_tag() { # the X-SocietyFlow-Release header of the health answer, or nothing. A fresh URL and no-cache so no proxy answers from its memory.
    : > "$TMP/hdr"
    curl -sS -m 20 -D "$TMP/hdr" -o /dev/null -H 'Cache-Control: no-cache' "$URL/api/v1/health?nocache=$RANDOM$RANDOM" 2>/dev/null || true
    tr -d '\r' < "$TMP/hdr" | awk -F': *' 'tolower($1) == "x-societyflow-release" { print $2; exit }'
}

echo "smoke check: $URL"
get home /;       { [ "$CODE" = 200 ] || [ "$CODE" = 302 ]; } && pass "GET /  -> $CODE" || miss "GET /  -> $CODE (expected 200/302)"
get login /login; [ "$CODE" = 200 ] && grep -qi '<form' "$TMP/login" && pass "GET /login -> 200 and shows a form" || miss "GET /login -> $CODE (expected 200 with a form)"
get owners /owners
if [ "$CODE" = 302 ] && printf '%s' "$LOC" | grep -q '/login'; then pass "GET /owners -> 302 to /login (the page is protected)"
elif [ "$CODE" = 200 ] && grep -qi 'name="password"' "$TMP/owners"; then pass "GET /owners -> the login form (protected)"
else miss "GET /owners -> $CODE $LOC (expected a redirect to /login without a session)"; fi
get health /api/v1/health
if [ "$CODE" = 200 ] && grep -q '"application":"societyflow"' "$TMP/health"; then pass "GET /api/v1/health -> societyflow answers"; else miss "GET /api/v1/health -> $CODE (expected the SocietyFlow health JSON)"; fi

for n in home login owners health; do
    if grep -qiE 'Whoops!|Stack trace|SQLSTATE\[|Illuminate\\\\|vendor/laravel' "$TMP/$n" 2>/dev/null; then miss "the /$n answer shows an error page / stack trace (APP_DEBUG or an unhandled error)"; fi
done
if [ ! -s "$TMP/login" ]; then miss "cannot check the login page for demo credentials: it was not served"
elif grep -qiE "fillCredentials\(|Demo Credentials|copyCredentials\(" "$TMP/login"; then miss "the login page still lists demo credentials (known weak accounts must not be advertised)"
else pass "the login page does not advertise demo credentials"; fi

if [ -n "$EXPECT_RELEASE" ]; then
    want="$(printf '%s' "$EXPECT_RELEASE" | sha256sum | cut -c1-16)"
    waited=0
    while :; do
        got="$(release_tag)"
        [ "$got" = "$want" ] && break
        [ "$waited" -ge "$RELEASE_WAIT" ] && break
        sleep "$RELEASE_INTERVAL"; waited=$((waited + RELEASE_INTERVAL))
    done
    if [ "$got" = "$want" ]; then pass "the live site reports release $EXPECT_RELEASE (fingerprint $want)"
    elif [ -z "$got" ]; then miss "the live site reports NO release fingerprint after ${waited}s, so it is not serving release $EXPECT_RELEASE (the previous code is still running, PHP has not reloaded, or a proxy is rewriting the health answer)"
    else miss "the live site reports fingerprint $got, expected $want for release $EXPECT_RELEASE: another release is being served (PHP may still be running the old compiled code, or this is not the server that was deployed)"; fi
fi
[ "$fail" = 0 ] && echo "smoke check PASSED" || echo "smoke check FAILED"
exit "$fail"
