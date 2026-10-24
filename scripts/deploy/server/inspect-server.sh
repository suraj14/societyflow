#!/usr/bin/env bash
# inspect-server.sh - READ-ONLY look at a SocietyFlow install on a CloudPanel server.
#
#   bash inspect-server.sh [APP_DIR] [SITE_USER] [DOMAIN]
#   defaults: /home/joboapps-societyflow/htdocs/societyflow.joboapps.com  joboapps-societyflow  societyflow.joboapps.com
#   PHP_BIN=/usr/bin/php8.2 bash inspect-server.sh      # when the default `php` is not the site's PHP
#
# It changes nothing: no file is written, no service is touched, nothing is restarted, no migration or cache command is run.
# It prints no secret: .env is never printed (only a fixed list of non-secret keys is read from it), the database is asked only for its
# version and a table count, and cron lines are masked. Paste the output to whoever is helping you; it is meant to be safe to share.
set -u

APP_DIR="${1:-/home/joboapps-societyflow/htdocs/societyflow.joboapps.com}"
SITE_USER="${2:-joboapps-societyflow}"
DOMAIN="${3:-societyflow.joboapps.com}"
PHP_BIN="${PHP_BIN:-php}"

sect() { printf '\n==== %s\n' "$*"; }
have() { command -v "$1" >/dev/null 2>&1; }
ok()   { printf '  [ok]   %s\n' "$*"; }
bad()  { printf '  [!!]   %s\n' "$*"; }
info() { printf '         %s\n' "$*"; }

# Read one NON-SECRET key from .env without sourcing the file. Only keys passed literally below are ever requested.
env_val() {
    local f="$APP_DIR/.env"
    [ -r "$f" ] || { echo "(.env unreadable)"; return; }
    local v
    v="$(grep -E "^$1=" "$f" | tail -n1 | cut -d= -f2-)"
    v="${v%\"}"; v="${v#\"}"; v="${v%\'}"; v="${v#\'}"
    [ -n "$v" ] && echo "$v" || echo "(not set)"
}

sect "Who / when"
info "user: $(id -un)   host: $(hostname)   time: $(date -u '+%Y-%m-%d %H:%M:%S UTC')"
[ "$(id -un)" = "$SITE_USER" ] && ok "running as the site user" || bad "running as $(id -un), not $SITE_USER (permission checks below will describe THIS user)"
info "app dir: $APP_DIR"
[ -d "$APP_DIR" ] && ok "app dir exists" || { bad "app dir does not exist"; exit 1; }

sect "Current release (what is deployed)"
if [ -r "$APP_DIR/RELEASE.txt" ]; then sed 's/^/         /' "$APP_DIR/RELEASE.txt"; else info "no RELEASE.txt - this install was not deployed with the packaged process (or predates it)"; fi
[ -d "$APP_DIR/.git" ] && info "a .git directory exists here: $(git -C "$APP_DIR" log -1 --format='%h %cd %s' --date=short 2>/dev/null)"
info "top level (names only):"; ls -A "$APP_DIR" | tr '\n' ' ' | fold -s -w 100 | sed 's/^/           /'; echo
for p in artisan composer.json composer.lock vendor/autoload.php public/index.php bootstrap/app.php .env; do
    [ -e "$APP_DIR/$p" ] && ok "$p present" || bad "$p MISSING"
done
[ -f "$APP_DIR/storage/framework/down" ] && bad "THE APPLICATION IS IN MAINTENANCE MODE (storage/framework/down exists)" || ok "not in maintenance mode"
info "last modified: app=$(stat -c '%y' "$APP_DIR/app" 2>/dev/null | cut -d. -f1)  config=$(stat -c '%y' "$APP_DIR/config" 2>/dev/null | cut -d. -f1)  composer.lock=$(stat -c '%y' "$APP_DIR/composer.lock" 2>/dev/null | cut -d. -f1)"

sect "PHP - command line"
info "PHP_BIN=$PHP_BIN -> $(command -v "$PHP_BIN" 2>/dev/null || echo 'NOT FOUND')"
if have "$PHP_BIN"; then
    info "$("$PHP_BIN" -v | head -n1)"
    info "php.ini in use: $("$PHP_BIN" --ini | grep -E 'Loaded Configuration' | sed 's/.*: *//')"
    mods="$("$PHP_BIN" -m 2>/dev/null)"
    for m in gd zip pdo_mysql mysqlnd curl mbstring openssl fileinfo xml dom tokenizer ctype json bcmath intl exif; do
        echo "$mods" | grep -qix "$m" && ok "ext-$m" || { case "$m" in gd|zip|pdo_mysql|curl|mbstring|openssl|fileinfo|xml|dom|tokenizer|ctype|json) bad "ext-$m MISSING (required)";; *) info "ext-$m not loaded (optional)";; esac; }
    done
fi
info "other PHP binaries on the box: $(ls /usr/bin/php[0-9]* 2>/dev/null | tr '\n' ' ')"

sect "PHP - the web runtime (PHP-FPM) that serves this site"
found=0
for f in /etc/php/*/fpm/pool.d/*.conf; do
    [ -r "$f" ] || continue
    if grep -qE "^\s*user\s*=\s*$SITE_USER\s*$" "$f"; then
        found=1; ver="$(echo "$f" | cut -d/ -f4)"
        ok "pool for $SITE_USER: PHP $ver  ($f)"
        grep -E '^\s*(listen|pm\.max_children|php_admin_value\[(memory_limit|upload_max_filesize|post_max_size|max_execution_time)\]|php_admin_flag\[display_errors\]|php_value\[(memory_limit|upload_max_filesize|post_max_size)\])' "$f" | sed 's/^/           /'
        have systemctl && info "service php${ver}-fpm: $(systemctl is-active "php${ver}-fpm" 2>/dev/null)"
        cli_major_minor="$("$PHP_BIN" -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;' 2>/dev/null)"
        [ "$cli_major_minor" = "$ver" ] && ok "CLI PHP ($cli_major_minor) matches the web PHP ($ver)" || bad "CLI PHP is $cli_major_minor but the web PHP is $ver - run artisan/composer with PHP_BIN=/usr/bin/php$ver"
    fi
done
[ "$found" = 0 ] && info "no FPM pool file readable for $SITE_USER (CloudPanel keeps them under /etc/php/<ver>/fpm/pool.d/). Check the PHP version in CloudPanel -> Sites -> $DOMAIN -> Settings."
info "from outside (run on your PC, read-only):  curl -sI https://$DOMAIN/login | grep -i -E 'server|x-powered-by'"

sect "Nginx virtual host and document root"
vh=""; for c in "/etc/nginx/sites-enabled/$DOMAIN.conf" "/etc/nginx/sites-enabled/$DOMAIN" "/etc/nginx/conf.d/$DOMAIN.conf"; do [ -r "$c" ] && vh="$c" && break; done
if [ -n "$vh" ]; then
    info "vhost: $vh"
    grep -nE '^\s*(server_name|root|index|client_max_body_size|fastcgi_pass|fastcgi_read_timeout)\b|location ~|try_files' "$vh" | sed 's/^/           /' | head -40
    root_line="$(grep -E '^\s*root\s' "$vh" | head -n1 | sed -E 's/^\s*root\s+//; s/;\s*$//')"
    [ "$root_line" = "$APP_DIR/public" ] && ok "document root is $root_line" || bad "document root is '$root_line' - expected $APP_DIR/public"
    grep -qE 'location[^{]*(storage|uploads)[^{]*\.\(?(php|phtml|phar)' "$vh" && ok "vhost denies script execution under /storage" || bad "no rule stops *.php under /storage from being executed (uploads live there) - see docs/DEPLOYMENT_CLOUDPANEL.md section 7"
else
    info "vhost not readable by this user (normal on some CloudPanel setups). See CloudPanel -> Sites -> $DOMAIN -> Vhost. Expected root: $APP_DIR/public"
fi

sect "Laravel and database (asks only for versions and counts)"
if [ -r "$APP_DIR/vendor/autoload.php" ] && have "$PHP_BIN"; then
    ( cd "$APP_DIR" && "$PHP_BIN" -r '
        require "vendor/autoload.php";
        $app = require "bootstrap/app.php";
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        echo "         laravel=", $app->version(), "  app_env=", config("app.env"), "  debug=", var_export((bool) config("app.debug"), true), "\n";
        $c = config("database.default"); $d = config("database.connections.$c.driver");
        echo "         db_connection=$c  db_driver=$d\n";
        try {
            Illuminate\Support\Facades\DB::connection()->getPdo();
            $v = $d === "sqlite" ? Illuminate\Support\Facades\DB::selectOne("select sqlite_version() as v")->v : Illuminate\Support\Facades\DB::selectOne("select version() as v")->v;
            echo "         db_version=$v\n";
            $schema = Illuminate\Support\Facades\Schema::getConnection();
            $tables = $d === "sqlite" ? Illuminate\Support\Facades\DB::selectOne("select count(*) as n from sqlite_master where type=\"table\"")->n
                                      : Illuminate\Support\Facades\DB::selectOne("select count(*) as n from information_schema.tables where table_schema = database()")->n;
            echo "         tables=$tables\n";
            if (Illuminate\Support\Facades\Schema::hasTable("migrations")) {
                $n = Illuminate\Support\Facades\DB::table("migrations")->count();
                $last = Illuminate\Support\Facades\DB::table("migrations")->orderByDesc("id")->value("migration");
                echo "         migrations_applied=$n  last=$last\n";
                $files = array_map(fn ($f) => basename($f, ".php"), glob(database_path("migrations/*.php")));
                $ran = Illuminate\Support\Facades\DB::table("migrations")->pluck("migration")->all();
                $pending = array_values(array_diff($files, $ran));
                echo "         migration_files_on_disk=", count($files), "  pending=", count($pending), "\n";
                foreach (array_slice($pending, 0, 30) as $p) { echo "           pending: $p\n"; }
            } else { echo "         no migrations table\n"; }
        } catch (Throwable $e) { echo "         database_error=", get_class($e), " (message withheld: it can contain the host or user name)\n"; }
    ' 2>&1 | sed 's/^/  /' )
else
    bad "cannot bootstrap the app (vendor/ or php missing)"
fi
have composer && info "composer: $(composer --version 2>/dev/null | head -n1)" || info "composer not on PATH for this user"
have mysqldump && info "mysqldump: $(mysqldump --version 2>/dev/null)" || bad "mysqldump not found - db-backup.sh needs it (MySQL/MariaDB sites)"

sect "Settings that matter (non-secret keys only)"
for k in APP_ENV APP_DEBUG APP_URL DB_CONNECTION SESSION_DRIVER SESSION_SECURE_COOKIE CACHE_DRIVER QUEUE_CONNECTION FILESYSTEM_DISK LOG_CHANNEL LOG_LEVEL MAIL_MAILER TRUSTED_PROXIES CORS_ALLOWED_ORIGINS EXTERNAL_MESSAGING_ENABLED OTP_EXPOSE_DEV_CODE; do
    printf '         %-28s %s\n' "$k" "$(env_val "$k")"
done
[ "$(env_val APP_DEBUG)" = "true" ] && bad "APP_DEBUG=true on a live site"
[ "$(env_val OTP_EXPOSE_DEV_CODE)" = "true" ] && bad "OTP_EXPOSE_DEV_CODE=true - one-time codes would be returned in API answers"
[ "$(env_val EXTERNAL_MESSAGING_ENABLED)" = "true" ] && info "EXTERNAL_MESSAGING_ENABLED=true: real e-mail/SMS/WhatsApp can be sent (expected on a live site; check the allow-list setting is intended)"

sect "Storage and uploads"
for p in public/storage storage storage/app storage/app/public storage/app/society-documents storage/logs storage/framework bootstrap/cache; do
    if [ -e "$APP_DIR/$p" ] || [ -L "$APP_DIR/$p" ]; then printf '         %-34s %s\n' "$p" "$(stat -c '%A %U:%G' "$APP_DIR/$p") $( [ -L "$APP_DIR/$p" ] && echo "-> $(readlink "$APP_DIR/$p")")"; else printf '         %-34s %s\n' "$p" "(missing)"; fi
done
if [ -L "$APP_DIR/public/storage" ]; then
    [ "$(readlink -f "$APP_DIR/public/storage")" = "$(readlink -f "$APP_DIR/storage/app/public")" ] && ok "public/storage is a symlink to storage/app/public" || bad "public/storage is a symlink to somewhere else"
elif [ -d "$APP_DIR/public/storage" ]; then bad "public/storage is a REAL DIRECTORY holding files - do not run storage:link / storage:setup; see docs/DEPLOYMENT_CLOUDPANEL.md section 6"
else bad "public/storage does not exist (uploads would not be served)"; fi
for d in storage/app/public storage/app/society-documents; do
    [ -d "$APP_DIR/$d" ] && info "$d: $(find -L "$APP_DIR/$d" -type f 2>/dev/null | wc -l) files, $(timeout 60 du -sh "$APP_DIR/$d" 2>/dev/null | cut -f1)"
done
for p in storage bootstrap/cache; do [ -w "$APP_DIR/$p" ] && ok "$p writable by $(id -un)" || bad "$p NOT writable by $(id -un)"; done
n_exec="$(find -L "$APP_DIR/public/storage" "$APP_DIR/storage/app/public" -type f \( -iname '*.php' -o -iname '*.phtml' -o -iname '*.phar' -o -iname '*.pht' -o -iname '*.phps' \) 2>/dev/null | sort -u | wc -l)"
[ "$n_exec" = 0 ] && ok "no script-type files among the uploads" || { bad "$n_exec script-type file(s) among the uploads (list below) - investigate before anything else"; find -L "$APP_DIR/public/storage" "$APP_DIR/storage/app/public" -type f \( -iname '*.php' -o -iname '*.phtml' -o -iname '*.phar' -o -iname '*.pht' -o -iname '*.phps' \) 2>/dev/null | sort -u | head -20 | sed 's/^/           /'; }

sect "Things that should not be web-reachable"
stray="$(find "$APP_DIR/public" -maxdepth 2 \( -iname '*.php' ! -name index.php -o -iname '.env*' -o -iname '*.sql' -o -iname '*.sqlite' -o -iname '*.zip' -o -iname '*.tar*' -o -iname '*.bak' -o -iname 'phpinfo*' -o -iname '*.log' \) -not -path "$APP_DIR/public/storage/*" 2>/dev/null)"
[ -z "$stray" ] && ok "nothing suspicious directly under public/" || { bad "unexpected files under public/:"; echo "$stray" | sed 's/^/           /'; }
[ -e "$APP_DIR/.env" ] && info ".env permissions: $(stat -c '%a %U:%G' "$APP_DIR/.env")   (600 or 640 expected)"
[ -e "$APP_DIR/storage/app/private" ] && info "storage/app/private exists (keep it outside public/)"

sect "Disk space and ownership"
df -hP "$APP_DIR" /home /tmp 2>/dev/null | sed 's/^/         /'
df -iP "$APP_DIR" 2>/dev/null | sed 's/^/         /'
foreign="$(find "$APP_DIR" -maxdepth 3 ! -user "$SITE_USER" -not -path '*/node_modules/*' 2>/dev/null | head -5)"
[ -z "$foreign" ] && ok "top levels owned by $SITE_USER" || { bad "paths not owned by $SITE_USER (first 5):"; echo "$foreign" | sed 's/^/           /'; }
info "app dir size: $(timeout 90 du -sh "$APP_DIR" 2>/dev/null | cut -f1)   (vendor: $(timeout 60 du -sh "$APP_DIR/vendor" 2>/dev/null | cut -f1))"

sect "Backups that already exist"
for d in "/home/$SITE_USER/backups" "/home/$SITE_USER/backups/societyflow" "/home/$SITE_USER/releases"; do
    [ -d "$d" ] && { info "$d:"; ls -lht "$d" 2>/dev/null | head -8 | sed 's/^/           /'; } || info "$d: (does not exist)"
done
info "CloudPanel's own backups (Sites -> $DOMAIN -> Backups) live in the panel; check there for a recent one before deploying."
info "other backup folders under /home you can see: $(ls -d /home/*/backups 2>/dev/null | tr '\n' ' ')"

sect "Scheduler, queue workers"
have crontab && { crontab -l 2>/dev/null | grep -E 'artisan|schedule:run|queue:work' | sed -E 's/((token|key|secret|password|pass)[a-z_]*=)[^ ]+/\1***/Ig' | sed 's/^/         /'; [ "${PIPESTATUS[0]}" -ne 0 ] && info "no crontab for this user"; } || info "crontab not available"
info "running workers: $(ps -u "$SITE_USER" -o pid=,etime=,args= 2>/dev/null | grep -E 'queue:work|horizon' | grep -v grep | wc -l)  (QUEUE_CONNECTION above: with 'sync' no worker is needed)"
have supervisorctl && info "supervisor: $(supervisorctl status 2>&1 | head -3 | tr '\n' ';')"

sect "Recent application errors (counts only)"
latest="$(ls -t "$APP_DIR"/storage/logs/*.log 2>/dev/null | head -n1)"
[ -n "$latest" ] && { info "log: $(basename "$latest")  size $(stat -c %s "$latest") bytes  modified $(stat -c '%y' "$latest" | cut -d. -f1)"; info "ERROR lines in the last 2000: $(tail -n 2000 "$latest" | grep -c '\.ERROR')   CRITICAL: $(tail -n 2000 "$latest" | grep -c '\.CRITICAL')"; } || info "no log files"

printf '\nDone. Nothing was changed.\n'
