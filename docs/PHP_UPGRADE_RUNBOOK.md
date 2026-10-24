# PHP 8.2.12 -> 8.2.35 on the local XAMPP (runbook)

Status 2026-10-23: **NOT UPGRADED.** `G:\xampp\php-8.2.35` exists but is **empty** - nothing has been downloaded or extracted. The running
PHP (CLI and Apache's module) is still 8.2.12. Nothing below has been done to the live setup; it is the prepared, rehearsed procedure.

## 0. What the new package must be

| Property | Required value | Why |
|---|---|---|
| Version | 8.2.35 (the newest 8.2.x on the download page; **verify on php.net before relying on it** - this machine could not reach php.net to confirm it) | stay on the 8.2 line: Laravel 10 / the locked packages are tested on 8.2; 8.3+ needs its own compatibility review |
| Source | https://windows.php.net/download/ - the **PHP 8.2** section only | official builds; no third-party binaries |
| File | `php-8.2.35-Win32-vs16-x64.zip` - **Thread Safe**, **x64**, **VS16** | the running PHP is `ZTS, x64, Visual C++ 2019 (API20220829,TS,VS16)`; Apache's `php8apache2_4.dll` must match it. NTS builds do not include the Apache module |
| Integrity | compare the zip's SHA256 with the value printed beside the download | `Get-FileHash <zip> -Algorithm SHA256` |
| Runtime | Microsoft Visual C++ Redistributable 2015-2022 (x64) | already installed here (v14.50) |

Extract into `G:\xampp\php-8.2.35` so that `php.exe`, `php8ts.dll`, `php8apache2_4.dll` and `ext\` sit directly in that folder (not in a nested folder).

## 1. Already done (safe, reversible)

* Config backup with matching SHA256: `C:\Users\USER\SocietyFlow-backups\php-apache-config-20261023-180131\` (php.ini, httpd.conf, extra\httpd-xampp.conf, httpd-vhosts.conf, httpd-ssl.conf, httpd-default.conf).
* `G:\xampp\php` (179 MB) is left alone for the whole procedure; it is the rollback.
* Apache config test before any change: `httpd.exe -t` -> `Syntax OK` (Apache 2.4.58, Apache Lounge VS17).
* Pre-flight script: `scripts\php-upgrade-preflight.ps1` (read-only). Dry-run against the current PHP: build type, 11 extensions (incl. `gd`, `zip`, `intl`) and `composer check-platform-reqs` all pass.

## 2. Check the package (read-only)

```powershell
cd G:\xampp\htdocs\SocietyFlow
.\scripts\php-upgrade-preflight.ps1 -NewPhpDir G:\xampp\php-8.2.35 -ExpectedVersion 8.2.35 `
    -ZipPath <path to the zip> -ExpectedSha256 <hash from windows.php.net>
```
Do not continue unless it ends with `RESULT: all checks passed`.

## 3. Prepare the new php.ini (separately; the old one is not edited)

Copy `G:\xampp\php-8.2.35\php.ini-development` to `G:\xampp\php-8.2.35\php.ini`, then carry over only what this project needs:

* `extension_dir="G:\xampp\php-8.2.35\ext"` (**not** the old path)
* enable: `curl fileinfo mbstring openssl pdo_mysql pdo_sqlite mysqli exif` (as today) **and `gd`, `zip`** (see 6), `intl` (optional, present in the dev ini but disabled)
* copy the values of `memory_limit`, `upload_max_filesize`, `post_max_size`, `date.timezone` from the old php.ini
* do not copy the old file wholesale (it contains paths into `G:\xampp\php`)
* for anything but a dev box set `display_errors=Off`, `expose_php=Off`, `log_errors=On` (today's dev ini has `display_errors=On`, `expose_php=On`)

## 4. Switch Apache (the smallest change)

Stop Apache from the XAMPP panel (or `httpd -k stop`), then edit **only** `G:\xampp\apache\conf\extra\httpd-xampp.conf` and replace `G:/xampp/php` with `G:/xampp/php-8.2.35` in these lines (backup of the original is in section 1):

```
SetEnv MIBDIRS "G:/xampp/php-8.2.35/extras/mibs"
SetEnv PHPRC "\\xampp\\php-8.2.35"
SetEnv PHP_PEAR_SYSCONF_DIR "\\xampp\\php-8.2.35"
LoadFile "G:/xampp/php-8.2.35/php8ts.dll"
LoadFile "G:/xampp/php-8.2.35/libpq.dll"
LoadFile "G:/xampp/php-8.2.35/libsqlite3.dll"
LoadModule php_module "G:/xampp/php-8.2.35/php8apache2_4.dll"
PHPINIDir "G:/xampp/php-8.2.35"
```
(`ScriptAlias /php-cgi/` and its `<Directory>` can stay pointing at the old folder or be switched too; nothing in this app uses php-cgi.)

Then: `G:\xampp\apache\bin\httpd.exe -t` must print `Syntax OK` **before** starting Apache.

## 5. Verify what Apache really runs (no public phpinfo)

The CLI version says nothing about Apache's module, and no `phpinfo()` page is created. Use one of these instead:

* the response header: `curl.exe -sI http://localhost/SocietyFlow/public/login` - with `expose_php=On` it shows `X-Powered-By: PHP/8.2.35`;
* or, with `expose_php=Off`, the line Apache writes at start-up in `G:\xampp\apache\logs\error.log`: `Apache/2.4.58 (Win32) ... PHP/8.2.35 configured -- resuming normal operations`.

Then exercise the app: log in on the web, open an owner's edit page (Villa Area / Villa No. load), download a PDF report, upload a rent receipt, and call `/api/v1/health`.

## 6. Known gap carried into the new PHP: `gd` and `zip`

Today's `php.ini` has `;extension=gd` and `;extension=zip`, so `composer check-platform-reqs` fails (exit 2: `phpoffice/phpspreadsheet` requires both, pulled in by `maatwebsite/excel`). The DLLs exist and load (the pre-flight proves it). Enable both in the **new** php.ini; the old one is not edited by this runbook. Needed on production too, or `composer install` stops on the platform check.

## 7. Rollback (if anything above fails)

1. Stop Apache.
2. Restore `extra\httpd-xampp.conf` from the backup folder in section 1 (copy `apache__conf__extra__httpd-xampp.conf` back, checking its SHA256 starts `18B213D78CF28444`).
3. `httpd.exe -t` -> `Syntax OK`, start Apache, confirm the login page loads. Both PHP folders are untouched, so nothing else needs undoing.

After switching, also repoint anything that runs `G:\xampp\php\php.exe` by absolute path (`scripts\run_android_usb.ps1` falls back to it; the Windows PATH has no `php`). `composer.phar` is run as `php composer.phar ...`.
