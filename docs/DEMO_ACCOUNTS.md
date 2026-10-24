# Accounts still on a default password (inventory 2026-10-21) - NOT rotated

Source: `php artisan users:rotate-weak-passwords` (report only; nothing was changed). **32** accounts of the dev DB match a known default password
(the earlier "31" count was before one more account was created). No password or e-mail address is recorded here. The dev DB holds real people's
data, so treat every row below as a live account.

| Group | User IDs | Society | Role | Risk |
|---|---|---|---|---|
| Platform | 26 | none | **Super Admin** | **Critical** - global access, last login 2026-10-05 |
| Society admins | 1, 2, 48, 53, 67, 68 | 1, 2, 3, 4, 6, 7 | Admin | **High** - full control of a society; 67 (society 6, Royal Eden) logged in today |
| Finance / office | 31, 20 | 1 | Accountant, Staff | High (payments) / Medium |
| Gate | 64, 77 | 1, 6 | Guard | High - visitor approval/check-in; 77 belongs to live society 6 |
| Residents | 27, 28, 34, 37, 38, 39, 40, 43, 44, 45, 47, 49, 50, 52, 60, 61, 62, 63, 73, 74, 75 | 1, 2, 3, 6 | Owner / Tenant | Medium - own unit data, bills, visitors |

14 of the 32 logged in within the last 30 days (so they are in use); 5 have never logged in (53, 60, 61, 62, 63); all 32 are `active`.
The "demo" accounts with `@societyflow.com` addresses (1, 26, 27, 28, 31) are the most guessable and must not exist in production.

## Proposed plan (needs your explicit approval - nothing below has been run)

1. **Before the command:** decide per group whether the person is real and still wanted. Anything that is only a test/demo account (the
   `@societyflow.com` set, `example.com` / `test` addresses, never-logged-in 53/60-63) -> **deactivate** (`status=inactive`, reversible), do not delete.
2. **Super Admin (26) and the six society admins first**, one society at a time, with the person present: rotate and hand over the temporary
   password out of band. `php artisan users:rotate-weak-passwords --force --no-mail` rotates without sending anything (the temporary passwords are
   shown once on the console only - capture them securely). Do not use the mailing form until e-mail delivery is verified.
3. Then guards and the live society (6) residents, then the rest. Every rotation revokes that user's API tokens (the mobile app logs them out).
4. Verify afterwards: re-run the report (must list 0), try one login per role on a copy, and confirm the OTP/e-mail channels are still off or allow-listed.
5. Production: never seed these accounts (`db:seed` default path ships none); run the report against the production DB after deploy and expect 0.
