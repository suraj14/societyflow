# Cleanup candidates (2026-10-21) - NOTHING WAS DELETED

Method: a read-only scan (`scratchpad/cleanup_scan.php`, not committed) searched `app`, `routes`, `resources`, `config`, `bootstrap`, `database`, `tests`
and the root PHP files for every Blade view name, controller class, model class and console-command signature. Static text search cannot see a name
built at run time, so "no reference found" is a candidate, never proof.

| Kind | Result | Evidence | Recommendation |
|---|---|---|---|
| Blade views | **0 unreferenced** | every view name appears in a `view()` / `@include` / `@extends` / route string | nothing to remove |
| Controllers | **0 unreferenced** | every controller is named in `routes/` or another class | nothing to remove |
| Models | **0 unreferenced** | each is used outside its own file | nothing to remove |
| Push / FCM / Web Push | **already gone** | no `PushNotificationService`, `FirebaseService`, `fcm` or `firebase` in `app/`, `routes/`, `config/`, `resources/`; only the historic migration `2026_02_05_create_push_notification_tables.php` mentions it | **keep the migration** (migration history); no code to remove |
| Console commands (7) | only their own file mentions the signature | `cache:clear-all`, `fix:maintenance-bills-schema`, `email:retry-failed`, `storage:setup`, `db:test`, `email:test-send`, `env:validate` | **keep**: they are run by hand on the command line, which a text search cannot see (`storage:setup` is listed in CLAUDE.md §13). `fix:maintenance-bills-schema` is a one-off repair - owner may retire it once confirmed run everywhere |
| Root dev scripts | `START-DEV.bat`, `run-server.bat`, `start-server.bat/.ps1`, `auto-start-server.ps1`, `set-static-ip.bat` | CLAUDE.md §12: "Root dev scripts the owner uses" | **do not touch** |
| Stale root documents | `DOCUMENTATION_CREATED_SUMMARY.txt` (Feb), `ACTION_GUIDE.md`, `QUICK_TEST_GUIDE.md`, `STORAGE_SETUP_INSTRUCTIONS.md`, `FEATURES.md`, `README.md` (Dec) | dated before the current work; superseded by `docs/` and `CLAUDE.md`; no code references them | **owner decision**: archive into `docs/archive/` rather than delete |
| Large files (> 5 MB) | none in the working tree outside vendor / storage / build | | - |

Never remove: `database/database.sqlite`, `storage/backups/*`, `C:\Users\USER\SocietyFlow-backups\*`, applied migrations, uploads.
