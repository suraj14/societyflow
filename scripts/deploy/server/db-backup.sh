#!/usr/bin/env bash
# db-backup.sh - make and VERIFY a database backup of the SocietyFlow install. Run it as the site user.
#
#   bash db-backup.sh [--app-dir DIR] [--backup-dir DIR] [--label TEXT]
#   PHP_BIN=/usr/bin/php8.2 bash db-backup.sh
#   defaults: --app-dir /home/joboapps-societyflow/htdocs/societyflow.joboapps.com
#             --backup-dir /home/joboapps-societyflow/backups/societyflow
#
# What it does
#   * reads the database connection from the application's own configuration (nothing is typed, nothing is echoed);
#   * writes the credentials to a private temporary option file (mode 600, deleted on exit) so they never appear in `ps` or in this output;
#   * dumps with a consistent snapshot (--single-transaction; the site can stay up) and compresses;
#   * VERIFIES the file: gzip integrity, the dump's completion marker, a plausible size, and that it contains at least as many tables as
#     the database has;
#   * writes <backup>.sha256 and <backup>.meta next to it, and prints   BACKUP_FILE=<path>   as its last line on success.
# It writes ONLY inside the backup directory (and a private temp directory). It deletes nothing that already exists and never touches the
# database except to read it. MySQL / MariaDB and SQLite sites are supported.
set -euo pipefail
umask 077

APP_DIR="${APP_DIR:-/home/joboapps-societyflow/htdocs/societyflow.joboapps.com}"
BACKUP_DIR="${BACKUP_DIR:-/home/joboapps-societyflow/backups/societyflow}"
PHP_BIN="${PHP_BIN:-php}"
LABEL=""
while [ $# -gt 0 ]; do
    case "$1" in
        --app-dir)    APP_DIR="$2"; shift 2;;
        --backup-dir) BACKUP_DIR="$2"; shift 2;;
        --label)      LABEL="-$(printf '%s' "$2" | tr -c 'A-Za-z0-9._-' '_')"; shift 2;;
        -h|--help)    sed -n '2,20p' "$0"; exit 0;;
        *) echo "unknown option: $1" >&2; exit 2;;
    esac
done

die() { echo "db-backup: $*" >&2; exit 1; }
for t in "$PHP_BIN" gzip sha256sum df stat; do command -v "$t" >/dev/null 2>&1 || die "required tool not found: $t"; done
[ -r "$APP_DIR/vendor/autoload.php" ] && [ -r "$APP_DIR/.env" ] || die "$APP_DIR does not look like the application (vendor/autoload.php or .env missing)"

mkdir -p "$BACKUP_DIR"; chmod 700 "$BACKUP_DIR" 2>/dev/null || true
WORK="$(mktemp -d "${TMPDIR:-/tmp}/sfdb.XXXXXXXX")"
trap 'rm -rf "$WORK"' EXIT
CNF="$WORK/client.cnf"

# 1. Ask the application which database it uses. Credentials go straight into $CNF; stdout gets only: driver|name|bytes|tables|sqlite_path
PROBE="$( cd "$APP_DIR" && "$PHP_BIN" -r '
    require "vendor/autoload.php";
    $app = require "bootstrap/app.php";
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    // Installed AFTER bootstrap(), which sets Laravel'"'"'s own handler: that one prints the full error, and a connection error carries the host,
    // the user name and (in the stack trace) the start of the password. Say WHAT failed - the exception class - never why.
    set_exception_handler(function (Throwable $e) { fwrite(STDERR, "db-backup: could not connect or read the configuration (" . get_class($e) . ")\n"); exit(4); });
    $name = config("database.default");
    $c = config("database.connections.$name");
    $driver = $c["driver"] ?? "";
    if (!in_array($driver, ["mysql", "mariadb", "sqlite"], true)) { fwrite(STDERR, "unsupported database driver\n"); exit(3); }
    $db = Illuminate\Support\Facades\DB::connection();
    if ($driver === "sqlite") {
        $tables = $db->selectOne("select count(*) as n from sqlite_master where type=\"table\" and name not like \"sqlite_%\"")->n;
        echo "sqlite|", basename((string) $c["database"]), "|", (int) @filesize($c["database"]), "|$tables|", $c["database"];
        exit(0);
    }
    $q = fn ($v) => "\"" . addcslashes((string) $v, "\"\\") . "\"";
    $lines = ["[client]", "user=" . $q($c["username"] ?? ""), "password=" . $q($c["password"] ?? "")];
    if (!empty($c["unix_socket"])) { $lines[] = "socket=" . $q($c["unix_socket"]); }
    else { $lines[] = "host=" . $q($c["host"] ?? "127.0.0.1"); $lines[] = "port=" . (int) ($c["port"] ?? 3306); }
    $f = $argv[1];
    touch($f); chmod($f, 0600);
    file_put_contents($f, implode("\n", $lines) . "\n");
    $row = $db->selectOne("select coalesce(sum(data_length + index_length), 0) as b, count(*) as n from information_schema.tables where table_schema = database() and table_type = \"BASE TABLE\"");
    echo $driver, "|", $db->getDatabaseName(), "|", (int) $row->b, "|", (int) $row->n, "|";
' "$CNF" )" || die "could not read the database configuration or connect to the database (details withheld: they can name the host and user). Check DB_* in the site's .env and run  cd $APP_DIR && $PHP_BIN artisan migrate:status  to see the connection error yourself."

IFS='|' read -r DRIVER DBNAME DBBYTES TABLES SQLITE_PATH <<<"$PROBE"
[ -n "$DRIVER" ] || die "empty answer from the application"
STAMP="$(date -u +%Y%m%d-%H%M%S)"
SAFE_NAME="$(printf '%s' "$DBNAME" | tr -c 'A-Za-z0-9._-' '_')"
echo "database: driver=$DRIVER name=$DBNAME tables=$TABLES size~$(( DBBYTES / 1024 / 1024 )) MiB"

# 2. Space: a compressed dump is usually smaller than the database; require 2x + 200 MiB free to be safe.
AVAIL_KB="$(df -Pk "$BACKUP_DIR" | awk 'NR==2 {print $4}')"
NEED_KB=$(( (DBBYTES / 1024) * 2 + 200 * 1024 ))
[ "$AVAIL_KB" -ge "$NEED_KB" ] || die "not enough free space in $BACKUP_DIR: ${AVAIL_KB} KiB free, ${NEED_KB} KiB needed"

if [ "$DRIVER" = "sqlite" ]; then
    OUT="$BACKUP_DIR/db-$SAFE_NAME$LABEL-$STAMP.sqlite"
    [ ! -e "$OUT" ] || die "$OUT already exists"
    "$PHP_BIN" -r '$d = new PDO("sqlite:" . $argv[1]); $d->exec("VACUUM INTO " . $d->quote($argv[2]));' "$SQLITE_PATH" "$OUT.partial"
    CHECK="$("$PHP_BIN" -r '$d = new PDO("sqlite:" . $argv[1]); echo $d->query("pragma integrity_check")->fetchColumn(), "|", $d->query("select count(*) from sqlite_master where type=\"table\" and name not like \"sqlite_%\"")->fetchColumn();' "$OUT.partial")"
    [ "${CHECK%%|*}" = "ok" ] || die "SQLite integrity_check on the copy failed: $CHECK"
    [ "${CHECK##*|}" -ge "$TABLES" ] || die "the copy has fewer tables (${CHECK##*|}) than the database ($TABLES)"
    mv "$OUT.partial" "$OUT"
else
    command -v mysqldump >/dev/null 2>&1 || die "mysqldump not found (install the MySQL/MariaDB client tools, or run the dump another way and keep it)"
    OUT="$BACKUP_DIR/db-$SAFE_NAME$LABEL-$STAMP.sql.gz"
    [ ! -e "$OUT" ] || die "$OUT already exists"
    # No routines / events: the application creates none. INCLUDE_ROUTINES=1 adds them (needs the matching privileges).
    EXTRA=""
    [ "${INCLUDE_ROUTINES:-0}" = "1" ] && EXTRA="--routines --events"
    # shellcheck disable=SC2086
    mysqldump --defaults-extra-file="$CNF" --single-transaction --quick --triggers --no-tablespaces --default-character-set=utf8mb4 $EXTRA ${DUMP_EXTRA_ARGS:-} "$DBNAME" | gzip -c > "$OUT.partial" \
        || die "mysqldump failed (the partial file is left at $OUT.partial for inspection - it is NOT a usable backup)"
    # 3. Verify, do not trust.
    gzip -t "$OUT.partial" || die "gzip integrity check failed"
    zcat "$OUT.partial" | tail -n 5 | grep -q 'Dump completed' || die "the dump has no completion marker - it was cut short"
    SIZE="$(stat -c %s "$OUT.partial")"
    [ "$SIZE" -gt 2048 ] || die "the dump is only $SIZE bytes - suspiciously small"
    CREATES="$(zcat "$OUT.partial" | grep -c '^CREATE TABLE ' || true)"
    [ "$CREATES" -ge "$TABLES" ] || die "the dump contains $CREATES CREATE TABLE statements but the database has $TABLES tables"
    mv "$OUT.partial" "$OUT"
    echo "verified: gzip ok, completion marker present, $CREATES CREATE TABLE statements for $TABLES tables"
fi

( cd "$BACKUP_DIR" && sha256sum "$(basename "$OUT")" > "$(basename "$OUT").sha256" )
{
    echo "created_utc=$STAMP"
    echo "driver=$DRIVER"
    echo "database=$DBNAME"
    echo "tables=$TABLES"
    echo "bytes=$(stat -c %s "$OUT")"
    echo "app_dir=$APP_DIR"
    [ -r "$APP_DIR/RELEASE.txt" ] && grep -E '^release_id=' "$APP_DIR/RELEASE.txt" | sed 's/^/app_/'
} > "$OUT.meta"
echo "size: $(stat -c %s "$OUT") bytes   sha256: $(cut -d' ' -f1 "$OUT.sha256")"
echo "To prove it restores: create a scratch database and load it there (see docs/DEPLOYMENT_CLOUDPANEL.md section 8). A backup nobody has restored is a hope, not a backup."
echo "BACKUP_FILE=$OUT"
