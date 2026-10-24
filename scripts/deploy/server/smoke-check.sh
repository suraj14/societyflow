#!/usr/bin/env bash
# smoke-check.sh - is the deployed site actually serving? READ-ONLY plain GET requests; no login, no POST, no data written.
#
#   bash smoke-check.sh --url https://societyflow.joboapps.com [--expect-sha <commit>]
#   exit 0 = every check passed, 1 = at least one failed.
#
# Checks: the home page and /login answer 200; /owners is protected (302 to /login, or 200 only if it is the login page); the API health
# endpoint identifies itself as societyflow; no answer is a 5xx; no body shows a framework error page, a stack trace or an SQL error
# (APP_DEBUG leaking); a login page that still lists demo credentials is flagged. It is run by the deploy script and by the pipeline.
set -uo pipefail

URL=""; EXPECT_SHA=""
while [ $# -gt 0 ]; do
    case "$1" in
        --url) URL="${2%/}"; shift 2;;
        --expect-sha) EXPECT_SHA="$2"; shift 2;;
        -h|--help) sed -n '2,10p' "$0"; exit 0;;
        *) echo "unknown option: $1" >&2; exit 2;;
    esac
done
[ -n "$URL" ] || { echo "smoke-check: --url is required" >&2; exit 2; }
command -v curl >/dev/null 2>&1 || { echo "smoke-check: curl not found" >&2; exit 2; }

fail=0; TMP="$(mktemp -d)"; trap 'rm -rf "$TMP"' EXIT
pass() { printf '  PASS  %s\n' "$*"; }
miss() { printf '  FAIL  %s\n' "$*"; fail=1; }
get()  { # get <name> <path>   -> sets CODE, LOC; body in $TMP/<name>
    local out; out="$(curl -sS -m 30 -o "$TMP/$1" -w '%{http_code} %{redirect_url}' "$URL$2" 2>"$TMP/err")" || { CODE=000; LOC=""; return; }
    CODE="${out%% *}"; LOC="${out#* }"
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

if [ -n "$EXPECT_SHA" ]; then
    echo "  info  expected release commit: ${EXPECT_SHA:0:12} (check it on the server with: cat RELEASE.txt)"
fi
[ "$fail" = 0 ] && echo "smoke check PASSED" || echo "smoke check FAILED"
exit "$fail"
