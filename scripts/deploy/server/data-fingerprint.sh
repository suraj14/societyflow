#!/usr/bin/env bash
# data-fingerprint.sh - READ-ONLY record counts of the business tables, to prove a deployment kept the data.
#
#   bash data-fingerprint.sh [--app-dir DIR] > before.txt          # take a fingerprint
#   bash data-fingerprint.sh --compare before.txt after.txt         # exit 0 = nothing lost, 1 = something shrank / vanished
#
# For each table that exists it prints   table <TAB> rows <TAB> max(id)  (and the sum of `amount` / `total_amount` for money tables).
# It only runs SELECT count / max / sum through the application's own database connection; no secret is printed.
# A deployment may ADD rows (new owners, payments made meanwhile) but must never remove any: --compare fails when a count or a
# max(id) is lower afterwards, or when a table disappeared.
set -euo pipefail

APP_DIR="${APP_DIR:-/home/joboapps-societyflow/htdocs/societyflow.joboapps.com}"
PHP_BIN="${PHP_BIN:-php}"

if [ "${1:-}" = "--compare" ]; then
    before="${2:?before file}"; after="${3:?after file}"
    [ -r "$before" ] && [ -r "$after" ] || { echo "compare: cannot read the fingerprint files" >&2; exit 2; }
    bad=0
    while IFS=$'\t' read -r table rows maxid sum; do
        case "$table" in ''|\#*) continue;; esac
        line="$(awk -F'\t' -v t="$table" '$1==t {print; exit}' "$after")"
        if [ -z "$line" ]; then echo "LOST   $table: table is missing after the deployment (was $rows rows)"; bad=1; continue; fi
        IFS=$'\t' read -r _ rows2 maxid2 sum2 <<<"$line"
        if [ "$rows2" -lt "$rows" ]; then echo "LOST   $table: rows $rows -> $rows2"; bad=1; fi
        if [ "${maxid:-0}" != "-" ] && [ "${maxid2:-0}" != "-" ] && [ "${maxid2:-0}" -lt "${maxid:-0}" ]; then echo "LOST   $table: highest id $maxid -> $maxid2"; bad=1; fi
        if [ "${sum:--}" != "-" ] && [ "${sum2:--}" != "-" ] && awk -v a="$sum" -v b="$sum2" 'BEGIN{exit !(b+0 < a+0 - 0.005)}'; then echo "LOST   $table: money total $sum -> $sum2"; bad=1; fi
    done < "$before"
    [ "$bad" = 0 ] && echo "data check: every table kept at least its earlier row count, highest id and money total ($(grep -vc '^#' "$before") tables compared)"
    exit "$bad"
fi

while [ $# -gt 0 ]; do
    case "$1" in
        --app-dir) APP_DIR="$2"; shift 2;;
        -h|--help) sed -n '2,12p' "$0"; exit 0;;
        *) echo "unknown option: $1" >&2; exit 2;;
    esac
done

cd "$APP_DIR"
"$PHP_BIN" -r '
    require "vendor/autoload.php";
    $app = require "bootstrap/app.php";
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    set_exception_handler(function (Throwable $e) { fwrite(STDERR, "data-fingerprint: could not read the database (" . get_class($e) . ")\n"); exit(4); });
    $schema = Illuminate\Support\Facades\Schema::class;
    $db = Illuminate\Support\Facades\DB::class;
    // The business data (owners and property, people, money, bookings, visitors, requests, notices, documents, accounts, history).
    $tables = ["societies", "users", "model_has_roles", "owners", "tenants", "residents", "flats", "buildings", "villa_areas",
        "maintenance_bills", "payments", "utility_bills", "expenses", "facilities", "facility_bookings", "visitors", "access_passes",
        "complaints", "complaint_updates", "service_requests", "service_providers", "notices", "events", "society_documents",
        "system_settings", "email_logs", "notifications", "personal_access_tokens", "migrations"];
    $money = ["payments" => "amount", "maintenance_bills" => "total_amount", "expenses" => "amount", "utility_bills" => "bill_amount"];
    echo "# fingerprint ", gmdate("Y-m-d H:i:s"), " UTC  (table, rows, highest id, money total)\n";
    foreach ($tables as $t) {
        if (!$schema::hasTable($t)) { continue; }
        $rows = (int) $db::table($t)->count();
        $max = $schema::hasColumn($t, "id") ? (string) ((int) $db::table($t)->max("id")) : "-";
        $sum = "-";
        if (isset($money[$t]) && $schema::hasColumn($t, $money[$t])) { $sum = number_format((float) $db::table($t)->sum($money[$t]), 2, ".", ""); }
        echo $t, "\t", $rows, "\t", $max, "\t", $sum, "\n";
    }
'
