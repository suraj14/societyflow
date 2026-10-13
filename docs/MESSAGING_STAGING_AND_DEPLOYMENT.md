# Messaging: staging delivery verification and deployment checklist

Automated tests in this repository use fake HTTP and fake mail. **They prove our requests, our rules and our error handling; they do not prove that a message reached a phone or a mailbox.** This document is what a person does to prove that, on a staging server, with an approved recipient - and what must be true before anything is deployed. Nothing here is run by the assistant.

Status words used everywhere (screens, logs, this document):

| Word | Meaning | Where you can see it |
|---|---|---|
| **not sent** | nothing was attempted (integration off, kill switch, missing key / template / number) | Delivery Logs: "Skipped - not sent" |
| **accepted** | the provider answered `return: true` / HTTP 200 and gave a request id. The provider now owns the message | Delivery Logs `sent`; provider dashboard |
| **delivered** | a handset / mailbox actually has it | **only** the recipient's device or mailbox - SocietyFlow does not read delivery receipts and never claims this |
| **failed** | the provider or mail server refused, or the request timed out | Delivery Logs `failed` + reason |

## A. Manual staging verification

### A0. Preconditions for every channel
1. A staging server (not production) with its own database - restored from a backup is fine, **but** a restored production database carries real society credentials and real resident numbers. Before anything else, either blank the provider credentials and replace residents' phone numbers / emails, or give the staging society only approved tester accounts.
2. `APP_ENV=staging` (or `production` on a pre-release server), `APP_DEBUG=false`, `OTP_EXPOSE_DEV_CODE` **absent or false**.
3. `EXTERNAL_MESSAGING_ENABLED=true` on the staging server only for the duration of the test (it is the global kill switch; with `false` everything below is "not sent" by design).
4. Credentials are **new** ones (see `CREDENTIAL_ROTATION.md`), entered through Settings (stored encrypted), never in a chat or ticket.
5. A named, approved recipient for each channel, agreed in writing by the owner: one mobile number (SMS + WhatsApp + OTP) and one mailbox. Do not use a number taken from a screenshot or from the database.
6. A second, *unapproved* phone / mailbox nearby to prove it received nothing (see "no unexpected recipient").

### A1. OTP by SMS (Fast2SMS `POST /dev/otp/send`, documented at docs.fast2sms.com/reference/send-otp)
Preconditions: Settings -> SMS / OTP: Integration active ON, API key saved, **route = OTP** and the **OTP ID** (the Fast2SMS OTP template id - *not* a DLT Message ID) saved; the tester's number is saved on exactly one active staging account.

| Step | Action | Expected / how to check |
|---|---|---|
| 1 | App (release or debug build pointed at staging) -> *Login with OTP* -> enter the tester's number -> **Send OTP** | App shows the verify screen. The HTTP answer contains **no** `otp` field (browser/proxy log or `curl`); the screen shows no "Development server" line |
| 2 | Look at the tester's phone | SMS arrives from the approved sender within ~1 minute -> **delivered**. Note the time |
| 3 | Fast2SMS dashboard (Dev API -> OTP) | One request with the `request_id` -> **accepted** |
| 4 | `storage/logs/laravel.log` and `email_logs` | No four-digit code, no key. A failure would show a reason word, not the code |
| 5 | Tap **Resend** before 30 s | "Please wait N seconds before asking for another code" (HTTP 429). After 30 s a new SMS arrives and the first code stops working |
| 6 | Enter a wrong code | "That code is not valid or has expired..." (same words for wrong / expired / used) |
| 7 | Enter the right code | Signed in to the account's home screen; going Back does not return to a login screen |
| 8 | Enter the same code again after signing out | Refused (one use) |
| 9 | Wait 5 minutes, enter an unused code | Refused (expired) |
| 10 | Enter a number that has no account | Same "If this number is registered..." answer, and **no SMS** to that number |

### A2. Ordinary SMS (Quick SMS or DLT)
Preconditions: Settings -> SMS: Integration active, API key, **Sender ID** (DLT, 3-6 letters) for DLT; Message Templates -> SMS: an **active** template for the event with the exact approved text and its **Fast2SMS Message ID** (leave the Message ID empty only for Quick SMS); "notifications by SMS" on.
1. Trigger the event as an admin of the staging society (e.g. publish a notice). **A notice goes to every resident of the society who has a phone number** - so first look at Admin -> People: the only residents with numbers must be approved testers.
2. Delivery Logs (`/admin/email-logs`, channel SMS): one row per recipient, status **sent** (= accepted). Count equals the number of testers.
3. The tester's phone receives exactly one SMS with the variables filled (no `{{...}}`) -> **delivered**.
4. Fast2SMS dashboard: sent count equals the log count.
5. Repeat the same action within a minute: no second SMS (duplicate suppression); the log shows "skipped duplicate".
6. Turn "Integration active" off and repeat: **not sent**, log says Skipped.

### A3. WhatsApp
Preconditions: Settings -> WhatsApp: Integration active, API key (or the shared SMS key), **Phone Number ID**, country code; Message Templates -> WhatsApp: a template you pasted from the Fast2SMS portal that is **approved there** (SocietyFlow cannot check that), its Message ID, language, text, the value picked for each `{{n}}`, set Active.
1. Settings -> WhatsApp -> *Test WhatsApp* with the approved tester number and that template (typed values or the sample values), **or** trigger the real event for a tester resident.
2. Answer shown: "accepted ... not a delivery confirmation". Delivery Logs: status sent -> **accepted**.
3. The tester's WhatsApp receives the template message with real values -> **delivered**. If the template is not approved / the Phone Number ID is wrong the provider refuses: Delivery Logs shows **failed** with the provider's reason.
4. A conversation outside the 24-hour customer window can only receive approved templates; SocietyFlow never sends free text to residents.
5. Switch the template off: the same event now sends nothing and says "No active WhatsApp template configured".

### A4. Email
Two different mailers are involved - test both:
- **Notification emails** (welcome, payment, notices, bookings ...) use the society's own SMTP saved in Settings -> Email.
- **Password-reset link** uses the server's default mailer (`MAIL_*` in the server environment).

1. Settings -> Email -> *Send test email* to the approved mailbox -> arrives (check the From name/address, that it is not in spam, and the TLS lock in the header details) -> **delivered**.
2. `/forgot-password` for the tester account -> the page always says "If an account exists..." (also for an unknown address) -> a mail with a link arrives within a minute.
3. Open the link, set a strong password -> "Your password has been reset" -> sign in works. Open the **same link again** -> refused. Wait over 60 minutes with a fresh link -> refused.
4. Ask for the link three times in a minute -> only one email (throttle).
5. Stop the mail server (or give a wrong password in `.env`) and ask for a link: the page still answers the same, `laravel.log` shows "Password reset email could not be sent" **without** the token or server name, and, with a queue worker, `failed_jobs` shows the retried job.
6. Create a user as admin: the welcome email carries the temporary password; afterwards the Delivery Log detail shows `[hidden]` instead of it.
7. If `QUEUE_CONNECTION` is not `sync`: `php artisan queue:work` is running (supervisor), and `failed_jobs` exists (this repository has **no** migration for `jobs` / `failed_jobs`: with a `redis` queue the failed-job table must still be created - `php artisan queue:failed-table` then migrate - or failed sends vanish).

### A5. Proving no unexpected recipient
- Before: list the staging society's users with a phone / email. After: compare the Delivery Log recipients with the approved list.
- Provider dashboards (Fast2SMS SMS and WhatsApp logs, the SMTP provider's activity log) must show only approved numbers / addresses.
- The unapproved test phone must have received nothing.
- If anything unexpected appears: set `EXTERNAL_MESSAGING_ENABLED=false` first, then investigate.

### A6. What to record
Date, build/commit, server, recipient (approved), channel, action, provider request ids, receipt time, screenshot of the device, and the result word from the table above. A channel is "live delivery verified" only with a **delivered** entry by a person; until then it is "mocked only".

## B. Deployment checklist (do not deploy automatically)

**Before**
- [ ] Production API base URL for the app: `https://societyflow.joboapps.com/api/v1` (compiled into release builds; a release build ignores any local override - `ApiConfig.resolveBaseUrl`, pinned by `api_config_test`). On 2026-10-13 a read-only `GET /api/v1/health` answered 200 over HTTPS behind Cloudflare with HSTS. That proves the host is up; it does **not** show which commit is deployed.
- [ ] Production `.env` (by the operator, never in git): `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://...` (reset links and QR codes are built from it - the development file points at a LAN `http://` address), `LOG_LEVEL=warning` or higher (the `log` mail driver and debug lines must not run in production), `OTP_EXPOSE_DEV_CODE` absent, `EXTERNAL_MESSAGING_ENABLED=true`, `MAIL_MAILER=smtp` with real `MAIL_*`, `QUEUE_CONNECTION` per the queue plan below, a strong unique `APP_KEY` (the OTP hash and encrypted settings depend on it: **changing it invalidates stored secrets and pending codes**).
- [ ] Credentials rotated and re-entered through Settings; `php artisan credentials:audit` exits 0 (`CREDENTIAL_ROTATION.md`).
- [ ] Database backup taken and a restore of it proven on a spare database.
- [ ] `php artisan migrate:status` reviewed. Migrations added since the last production run in this working tree: `2026_10_13_000001` (sms templates table), `2026_10_13_000002` (bill type unique index), `2026_10_14_000001` (WhatsApp templates become manual records; carries over existing attachments), `2026_10_14_000002` (one-time codes stored hashed + purpose; retires waiting codes), `2026_10_14_000003` (hides passwords/links already stored in `email_logs.variables`; irreversible), `2026_10_14_000004` (MySQL/MariaDB only: stops `mobile_otps.expires_at`, `notice_reads.read_at` and `society_document_acknowledgements.acknowledged_at` rewriting themselves on every UPDATE - an expired one-time code stayed valid; read / acknowledgement times already overwritten cannot be recovered). Confirm which of them production already has.
- [ ] Rollback plan per migration: `2026_10_13_000002`, `2026_10_14_000001` and `2026_10_14_000002` have a tested `down()` (`NewMigrationsSafetyTest`; the bill index refuses to roll back if a unit has both a rent and a maintenance bill for one month - nothing is deleted; rolling back the code table retires waiting codes); `2026_10_14_000003` and `000004` cannot be meaningfully undone (restore the backup if ever needed).
- [ ] Cache plan: `php artisan config:cache route:cache view:cache` after deploy. The code reads provider defaults through `config('sms.*')`, never `env()`, so a cached configuration behaves like an uncached one (`SmsConfigCacheSafetyTest` fails the build if `env()` reappears outside `config/`).
- [ ] Queue worker: with `QUEUE_CONNECTION=redis` a supervised `php artisan queue:work --tries=3` and a `failed_jobs` table (see A4.7). With `sync` notifications run inside the request.
- [ ] Scheduler: cron `* * * * * php artisan schedule:run` (bill reminders, event status, missed-shift sweep).
- [ ] Storage: `php artisan storage:link`, `storage/` and `bootstrap/cache/` writable by the web user, public assets reachable.
- [ ] Android release: `flutter build apk --release` (or `appbundle`) with the project's upload keystore (`android/key.properties`, git-ignored; **absent in the development checkout, where the same command produces a debug-signed APK that must not be published**); verify the signed build's `ApiConfig.baseUrl` is the HTTPS domain and that the OTP screen shows no development line.
- [ ] Approved templates exist for the events you want live (SMS Message IDs, WhatsApp templates) - see the event matrix in `SOCIETYFLOW_STABILIZATION.md`.

**Deploy (by the operator)**: maintenance mode on -> pull -> `composer install --no-dev -o` -> backup -> `php artisan migrate --force` -> caches -> restart workers -> maintenance mode off.

**Smoke test right after**: `/api/v1/health`; web login; Login with OTP for one approved account (A1); forgot-password mail (A4); a bill list; admin dashboard; a gate page for a guard; Settings pages open.

**Roll back when**: health is not 200; sign-in fails for approved accounts; OTP or reset mail fails for the approved tester; any 5xx on the smoke list; a migration errors. Action: maintenance mode on, restore the backup taken before `migrate`, redeploy the previous release, workers restarted.
