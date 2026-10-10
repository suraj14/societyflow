# Data repair plan — Piyus Chudarri's stale residency row (EXECUTED)

Status: **EXECUTED 2026-10-10 (after explicit authorisation) - see docs/TEST_DATA_2026-10-10.md.** The plan below was run, as written, against
`database/database.sqlite`. Evidence was read from the verified backup
`C:\Users\USER\SocietyFlow-backups\database-20261010-153421.sqlite`.

## Finding (`php artisan data:integrity-audit`)

| | id | detail |
|---|---|---|
| User | 73 | Piyus chudarri, society 6, `tenant_id` = 10 |
| Tenant (canonical) | 10 | flat **131** (villa 159), status `active`, move-in 2026-09-22, created 2026-09-22 09:09:30 |
| Residents row (legacy) | 15 | flat **123** (villa 160 "Suman Sadan"), type `tenant`, status `active`, move-in 2026-09-22 09:11:54 |

`UserFlatResolver::flatIdsFor(user 73)` therefore returned `[123, 131]`: the app
showed this tenant in two villas, and gave them flat 123's bills.

## Evidence it is a superseded history row, not a second tenancy

- Rent bill `RENT-6-123-202609-…` (id 45) exists for **flat 123**, ₹10,000, paid
  2026-09-22 10:23 — he first rented 160.
- `activity_logs` #29 (`tenant.stale_move_out_cleared`, 2026-10-03) records tenant 10's
  old `move_out_date` as **2026-09-24 00:00:00** — the day he left 160. Flat 131 was
  created 2026-09-24 08:04.
- The tenancy was then re-pointed to flat 131 by editing the tenant; the legacy
  residents row for flat 123 was never closed. The code gap is fixed
  (`App\Models\Tenant` updated-hook now closes it; `OwnerAndResidencyConsistencyTest`).
  The fix only applies to future moves, so this one row needs a data repair.

The move-out date comes from the audit log, not from a guess. The bill on flat 123
is history and stays attached to flat 123.

## Proposed repair (reversible, one row)

```sql
-- BEFORE: id=15 status='active', move_out_date=NULL, flat_id=123, user_id=73
UPDATE residents
   SET status = 'inactive',
       move_out_date = '2026-09-24 00:00:00'
 WHERE id = 15 AND user_id = 73 AND flat_id = 123 AND type = 'tenant' AND status = 'active';
-- expect exactly 1 row changed
```

Rollback:

```sql
UPDATE residents SET status = 'active', move_out_date = NULL
 WHERE id = 15 AND user_id = 73 AND flat_id = 123;
```

Nothing is deleted; no flat, tenant, bill or payment is changed. Flat 123's stored
status (`occupied`, owner-occupied by Suraj Suman) is already correct.

## How to run (when authorised)

1. Fresh backup (`VACUUM INTO` a timestamped file outside the repo) and integrity check.
2. Run the UPDATE above; confirm "1 row".
3. `php artisan data:integrity-audit` must report 0 contradictions;
   `UserFlatResolver::flatIdsFor(user 73)` must be `[131]`.

## Not part of this repair

- Suraj Suman's villa: his Owner record (id 19) names villa 160 in area 7 = flat 123;
  `flats.owner_id` is NULL. The assignment screens now show "Recorded owner … (not yet
  linked)". Linking `flats.owner_id` is a one-click admin action on Villa Assignments
  ("Assign Villa" → Suraj → Suman Sadan); it was **not** done automatically (P1-7).
