# Credential rotation checklist (SMTP, Fast2SMS, WhatsApp)

**Status: NOT ROTATED. Production readiness stays BLOCKED until this is done and verified.**
Provider dashboards are not reachable from this environment, so every provider step below is manual.
Nothing here contains a secret; fingerprints are the first 8 hex of `sha256(secret)`.

## Why

The development database stores live-looking provider credentials per society, and an earlier
browser run attempted one real SMTP login from them (it failed authentication - nothing was
delivered). Backups, copies and `.env` all carry the same values. Treat every credential below as
exposed and replace it.

## Inventory (from `php artisan credentials:audit`, 2026-10-10 baseline)

| Scope | Key | Storage | Baseline fingerprint | Note |
|---|---|---|---|---|
| society 1 | `fast2sms_api_key` | encrypted | `83e97e3e` | **also in `.env`** (`FAST2SMS_API_KEY`) |
| society 6 | `fast2sms_api_key` | encrypted | `c52b38bb` | same key as both WhatsApp rows below **by design**: WhatsApp is sent through Fast2SMS's WhatsApp API |
| society 1 | `whatsapp_api_key` | encrypted | `c52b38bb` | |
| society 6 | `whatsapp_api_key` | encrypted | `c52b38bb` | |
| society 1 | `mail_password` | encrypted | `e622e9ad` | |
| society 3 | `mail_password` | encrypted | `9955b7e0` | |
| society 6 | `mail_password` | encrypted | `7c75426b` | |
| society 1 | `bulksmsplans_api_id` | encrypted | `e5b615bd` | |
| society 1 | `bulksmsplans_api_password` | encrypted | `66af2fe2` | short value - weak/placeholder |
| society 1 | `razorpay_secret` | encrypted | `5e884898` | 8 characters - a placeholder, never a real gateway secret; clear it |
| `.env` | `MAIL_PASSWORD`, `MAIL_USERNAME` | plaintext file | - | gitignored, untracked |
| `.env` | `FAST2SMS_API_KEY`, `FAST2SMS_*` | plaintext file | - | duplicates the society-1 DB value |

Not secrets (identifiers): `whatsapp_phone_number_id`, `whatsapp_waba_id`, sender/template ids.

## Steps (do them in this order)

1. **Freeze outbound messaging** everywhere you are not testing: run with `EXTERNAL_MESSAGING_ENABLED=false`
   (queue workers too - it is read per process).
2. **Revoke at the provider, then issue new ones**
   - **SMTP** (the transactional-mail provider behind `smtp.*` / the API-key style username): delete the
     SMTP credential / API key in the provider console, create a new one scoped to sending only.
   - **Fast2SMS**: Dev API -> regenerate the API key (the old key stops working immediately).
   - **WhatsApp**: sent through **Fast2SMS's WhatsApp API** (not Meta directly), so it uses the same Fast2SMS
     API key - regenerating it in step above rotates SMS and WhatsApp together. Enter the new key in the
     society's WhatsApp *and* SMS settings (they are separate rows that must match). Template names and
     the phone-number / WABA ids are not secrets and do not change.
   - **BulkSMSPlans** (if still used): change the account password and API id; otherwise clear the settings.
   - **Razorpay placeholder**: clear `razorpay_secret` (no gateway is wired; see CLAUDE.md §11).
   - Use a **different** SMTP credential per environment (dev/staging/production) so a leaked dev copy can never send production mail.
3. **Enter the new values through the application**, never by editing the database: Settings -> Email / SMS /
   WhatsApp (per society) and Super Admin -> Settings (global SMTP). They are stored encrypted and shown
   masked. Update `.env` (`MAIL_PASSWORD`, `FAST2SMS_API_KEY`, ...) only if that fallback is still wanted,
   or better, remove the `.env` copies so there is a single source. Then `php artisan config:clear`.
4. **Prove it changed:** `php artisan credentials:audit` - every fingerprint above must be different, no row
   may say `PLAINTEXT` or `also in .env`, and the exit code must be 0.
5. **Purge the old values** - they live on after revocation only as dead strings, but still:
   - the snapshots in `C:\Users\USER\SocietyFlow-backups\` (7 files) contain the old encrypted secrets and
     real personal data: keep only what you need, store the rest encrypted or delete them;
   - `database/database.sqlite` is gitignored; confirm no copy was committed (`git log --all -- database/`).
6. **Consider rotating `APP_KEY`** (it decrypts every stored secret and session). That invalidates stored
   encrypted settings, so re-enter them (step 3) *after* changing it, and expect everyone to be logged out.
7. **Verify with approved recipients** in a staging environment (kill switch OFF, one society, your own
   test number/inbox): Settings -> "Send test email", one SMS and one WhatsApp template test. Record
   provider acceptance (HTTP 2xx / message id) separately from actual receipt on the handset/inbox.
   Mark delivery PASS only when the message was received.
8. Re-enable normal operation, then update `docs/CURRENT_STATUS.md` with the date, who rotated, and the
   new `credentials:audit` output (fingerprints only).

## Guards now in place (verified by tests)

- `EXTERNAL_MESSAGING_ENABLED=false` forces mail to the `log` driver (including the per-society SMTP
  override), suppresses SMS / Fast2SMS / WhatsApp HTTP calls, and a `MessageSending` guard cancels any mail
  whose transport is not `log`/`array` (`ExternalMessagingKillSwitchTest`).
- The "Send test email" buttons report "Not sent" while disabled instead of claiming success.
- `credentials:audit` is read-only and never prints a secret (`CredentialAuditCommandTest`).
- Failed-form logging strips passwords and tokens (`HandlesFormSubmissions`).
- Automated tests never reach a provider: they use transport overrides, `Mail::fake()`, or unresolvable
  `.invalid` hosts.

## Addendum 2026-10-12 - exposed Fast2SMS key and the operator's secure steps

A live Fast2SMS API key was pasted into a chat session during development. Treat it as **exposed**: it was never used by any
test or by the assistant, never written to a file, but it must be replaced.

1. In the Fast2SMS dashboard (Dev API), generate a new key and delete the old one. Do this first - nothing else matters while the old key is live.
2. Enter the new key only in Settings -> SMS of each society (it is stored encrypted; the page shows it masked and never echoes it).
   Do not put it in `.env`, a ticket, a chat or a commit. Remove the duplicated `FAST2SMS_API_KEY` and `MAIL_PASSWORD` lines from `.env` if present.
3. Keep `EXTERNAL_MESSAGING_ENABLED=false` on every non-production copy; the per-society key is applied over `.env` at send time,
   so a restored production backup on a laptop can send real messages unless the flag is off.
4. Send ONE test from Settings -> SMS to a number you own, then check the Delivery Log. "Not sent / Skipped" means nothing left the server.
5. Run `php artisan credentials:audit` (read-only, fingerprints only). Exit 0 means no stored credential matches the old baseline.
6. Rotate SMTP, WhatsApp and any gateway secrets the same way (see the inventory above), then purge the old DB snapshots in `C:\Users\USER\SocietyFlow-backups` that hold the previous encrypted values (operator decision; nothing was deleted by the assistant).